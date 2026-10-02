<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Tests\Unit\Enrollment;

use OCA\OtherAccounts\Enrollment\ConnectionKind;
use OCA\OtherAccounts\Enrollment\EmailAddress;
use OCA\OtherAccounts\Enrollment\Enrollment;
use OCA\OtherAccounts\Enrollment\EnrollmentException;
use OCA\OtherAccounts\Enrollment\ImapCredential;
use OCA\OtherAccounts\Http\HttpResponse;
use OCA\OtherAccounts\Mail\MailProtocolException;
use OCA\OtherAccounts\Mail\MailProver;
use OCA\OtherAccounts\Service\GoogleGrant;
use OCA\OtherAccounts\Service\NetBoxMailboxes;
use OCA\OtherAccounts\Service\OpenBao;
use OCA\OtherAccounts\Tests\Support\ScriptedChannel;
use OCA\OtherAccounts\Tests\Support\ScriptedHttpClient;
use PHPUnit\Framework\TestCase;

final class EnrollmentTest extends TestCase
{
    private const string SERVICES = 'prod/zephyrex/ct/prod-stalwart/services';

    /**
     * @param list<string> $jamesonSendAs
     * @param list<string> $hannahSendAs
     * @return array{results: list<array<string, mixed>>}
     */
    private static function netbox(array $jamesonSendAs = [], array $hannahSendAs = []): array
    {
        return ['results' => [
            ['id' => 3, 'local_part' => 'jameson', 'domain' => ['name' => 'thegrieves.ca'], 'send_as_addresses' => $jamesonSendAs],
            ['id' => 4, 'local_part' => 'hannah', 'domain' => ['name' => 'thegrieves.ca'], 'send_as_addresses' => $hannahSendAs],
        ]];
    }

    /**
     * @param array{results: list<array<string, mixed>>} $netbox
     * @param array<string, HttpResponse> $extra
     */
    private static function http(array $netbox, array $extra = []): ScriptedHttpClient
    {
        return new ScriptedHttpClient([
            ...$extra,
            'POST /v1/auth/approle/login' => ScriptedHttpClient::json(200, ['auth' => ['client_token' => 'bao-tok']]),
            'POST /v1/secret/data/' => ScriptedHttpClient::json(200, ['data' => []]),
            'DELETE /v1/secret/metadata/' => ScriptedHttpClient::json(204, []),
            'GET /api/plugins/email/mailboxes/' => ScriptedHttpClient::json(200, $netbox),
            'PATCH /api/plugins/email/mailboxes/' => ScriptedHttpClient::json(200, []),
        ]);
    }

    private static function enrollment(ScriptedHttpClient $http, ScriptedChannel $mail): Enrollment
    {
        return new Enrollment(
            new OpenBao($http, 'http://bao', 'secret', 'role', 'sid'),
            self::SERVICES,
            new NetBoxMailboxes($http, 'http://netbox', 'nb-tok'),
            new MailProver($mail),
        );
    }

    private static function jameson(): EmailAddress
    {
        return EmailAddress::fromString('jameson@thegrieves.ca');
    }

    public function testGoogleConnectProvesThenStoresThenClaims(): void
    {
        $http = self::http(self::netbox());
        $mail = new ScriptedChannel(['* OK Gimap', 'a1 OK authenticated']);

        $scope = self::enrollment($http, $mail)->connectGoogle(self::jameson(), EmailAddress::fromString('JamesonRGrieve@gmail.com'), new GoogleGrant('at', 'rt'));

        self::assertSame('jamesonrgrieve-gmail', $scope->label->value);
        self::assertFalse($scope->disconnect);
        $write = $http->requestsTo('POST', '/v1/secret/data/' . self::SERVICES . '/external_jamesonrgrieve-gmail');
        self::assertCount(1, $write);
        self::assertSame(['data' => ['email' => 'jamesonrgrieve@gmail.com', 'refresh_token' => 'rt']], json_decode((string) $write[0]['body'], true));
        $patch = $http->requestsTo('PATCH', '/api/plugins/email/mailboxes/3/');
        self::assertSame(['send_as_addresses' => ['jamesonrgrieve@gmail.com']], json_decode((string) $patch[0]['body'], true));
        self::assertSame('bao-tok', $write[0]['headers']['X-Vault-Token']);
    }

    public function testAFailedProofStoresNothing(): void
    {
        $http = self::http(self::netbox());
        $mail = new ScriptedChannel(['* OK Gimap', 'a1 NO [AUTHENTICATIONFAILED]']);

        try {
            self::enrollment($http, $mail)->connectGoogle(self::jameson(), EmailAddress::fromString('me@gmail.com'), new GoogleGrant('at', 'rt'));
            self::fail('expected MailProtocolException');
        } catch (MailProtocolException) {
            self::assertCount(0, $http->requestsTo('POST', '/v1/secret/data/'));
            self::assertCount(0, $http->requestsTo('PATCH', '/api/plugins/email/mailboxes/'));
        }
    }

