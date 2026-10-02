<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Service;

/** The user unticked one or more of the required Google scopes on the consent screen. */
final class IncompleteConsentException extends \RuntimeException
{
    /** @param list<string> $missingScopes */
    public function __construct(public readonly array $missingScopes)
    {
        parent::__construct('Every requested Google permission is required; please approve all of them.');
    }
}
