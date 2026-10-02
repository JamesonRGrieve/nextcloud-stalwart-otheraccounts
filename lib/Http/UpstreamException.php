<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Http;

/** A backing service (OpenBao, NetBox, Semaphore, Google) refused or failed a request. */
final class UpstreamException extends \RuntimeException
{
    public static function from(string $service, string $action, HttpResponse $response): self
    {
        return new self("{$service} {$action} failed (HTTP {$response->status}).");
    }
}
