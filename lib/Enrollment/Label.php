<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Enrollment;

/**
 * The label an external mailbox is known by in OpenBao (`external_<label>`), in the mbsync
 * store names and in the "Other Accounts/<label>" folder: `<localpart>-<provider>`, where the
 * provider is the address domain's registrable name (gmail.com → gmail, telus.net → telus).
 */
final readonly class Label
{
    private const string VALID = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private function __construct(public string $value) {}

    public static function forAddress(EmailAddress $address): self
    {
        $local = self::slug($address->localPart());
        $provider = self::slug(explode('.', $address->domain())[0]);

        return self::fromString("{$local}-{$provider}");
    }

    public static function fromString(string $value): self
    {
        if (preg_match(self::VALID, $value) !== 1) {
            throw new \InvalidArgumentException('Invalid external mailbox label.');
        }

        return new self($value);
    }

    public function secretName(): string
    {
        return "external_{$this->value}";
    }

    private static function slug(string $part): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($part)), '-');
    }
}
