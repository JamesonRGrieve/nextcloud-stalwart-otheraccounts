<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Http;

use OCP\Http\Client\IClientService;

/** HttpClient over Nextcloud's own HTTP client (proxy settings, CA bundle, timeouts). */
final readonly class NextcloudHttpClient implements HttpClient
{
    private const int TIMEOUT_S = 30;

    public function __construct(private IClientService $clients) {}

    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $options = ['headers' => $headers, 'timeout' => self::TIMEOUT_S, 'http_errors' => false, 'nextcloud' => ['allow_local_address' => true]];
        if ($body !== null) {
            $options['body'] = $body;
        }
        $response = $this->clients->newClient()->request($method, $url, $options);
        $content = $response->getBody();
        $body = match (true) {
            is_string($content) => $content,
            is_resource($content) => (string) stream_get_contents($content),
            default => '',
        };

        return new HttpResponse($response->getStatusCode(), $body);
    }
}
