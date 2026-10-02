<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Converge;

final readonly class PlanVerdict
{
    private function __construct(public bool $apply, public bool $changes, public string $reason) {}

    public static function apply(): self
    {
        return new self(true, true, 'Plan is within the enrollment scope.');
    }

    public static function noChanges(): self
    {
        return new self(false, false, 'Nothing to change.');
    }

    public static function refuse(string $reason): self
    {
        return new self(false, true, $reason);
    }
}
