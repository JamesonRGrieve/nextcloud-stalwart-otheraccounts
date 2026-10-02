<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Tests\Support;

use OCA\OtherAccounts\Converge\ConvergeOutcome;
use OCA\OtherAccounts\Converge\ConvergeScope;
use OCA\OtherAccounts\Converge\ConvergeStatusStore;

final class InMemoryStatusStore implements ConvergeStatusStore
{
    /** @var list<ConvergeOutcome> */
    public array $history = [];

    public function record(ConvergeScope $scope, ConvergeOutcome $outcome): void
    {
        $this->history[] = $outcome;
    }

    public function latest(ConvergeScope $scope): ?ConvergeOutcome
    {
        return $this->history === [] ? null : $this->history[array_key_last($this->history)];
    }
}
