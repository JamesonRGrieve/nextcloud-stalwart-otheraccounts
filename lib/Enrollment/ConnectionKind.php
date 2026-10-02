<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Enrollment;

enum ConnectionKind: string
{
    case Google = 'google';
    case Imap = 'imap';
    case Missing = 'missing';

    /** @param array<string, string>|null $secret */
    public static function fromSecret(?array $secret): self
    {
        return match (true) {
            $secret === null => self::Missing,
            isset($secret['refresh_token']) => self::Google,
            default => self::Imap,
        };
    }
}
