<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Tests\Support;

use OCA\OtherAccounts\Http\HttpClient;
use OCA\OtherAccounts\Http\HttpResponse;

/**
 * An HTTP boundary double: routes are "METHOD url-substring" => response (or a queue of
 * responses, consumed in order); every request is recorded for assertions.
 */
final class ScriptedHttpClient implements HttpClient
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];

    /** @param array<string, HttpResponse|list<HttpResponse>> $routes */
    public function __construct(private array $routes) {}

    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        foreach ($this->routes as $route => &$response) {
            [$routeMethod, $fragment] = explode(' ', $route, 2);
            if ($routeMethod !== $method || !str_contains($url, $fragment)) {
                continue;
            }
            if (is_array($response)) {
                $next = count($response) > 1 ? array_shift($response) : $response[0];

                return $next;
            }

            return $response;
        }
        throw new \LogicException("Unscripted request: {$method} {$url}");
    }

    public static function json(int $status, mixed $payload): HttpResponse
    {
        return new HttpResponse($status, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public function requestsTo(string $method, string $fragment): array
    {
        return array_values(array_filter($this->requests, static fn(array $r): bool => $r['method'] === $method && str_contains($r['url'], $fragment)));
    }
}
