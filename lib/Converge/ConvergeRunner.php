<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Converge;

/**
 * Runs one scoped tmpl1 converge to completion: start it, wait until it parks (or ends with no
 * changes), apply the plan-scope gate, then confirm and wait — or reject. A refused plan is
 * rejected rather than left parked, because a parked plan holds the shared runner for everyone;
 * the reason is recorded for the user and the operator.
 */
final readonly class ConvergeRunner
{
    private const int POLL_INTERVAL_S = 15;
    private const int MAX_POLLS = 120;

    public function __construct(
        private Semaphore $semaphore,
        private PlanScopeGate $gate,
        private ConvergeStatusStore $statuses,
        private Clock $clock,
    ) {}

    public function run(ConvergeScope $scope): ConvergeOutcome
    {
        $verb = $scope->disconnect ? 'Disconnect' : 'Connect';
        $taskId = $this->semaphore->start($scope->arguments(), "Other Accounts app: {$verb} {$scope->label->value} for {$scope->owner->value}");
        $this->statuses->record($scope, ConvergeOutcome::running($taskId));

        $status = $this->waitWhile($taskId, static fn(TaskStatus $s): bool => $s === TaskStatus::Pending);
        if ($status === TaskStatus::Parked) {
            $verdict = $this->gate->evaluate($this->semaphore->output($taskId), $scope);
            if (!$verdict->apply) {
                $this->semaphore->reject($taskId);

                return $this->finish($scope, ConvergeOutcome::refused($taskId, $verdict->reason));
            }
            $this->semaphore->confirm($taskId);
            $status = $this->waitWhile($taskId, static fn(TaskStatus $s): bool => !$s->finished());
        }

        return $this->finish($scope, match ($status) {
            TaskStatus::Succeeded => ConvergeOutcome::applied($taskId),
            TaskStatus::Failed => ConvergeOutcome::failed($taskId),
            default => ConvergeOutcome::timedOut($taskId),
        });
    }

    /** @param \Closure(TaskStatus): bool $keepWaiting */
    private function waitWhile(int $taskId, \Closure $keepWaiting): TaskStatus
    {
        for ($poll = 0; $poll < self::MAX_POLLS; $poll++) {
            $status = $this->semaphore->status($taskId);
            if (!$keepWaiting($status)) {
                return $status;
            }
            $this->clock->sleep(self::POLL_INTERVAL_S);
        }

        return TaskStatus::Pending;
    }

    private function finish(ConvergeScope $scope, ConvergeOutcome $outcome): ConvergeOutcome
    {
        $this->statuses->record($scope, $outcome);

        return $outcome;
    }
}
