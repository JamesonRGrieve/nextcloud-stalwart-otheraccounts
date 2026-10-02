<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Http;

final readonly class HttpResponse
{
    private const int FIRST_ERROR_STATUS = 400;

    public function __construct(public int $status, public string $body) {}

    public function ok(): bool
    {
        return $this->status < self::FIRST_ERROR_STATUS;
    }

    /** @return array<mixed> */
    public function json(): array
    {
        $decoded = json_decode($this->body, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('Expected a JSON object or array.');
        }

        return $decoded;
    }
}
