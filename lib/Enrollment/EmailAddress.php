<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Enrollment;

/** A validated, lowercased email address. */
final readonly class EmailAddress
{
    private function __construct(public string $value) {}

    public static function fromString(string $raw): self
    {
        $value = strtolower(trim($raw));
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        return new self($value);
    }

    public function localPart(): string
    {
        return substr($this->value, 0, (int) strrpos($this->value, '@'));
    }

    public function domain(): string
    {
        return substr($this->value, (int) strrpos($this->value, '@') + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
