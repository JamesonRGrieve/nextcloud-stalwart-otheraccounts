<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Converge;

/** Where a label's last converge stands, as shown to its owner. */
final readonly class ConvergeOutcome implements \JsonSerializable
{
    private function __construct(public string $state, public ?int $taskId, public string $detail) {}

    public static function queued(): self
    {
        return new self('queued', null, 'Waiting for the next background run.');
    }

    public static function running(int $taskId): self
    {
        return new self('running', $taskId, 'Applying your change.');
    }

    public static function applied(int $taskId): self
    {
        return new self('applied', $taskId, 'Connected and syncing.');
    }

    public static function refused(int $taskId, string $reason): self
    {
        return new self('refused', $taskId, "Held for an administrator: {$reason}");
    }

    public static function failed(int $taskId): self
    {
        return new self('failed', $taskId, "The change failed to apply; ask an administrator to check pipeline task {$taskId}.");
    }

    public static function timedOut(int $taskId): self
    {
        return new self('timed_out', $taskId, 'Still waiting on the pipeline; check back shortly.');
    }

    /** @param array{state: string, task_id: int|null, detail: string} $row */
    public static function fromArray(array $row): self
    {
        return new self($row['state'], $row['task_id'], $row['detail']);
    }

    /** @return array{state: string, task_id: int|null, detail: string} */
    public function jsonSerialize(): array
    {
        return ['state' => $this->state, 'task_id' => $this->taskId, 'detail' => $this->detail];
    }
}
