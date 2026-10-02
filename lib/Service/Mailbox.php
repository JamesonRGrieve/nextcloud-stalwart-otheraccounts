<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Service;

use OCA\OtherAccounts\Enrollment\EmailAddress;

/** A netbox-email mailbox as far as ownership of external addresses is concerned. */
final readonly class Mailbox
{
    /** @param list<string> $sendAs lowercased owned external addresses */
    public function __construct(public int $id, public EmailAddress $address, public array $sendAs) {}

    /** @param array{id: int, local_part: string, domain: array{name: string}, send_as_addresses: list<string>} $row */
    public static function fromRow(array $row): self
    {
        return new self(
            $row['id'],
            EmailAddress::fromString("{$row['local_part']}@{$row['domain']['name']}"),
            array_map(static fn(string $a): string => strtolower(trim($a)), $row['send_as_addresses']),
        );
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
