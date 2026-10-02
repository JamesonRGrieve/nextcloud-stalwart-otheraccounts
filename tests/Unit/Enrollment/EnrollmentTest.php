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
     * Jameson's and Hannah's own mailboxes, jameson@zephyrex.ca shared with Jameson, and the
     * shared wedding@ mailbox shared with both.
     *
     * @param list<string> $jamesonSendAs
     * @param list<string> $hannahSendAs
     * @param list<string> $weddingSendAs
     * @return array{results: list<array<string, mixed>>}
     */
    private static function netbox(array $jamesonSendAs = [], array $hannahSendAs = [], array $weddingSendAs = []): array
    {
        return ['results' => [
            ['id' => 3, 'local_part' => 'jameson', 'domain' => ['name' => 'thegrieves.ca'], 'send_as_addresses' => $jamesonSendAs, 'shared_with' => []],
            ['id' => 4, 'local_part' => 'hannah', 'domain' => ['name' => 'thegrieves.ca'], 'send_as_addresses' => $hannahSendAs, 'shared_with' => []],
            ['id' => 1, 'local_part' => 'jameson', 'domain' => ['name' => 'zephyrex.ca'], 'send_as_addresses' => [], 'shared_with' => ['jameson@thegrieves.ca']],
            ['id' => 16, 'local_part' => 'wedding', 'domain' => ['name' => 'thegrieves.ca'], 'send_as_addresses' => $weddingSendAs, 'shared_with' => ['Hannah@thegrieves.ca', 'jameson@thegrieves.ca']],
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

    private static function addr(string $value): EmailAddress
    {
        return EmailAddress::fromString($value);
    }

    private static function jameson(): EmailAddress
    {
        return self::addr('jameson@thegrieves.ca');
    }

    /** @return array<mixed> */
    private static function body(ScriptedHttpClient $http, string $method, string $fragment): array
    {
        $decoded = json_decode((string) $http->requestsTo($method, $fragment)[0]['body'], true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    public function testGoogleConnectProvesThenStoresThenClaims(): void
    {
        $http = self::http(self::netbox());
        $mail = new ScriptedChannel(['* OK Gimap', 'a1 OK authenticated']);

        $scope = self::enrollment($http, $mail)->connectGoogle(self::jameson(), self::jameson(), self::addr('JamesonRGrieve@gmail.com'), new GoogleGrant('at', 'rt'));

        self::assertSame('jamesonrgrieve-gmail', $scope->label->value);
        self::assertSame('jameson@thegrieves.ca', $scope->owner->value);
        self::assertFalse($scope->disconnect);
        self::assertSame(['data' => ['email' => 'jamesonrgrieve@gmail.com', 'refresh_token' => 'rt']], self::body($http, 'POST', '/v1/secret/data/' . self::SERVICES . '/external_jamesonrgrieve-gmail'));
        self::assertSame(['send_as_addresses' => ['jamesonrgrieve@gmail.com']], self::body($http, 'PATCH', '/api/plugins/email/mailboxes/3/'));
        self::assertSame('bao-tok', $http->requestsTo('POST', 'external_jamesonrgrieve-gmail')[0]['headers']['X-Vault-Token']);
    }

    public function testASharedMailboxCanBeTheTarget(): void
    {
        $http = self::http(self::netbox());
        $mail = new ScriptedChannel(['* OK', 'a1 OK']);

        $scope = self::enrollment($http, $mail)->connectGoogle(self::addr('hannah@thegrieves.ca'), self::addr('wedding@thegrieves.ca'), self::addr('ourwedding@gmail.com'), new GoogleGrant('at', 'rt'));

        self::assertSame('wedding@thegrieves.ca', $scope->owner->value);
        self::assertSame(['send_as_addresses' => ['ourwedding@gmail.com']], self::body($http, 'PATCH', '/api/plugins/email/mailboxes/16/'));
    }

    public function testAMailboxNotSharedWithYouIsRefused(): void
    {
        $http = self::http(self::netbox());

        try {
            self::enrollment($http, new ScriptedChannel([]))->connectGoogle(self::addr('hannah@thegrieves.ca'), self::jameson(), self::addr('x@gmail.com'), new GoogleGrant('a', 'r'));
            self::fail('expected EnrollmentException');
        } catch (EnrollmentException) {
            self::assertCount(0, $http->requestsTo('POST', '/v1/secret/data/'));
        }
    }

    public function testAFailedProofStoresNothing(): void
    {
        $http = self::http(self::netbox());
        $mail = new ScriptedChannel(['* OK Gimap', 'a1 NO [AUTHENTICATIONFAILED]']);

        try {
            self::enrollment($http, $mail)->connectGoogle(self::jameson(), self::jameson(), self::addr('me@gmail.com'), new GoogleGrant('at', 'rt'));
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
            self::jameson(),
            self::addr('smithfamilyha@shaw.ca'),
            new ImapCredential('imap.shaw.ca', 993, 'smtp.shaw.ca', 587, 'smithfamilyha', 'pw'),
        );
    }

    public function testReconnectingKeepsTheSendAsListUnchanged(): void
    {
        $http = self::http(self::netbox(['jamesonrgrieve@gmail.com']));
        $mail = new ScriptedChannel(['* OK', 'a1 OK']);

        self::enrollment($http, $mail)->connectGoogle(self::jameson(), self::jameson(), self::addr('jamesonrgrieve@gmail.com'), new GoogleGrant('at', 'rt2'));

        self::assertSame(['send_as_addresses' => ['jamesonrgrieve@gmail.com']], self::body($http, 'PATCH', '/api/plugins/email/mailboxes/3/'));
    }

    public function testImapConnectStoresTheProviderFields(): void
    {
        $http = self::http(self::netbox());
        $mail = new ScriptedChannel(['* OK', 'a1 OK', '220 hi', '250 ok', '220 go', '250 ok', '235 ok']);

        self::enrollment($http, $mail)->connectImap(self::jameson(), self::jameson(), self::addr('jrg09@shaw.ca'), new ImapCredential('IMAP.shaw.ca', 993, 'smtp.shaw.ca', 587, 'jrg09', 'pw'));

        self::assertSame(
            ['data' => ['email' => 'jrg09@shaw.ca', 'username' => 'jrg09', 'imap_host' => 'imap.shaw.ca', 'imap_port' => '993', 'smtp_host' => 'smtp.shaw.ca', 'smtp_port' => '587', 'password' => 'pw']],
            self::body($http, 'POST', 'external_jrg09-shaw'),
        );
    }

    public function testDisconnectReleasesTheAddressAndDeletesTheSecret(): void
    {
        $http = self::http(self::netbox(['jamesonrgrieve@gmail.com', 'jrg09@shaw.ca']));

        $scope = self::enrollment($http, new ScriptedChannel([]))->disconnect(self::jameson(), self::jameson(), self::addr('jrg09@shaw.ca'));

        self::assertTrue($scope->disconnect);
        self::assertSame(['send_as_addresses' => ['jamesonrgrieve@gmail.com']], self::body($http, 'PATCH', 'mailboxes/3/'));
        self::assertCount(1, $http->requestsTo('DELETE', '/v1/secret/metadata/' . self::SERVICES . '/external_jrg09-shaw'));
    }

    public function testDisconnectingAnAccountTheMailboxDoesNotOwnIsRefused(): void
    {
        $this->expectException(EnrollmentException::class);
        self::enrollment(self::http(self::netbox()), new ScriptedChannel([]))->disconnect(self::jameson(), self::jameson(), self::addr('x@gmail.com'));
    }

    public function testConnectedListsUsableMailboxesOwnFirstWithoutSecrets(): void
    {
        $http = self::http(self::netbox(['me@gmail.com', 'jrg09@shaw.ca', 'gone@gmail.com'], [], ['ourwedding@gmail.com']), [
            'GET external_me-gmail' => ScriptedHttpClient::json(200, ['data' => ['data' => ['email' => 'me@gmail.com', 'refresh_token' => 'rt']]]),
            'GET external_jrg09-shaw' => ScriptedHttpClient::json(200, ['data' => ['data' => ['email' => 'jrg09@shaw.ca', 'password' => 'pw']]]),
            'GET external_gone-gmail' => ScriptedHttpClient::json(404, []),
            'GET external_ourwedding-gmail' => ScriptedHttpClient::json(200, ['data' => ['data' => ['email' => 'ourwedding@gmail.com', 'refresh_token' => 'rt']]]),
        ]);

        $mailboxes = self::enrollment($http, new ScriptedChannel([]))->connected(self::jameson());

        self::assertSame(['jameson@thegrieves.ca', 'jameson@zephyrex.ca', 'wedding@thegrieves.ca'], array_map(static fn($m) => $m->mailbox->value, $mailboxes));
        self::assertSame([true, false, false], array_map(static fn($m) => $m->own, $mailboxes));
        self::assertSame([ConnectionKind::Google, ConnectionKind::Imap, ConnectionKind::Missing], array_map(static fn($a) => $a->kind, $mailboxes[0]->accounts));
        self::assertSame('ourwedding-gmail', $mailboxes[2]->accounts[0]->label->value);
        $json = (string) json_encode(array_map(static fn($m) => $m->accounts, $mailboxes));
        self::assertStringNotContainsString('"rt"', $json);
        self::assertStringNotContainsString('pw', $json);
    }

    public function testTheMailboxAddressItselfCannotBeConnected(): void
    {
        $this->expectException(EnrollmentException::class);
        self::enrollment(self::http(self::netbox()), new ScriptedChannel([]))->connectGoogle(self::jameson(), self::jameson(), self::jameson(), new GoogleGrant('a', 'r'));
    }

    public function testImapCredentialValidatesHostsAndPorts(): void
    {
        $this->expectException(EnrollmentException::class);
        new ImapCredential('not a host', 993, 'smtp.shaw.ca', 587, 'u', 'p');
    }
}
