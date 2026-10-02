<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Service;

use OCA\OtherAccounts\Enrollment\EmailAddress;

/**
 * A netbox-email mailbox as far as external accounts are concerned: which external addresses it
 * owns (`send_as_addresses`) and which other mailboxes' owners may also use it (`shared_with`).
 */
final readonly class Mailbox
{
    /**
     * @param list<string> $sendAs lowercased owned external addresses
     * @param list<string> $sharedWith lowercased addresses of the mailboxes whose owners may use this one
     */
    public function __construct(public int $id, public EmailAddress $address, public array $sendAs, public array $sharedWith = []) {}

    /** @param array{id: int, local_part: string, domain: array{name: string}, send_as_addresses: list<string>, shared_with?: list<string>} $row */
    public static function fromRow(array $row): self
    {
        $normalize = static fn(string $a): string => strtolower(trim($a));

        return new self(
            $row['id'],
            EmailAddress::fromString("{$row['local_part']}@{$row['domain']['name']}"),
            array_map($normalize, $row['send_as_addresses']),
            array_map($normalize, $row['shared_with'] ?? []),
        );
    }

    /** True when $user (a mailbox owner's address) may manage this mailbox's external accounts. */
    public function usableBy(EmailAddress $user): bool
    {
        return $this->address->equals($user) || in_array($user->value, $this->sharedWith, true);
    }

    public function owns(EmailAddress $external): bool
    {
        return in_array($external->value, $this->sendAs, true);
    }

    /** @return list<string> */
    public function withSendAs(EmailAddress $external): array
    {
        return $this->owns($external) ? $this->sendAs : [...$this->sendAs, $external->value];
    }

    /** @return list<string> */
    public function withoutSendAs(EmailAddress $external): array
    {
        return array_values(array_filter($this->sendAs, static fn(string $a): bool => $a !== $external->value));
    }
}
