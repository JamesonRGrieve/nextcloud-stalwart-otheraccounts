<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Converge;

interface ConvergeStatusStore
{
    public function record(ConvergeScope $scope, ConvergeOutcome $outcome): void;

    public function latest(ConvergeScope $scope): ?ConvergeOutcome;
}
