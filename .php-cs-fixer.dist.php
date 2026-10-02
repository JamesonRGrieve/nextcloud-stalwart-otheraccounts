<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())->in([__DIR__ . '/lib', __DIR__ . '/tests', __DIR__ . '/appinfo']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'single_quote' => true,
    ])
    ->setFinder($finder);
