<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'accounts#index', 'url' => '/accounts', 'verb' => 'GET'],
        ['name' => 'accounts#connectImap', 'url' => '/accounts/imap', 'verb' => 'POST'],
        ['name' => 'accounts#disconnect', 'url' => '/accounts/disconnect', 'verb' => 'POST'],
        ['name' => 'google#start', 'url' => '/google/start', 'verb' => 'POST'],
        ['name' => 'google#callback', 'url' => '/google/callback', 'verb' => 'GET'],
    ],
];