    public function testAnAddressOwnedByAnotherMailboxIsRefused(): void
    {
        $http = self::http(self::netbox([], ['smithfamilyha@shaw.ca']));

        $this->expectException(EnrollmentException::class);
        self::enrollment($http, new ScriptedChannel([]))->connectImap(
            self::jameson(),
            EmailAddress::fromString('smithfamilyha@shaw.ca'),
            new ImapCredential('imap.shaw.ca', 993, 'smtp.shaw.ca', 587, 'smithfamilyha', 'pw'),
        );
    }

    public function testReconnectingYourOwnAccountKeepsTheSendAsListUnchanged(): void
    {
        $http = self::http(self::netbox(['jamesonrgrieve@gmail.com']));
        $mail = new ScriptedChannel(['* OK', 'a1 OK']);

        self::enrollment($http, $mail)->connectGoogle(self::jameson(), EmailAddress::fromString('jamesonrgrieve@gmail.com'), new GoogleGrant('at', 'rt2'));

        $patch = $http->requestsTo('PATCH', '/api/plugins/email/mailboxes/3/');
        self::assertSame(['send_as_addresses' => ['jamesonrgrieve@gmail.com']], json_decode((string) $patch[0]['body'], true));
    }

    public function testImapConnectStoresTheProviderFields(): void
    {
        $http = self::http(self::netbox());
        $mail = new ScriptedChannel(['* OK', 'a1 OK', '220 hi', '250 ok', '220 go', '250 ok', '235 ok']);

        self::enrollment($http, $mail)->connectImap(self::jameson(), EmailAddress::fromString('jrg09@shaw.ca'), new ImapCredential('IMAP.shaw.ca', 993, 'smtp.shaw.ca', 587, 'jrg09', 'pw'));

        /** @var array{data: array<string, string>} $body */
        $body = json_decode((string) $http->requestsTo('POST', 'external_jrg09-shaw')[0]['body'], true);
        self::assertSame(
            ['email' => 'jrg09@shaw.ca', 'username' => 'jrg09', 'imap_host' => 'imap.shaw.ca', 'imap_port' => '993', 'smtp_host' => 'smtp.shaw.ca', 'smtp_port' => '587', 'password' => 'pw'],
            $body['data'],
        );
    }

    public function testDisconnectReleasesTheAddressAndDeletesTheSecret(): void
    {
        $http = self::http(self::netbox(['jamesonrgrieve@gmail.com', 'jrg09@shaw.ca']));

        $scope = self::enrollment($http, new ScriptedChannel([]))->disconnect(self::jameson(), EmailAddress::fromString('jrg09@shaw.ca'));

        self::assertTrue($scope->disconnect);
        self::assertSame(['send_as_addresses' => ['jamesonrgrieve@gmail.com']], json_decode((string) $http->requestsTo('PATCH', 'mailboxes/3/')[0]['body'], true));
        self::assertCount(1, $http->requestsTo('DELETE', '/v1/secret/metadata/' . self::SERVICES . '/external_jrg09-shaw'));
    }

    public function testDisconnectingSomeoneElsesAccountIsRefused(): void
    {
        $this->expectException(EnrollmentException::class);
        self::enrollment(self::http(self::netbox()), new ScriptedChannel([]))->disconnect(self::jameson(), EmailAddress::fromString('x@gmail.com'));
    }

    public function testConnectedListsKindsWithoutSecrets(): void
    {
        $http = self::http(self::netbox(['me@gmail.com', 'jrg09@shaw.ca', 'gone@gmail.com']), [
            'GET external_me-gmail' => ScriptedHttpClient::json(200, ['data' => ['data' => ['email' => 'me@gmail.com', 'refresh_token' => 'rt']]]),
            'GET external_jrg09-shaw' => ScriptedHttpClient::json(200, ['data' => ['data' => ['email' => 'jrg09@shaw.ca', 'password' => 'pw']]]),
            'GET external_gone-gmail' => ScriptedHttpClient::json(404, []),
        ]);

        $accounts = self::enrollment($http, new ScriptedChannel([]))->connected(self::jameson());

        self::assertSame([ConnectionKind::Google, ConnectionKind::Imap, ConnectionKind::Missing], array_map(static fn($a) => $a->kind, $accounts));
        self::assertStringNotContainsString('rt', (string) json_encode($accounts));
        self::assertStringNotContainsString('pw', (string) json_encode($accounts));
    }

    public function testYourOwnAddressCannotBeConnected(): void
    {
        $this->expectException(EnrollmentException::class);
        self::enrollment(self::http(self::netbox()), new ScriptedChannel([]))->connectGoogle(self::jameson(), self::jameson(), new GoogleGrant('a', 'r'));
    }

    public function testImapCredentialValidatesHostsAndPorts(): void
    {
        $this->expectException(EnrollmentException::class);
        new ImapCredential('not a host', 993, 'smtp.shaw.ca', 587, 'u', 'p');
    }
}
