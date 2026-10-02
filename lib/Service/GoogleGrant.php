<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Service;

/** The tokens a completed Google consent yields. Never logged, never sent to the browser. */
final readonly class GoogleGrant
{
    public function __construct(
        #[\SensitiveParameter]
        public string $accessToken,
        #[\SensitiveParameter]
        public string $refreshToken,
    ) {}
}
