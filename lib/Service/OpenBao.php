<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Service;

use OCA\OtherAccounts\Http\HttpClient;
use OCA\OtherAccounts\Http\HttpResponse;
use OCA\OtherAccounts\Http\UpstreamException;

/**
 * KV-v2 access to OpenBao as the app's own AppRole. The role's policy confines it to the Stalwart
 * services path (read the OAuth client + the NetBox/Semaphore tokens, write `external_*`), so a
 * compromised Nextcloud can never read the rest of the tree.
 */
final class OpenBao
{
    private const string SERVICE = 'OpenBao';
    private const int NOT_FOUND = 404;

    private ?string $token = null;

    public function __construct(
        private readonly HttpClient $http,
        private readonly string $address,
        private readonly string $mount,
        private readonly string $roleId,
        private readonly string $secretId,
    ) {}

    /** @return array<string, string>|null */
    public function read(string $path): ?array
    {
        $response = $this->call('GET', "/v1/{$this->mount}/data/{$path}");
        if ($response->status === self::NOT_FOUND) {
            return null;
        }
        $this->expectOk($response, "read {$path}");
        /** @var array{data: array{data: array<string, string>}} $body */
        $body = $response->json();

        return $body['data']['data'];
    }

    /** @param array<string, string> $data */
    public function write(string $path, array $data): void
    {
        $this->expectOk($this->call('POST', "/v1/{$this->mount}/data/{$path}", ['data' => $data]), "write {$path}");
    }

    /** @return list<string> secret names directly under $path */
    public function list(string $path): array
    {
        $response = $this->call('LIST', "/v1/{$this->mount}/metadata/{$path}");
        if ($response->status === self::NOT_FOUND) {
            return [];
        }
        $this->expectOk($response, "list {$path}");
        /** @var array{data: array{keys: list<string>}} $body */
        $body = $response->json();

        return $body['data']['keys'];
    }

    /** Delete every version and the metadata of $path. */
    public function destroy(string $path): void
    {
        $response = $this->call('DELETE', "/v1/{$this->mount}/metadata/{$path}");
        if ($response->status !== self::NOT_FOUND) {
            $this->expectOk($response, "delete {$path}");
        }
    }

    /** @param array<mixed>|null $payload */
    private function call(string $method, string $path, ?array $payload = null): HttpResponse
    {
        $headers = ['X-Vault-Token' => $this->token(), 'Content-Type' => 'application/json'];

        return $this->http->request($method, $this->address . $path, $headers, $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }
        $body = json_encode(['role_id' => $this->roleId, 'secret_id' => $this->secretId], JSON_THROW_ON_ERROR);
        $response = $this->http->request('POST', "{$this->address}/v1/auth/approle/login", ['Content-Type' => 'application/json'], $body);
        $this->expectOk($response, 'AppRole login');
        /** @var array{auth: array{client_token: string}} $auth */
        $auth = $response->json();

        return $this->token = $auth['auth']['client_token'];
    }

    private function expectOk(HttpResponse $response, string $action): void
    {
        if (!$response->ok()) {
            throw UpstreamException::from(self::SERVICE, $action, $response);
        }
    }
}
