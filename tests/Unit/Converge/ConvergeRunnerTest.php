<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Tests\Unit\Converge;

use OCA\OtherAccounts\Converge\ConvergeRunner;
use OCA\OtherAccounts\Converge\ConvergeScope;
use OCA\OtherAccounts\Converge\PlanScopeGate;
use OCA\OtherAccounts\Converge\Semaphore;
use OCA\OtherAccounts\Enrollment\EmailAddress;
use OCA\OtherAccounts\Enrollment\Label;
use OCA\OtherAccounts\Tests\Support\CountingClock;
use OCA\OtherAccounts\Tests\Support\InMemoryStatusStore;
use OCA\OtherAccounts\Tests\Support\ScriptedHttpClient;
use PHPUnit\Framework\TestCase;

final class ConvergeRunnerTest extends TestCase
{
    private const int TASK = 4242;

    private InMemoryStatusStore $statuses;
    private CountingClock $clock;

    protected function setUp(): void
    {
        $this->statuses = new InMemoryStatusStore();
        $this->clock = new CountingClock();
    }

    private function runner(ScriptedHttpClient $http): ConvergeRunner
    {
        return new ConvergeRunner(new Semaphore($http, 'http://sem', 'tok', 1, 1), new PlanScopeGate(), $this->statuses, $this->clock);
    }

    private static function scope(): ConvergeScope
    {
        return ConvergeScope::connect(Label::fromString('jrg09-shaw'), EmailAddress::fromString('jameson@thegrieves.ca'));
    }

    /** @param list<string> $statuses */
    private static function http(array $statuses, string $plan): ScriptedHttpClient
    {
        return new ScriptedHttpClient([
            'POST /tasks/' . self::TASK . '/confirm' => ScriptedHttpClient::json(204, []),
            'POST /tasks/' . self::TASK . '/reject' => ScriptedHttpClient::json(204, []),
            'POST /api/project/1/tasks' => ScriptedHttpClient::json(201, ['id' => self::TASK]),
            'GET /tasks/' . self::TASK . '/output' => ScriptedHttpClient::json(200, array_map(static fn(string $l): array => ['output' => $l], explode("\n", $plan))),
            'GET /tasks/' . self::TASK => array_map(static fn(string $s) => ScriptedHttpClient::json(200, ['status' => $s]), $statuses),
        ]);
    }

    public function testInScopePlanIsConfirmedAndApplied(): void
    {
        $plan = "  # stalwart_relay.external[\"jrg09-shaw\"] will be updated in-place\nPlan: 0 to add, 1 to change, 0 to destroy.";
        $http = self::http(['waiting', 'running', 'waiting_confirmation', 'running', 'success'], $plan);

        $outcome = $this->runner($http)->run(self::scope());

        self::assertSame('applied', $outcome->state);
        self::assertCount(1, $http->requestsTo('POST', '/confirm'));
        self::assertCount(0, $http->requestsTo('POST', '/reject'));
        /** @var array{arguments: string} $started */
        $started = json_decode((string) $http->requestsTo('POST', '/api/project/1/tasks')[0]['body'], true);
        self::assertSame(self::scope()->arguments(), json_decode($started['arguments'], true));
        self::assertSame(['running', 'applied'], array_map(static fn($o) => $o->state, $this->statuses->history));
        self::assertGreaterThan(0, $this->clock->slept);
    }

    public function testOutOfScopePlanIsRejectedNotLeftParked(): void
    {
        $plan = "  # module.net_routers.x[\"y\"] will be destroyed\nPlan: 0 to add, 0 to change, 1 to destroy.";
        $http = self::http(['waiting_confirmation'], $plan);

        $outcome = $this->runner($http)->run(self::scope());

        self::assertSame('refused', $outcome->state);
        self::assertCount(1, $http->requestsTo('POST', '/reject'));
        self::assertCount(0, $http->requestsTo('POST', '/confirm'));
        self::assertStringContainsString('module.net_routers', $outcome->detail);
    }

    public function testATaskThatSucceedsWithoutParkingNeedsNoConfirm(): void
    {
        $http = self::http(['running', 'success'], 'No changes.');

        self::assertSame('applied', $this->runner($http)->run(self::scope())->state);
        self::assertCount(0, $http->requestsTo('POST', '/confirm'));
    }

    public function testAFailedApplyIsReported(): void
    {
        $plan = "  # stalwart_relay.external[\"jrg09-shaw\"] will be created\nPlan: 1 to add, 0 to change, 0 to destroy.";
        $outcome = $this->runner(self::http(['waiting_confirmation', 'error'], $plan))->run(self::scope());

        self::assertSame('failed', $outcome->state);
        self::assertSame(self::TASK, $outcome->taskId);
    }

    public function testAStuckTaskTimesOut(): void
    {
        $outcome = $this->runner(self::http(['waiting'], ''))->run(self::scope());

        self::assertSame('timed_out', $outcome->state);
    }
}
