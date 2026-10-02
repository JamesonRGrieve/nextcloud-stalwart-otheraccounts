<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Service;

/** RFC 7636 PKCE pair (S256). */
final readonly class Pkce
{
    private const int VERIFIER_BYTES = 48;

    private function __construct(public string $verifier) {}

    public static function generate(): self
    {
        return new self(self::base64Url(random_bytes(self::VERIFIER_BYTES)));
    }

    public static function fromVerifier(string $verifier): self
    {
        return new self($verifier);
    }

    public function challenge(): string
    {
        return self::base64Url(hash('sha256', $this->verifier, true));
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
