<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Converge;

use OCA\OtherAccounts\Http\HttpClient;
use OCA\OtherAccounts\Http\HttpResponse;
use OCA\OtherAccounts\Http\UpstreamException;

/** The Semaphore task API for the zephyrex template: start, poll, read the plan, confirm/reject. */
final readonly class Semaphore
{
    private const string SERVICE = 'Semaphore';

    public function __construct(
        private HttpClient $http,
        private string $baseUrl,
        private string $token,
        private int $projectId,
        private int $templateId,
    ) {}

    /** @param list<string> $arguments */
    public function start(array $arguments, string $message): int
    {
        $body = json_encode([
            'template_id' => $this->templateId,
            'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
            'message' => $message,
        ], JSON_THROW_ON_ERROR);
        $response = $this->call('POST', 'tasks', $body);
        $this->expectOk($response, 'task start');
        /** @var array{id: int} $task */
        $task = $response->json();

        return $task['id'];
    }

    public function status(int $taskId): TaskStatus
    {
        $response = $this->call('GET', "tasks/{$taskId}");
        $this->expectOk($response, "status of task {$taskId}");
        /** @var array{status: string} $task */
        $task = $response->json();

        return TaskStatus::fromApi($task['status']);
    }

    public function output(int $taskId): string
    {
        $response = $this->call('GET', "tasks/{$taskId}/output");
        $this->expectOk($response, "output of task {$taskId}");
        /** @var list<array{output: string}> $lines */
        $lines = $response->json();

        return (string) preg_replace('/\e\[[0-9;]*m/', '', implode("\n", array_column($lines, 'output')));
    }

    public function confirm(int $taskId): void
    {
        $this->expectOk($this->call('POST', "tasks/{$taskId}/confirm"), "confirm of task {$taskId}");
    }

    public function reject(int $taskId): void
    {
        $this->expectOk($this->call('POST', "tasks/{$taskId}/reject"), "reject of task {$taskId}");
    }

    private function call(string $method, string $path, ?string $body = null): HttpResponse
    {
        $url = rtrim($this->baseUrl, '/') . "/api/project/{$this->projectId}/{$path}";
        $headers = ['Authorization' => "Bearer {$this->token}", 'Accept' => 'application/json', 'Content-Type' => 'application/json'];

        return $this->http->request($method, $url, $headers, $body);
    }

    private function expectOk(HttpResponse $response, string $action): void
    {
        if (!$response->ok()) {
            throw UpstreamException::from(self::SERVICE, $action, $response);
        }
    }
}
