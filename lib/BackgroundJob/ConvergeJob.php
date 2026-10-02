<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\BackgroundJob;

use OCA\OtherAccounts\Converge\ConvergeRunner;
use OCA\OtherAccounts\Converge\ConvergeScope;
use OCA\OtherAccounts\Enrollment\EmailAddress;
use OCA\OtherAccounts\Enrollment\Label;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

/** Runs one enrollment's scoped converge off the request path (it takes minutes). */
final class ConvergeJob extends QueuedJob
{
    public function __construct(ITimeFactory $time, private readonly ConvergeRunner $runner, private readonly LoggerInterface $logger)
    {
        parent::__construct($time);
    }

    /** @return array{label: string, owner: string, disconnect: bool} */
    public static function arguments(ConvergeScope $scope): array
    {
        return ['label' => $scope->label->value, 'owner' => $scope->owner->value, 'disconnect' => $scope->disconnect];
    }

    /** @param mixed $argument */
    protected function run($argument): void
    {
        if (!is_array($argument) || !is_string($argument['label'] ?? null) || !is_string($argument['owner'] ?? null)) {
            $this->logger->error('Other Accounts converge job has malformed arguments.');

            return;
        }
        $label = Label::fromString($argument['label']);
        $owner = EmailAddress::fromString($argument['owner']);
        $scope = ($argument['disconnect'] ?? false) === true ? ConvergeScope::disconnect($label, $owner) : ConvergeScope::connect($label, $owner);
        $outcome = $this->runner->run($scope);
        $this->logger->info('Other Accounts converge for {label} ({owner}): {state} (task {task})', [
            'label' => $label->value, 'owner' => $owner->value, 'state' => $outcome->state, 'task' => $outcome->taskId,
        ]);
    }
}
