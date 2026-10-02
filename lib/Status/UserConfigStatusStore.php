<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Status;

use OCA\OtherAccounts\AppInfo\Application;
use OCA\OtherAccounts\Converge\ConvergeOutcome;
use OCA\OtherAccounts\Converge\ConvergeScope;
use OCA\OtherAccounts\Converge\ConvergeStatusStore;
use OCP\IAppConfig;

/** Last converge outcome per (owner mailbox, label) in app config — no secrets, just state. */
final readonly class UserConfigStatusStore implements ConvergeStatusStore
{
    public function __construct(private IAppConfig $config) {}

    public function record(ConvergeScope $scope, ConvergeOutcome $outcome): void
    {
        $this->config->setValueString(Application::APP_ID, self::key($scope), json_encode($outcome, JSON_THROW_ON_ERROR));
    }

    public function latest(ConvergeScope $scope): ?ConvergeOutcome
    {
        $raw = $this->config->getValueString(Application::APP_ID, self::key($scope));
        if ($raw === '') {
            return null;
        }
        /** @var array{state: string, task_id: int|null, detail: string} $row */
        $row = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        return ConvergeOutcome::fromArray($row);
    }

    private static function key(ConvergeScope $scope): string
    {
        return 'status:' . $scope->owner->value . ':' . $scope->label->value;
    }
}
