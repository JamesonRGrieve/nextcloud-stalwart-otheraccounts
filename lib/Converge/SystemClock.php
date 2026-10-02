<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Converge;

final readonly class SystemClock implements Clock
{
    public function sleep(int $seconds): void
    {
        sleep($seconds);
    }
}
