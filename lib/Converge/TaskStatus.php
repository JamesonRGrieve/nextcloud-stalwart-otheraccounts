<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Converge;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Parked = 'parked';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public static function fromApi(string $status): self
    {
        return match ($status) {
            'waiting_confirmation' => self::Parked,
            'success' => self::Succeeded,
            'error', 'stopped', 'rejected' => self::Failed,
            default => self::Pending,
        };
    }

    public function finished(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}
