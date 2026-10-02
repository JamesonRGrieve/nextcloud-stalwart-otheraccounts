<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Tests\Support;

use OCA\OtherAccounts\Converge\Clock;

/** Never sleeps; counts how long the code under test asked to wait. */
final class CountingClock implements Clock
{
    public int $slept = 0;

    public function sleep(int $seconds): void
    {
        $this->slept += $seconds;
    }
}
