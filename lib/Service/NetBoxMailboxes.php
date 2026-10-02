<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Service;

use OCA\OtherAccounts\Enrollment\EmailAddress;
use OCA\OtherAccounts\Http\HttpClient;
use OCA\OtherAccounts\Http\HttpResponse;
use OCA\OtherAccounts\Http\UpstreamException;

/**
 * The netbox-email Mailbox records that define ownership: an external address belongs to the
 * mailbox whose `send_as_addresses` lists it (that is what the Stalwart converge reads).
 */
final readonly class NetBoxMailboxes
{
    private const string SERVICE = 'NetBox';
    private const string MAILBOXES = '/api/plugins/email/mailboxes/';
    private const int PAGE_LIMIT = 1000;

    public function __construct(private HttpClient $http, private string $baseUrl, private string $token) {}

    public function find(EmailAddress $address): ?Mailbox
    {
        foreach ($this->all() as $mailbox) {
            if ($mailbox->address->equals($address)) {
                return $mailbox;
            }
        }

        return null;
    }

    /** Which mailbox already owns $external as a send-as address, if any. */
    public function ownerOf(EmailAddress $external): ?Mailbox
    {
        foreach ($this->all() as $mailbox) {
            if ($mailbox->owns($external)) {
                return $mailbox;
            }
        }

        return null;
    }

    /** @return list<Mailbox> */
    private function all(): array
    {
        $response = $this->call('GET', self::MAILBOXES . '?limit=' . self::PAGE_LIMIT);
        $this->expectOk($response, 'mailbox listing');
        /** @var array{results: list<array{id: int, local_part: string, domain: array{name: string}, send_as_addresses: list<string>, shared_with?: list<string>}>} $body */
        $body = $response->json();

        return array_map(Mailbox::fromRow(...), $body['results']);
    }

    /** @return list<Mailbox> the user's own mailbox first, then every mailbox shared with them */
    public function usableBy(EmailAddress $user): array
    {
        $usable = array_values(array_filter($this->all(), static fn(Mailbox $m): bool => $m->usableBy($user)));
        usort($usable, static fn(Mailbox $a, Mailbox $b): int => [!$a->address->equals($user), $a->address->value] <=> [!$b->address->equals($user), $b->address->value]);

        return $usable;
    }

    /** @param list<string> $sendAs */
    public function setSendAs(Mailbox $mailbox, array $sendAs): void
    {
        $body = json_encode(['send_as_addresses' => $sendAs], JSON_THROW_ON_ERROR);
        $this->expectOk($this->call('PATCH', self::MAILBOXES . "{$mailbox->id}/", $body), "send-as update of mailbox {$mailbox->id}");
    }

    private function call(string $method, string $path, ?string $body = null): HttpResponse
    {
        $headers = ['Authorization' => "Token {$this->token}", 'Accept' => 'application/json', 'Content-Type' => 'application/json'];

        return $this->http->request($method, rtrim($this->baseUrl, '/') . $path, $headers, $body);
    }

    private function expectOk(HttpResponse $response, string $action): void
    {
        if (!$response->ok()) {
            throw UpstreamException::from(self::SERVICE, $action, $response);
        }
    }
}
