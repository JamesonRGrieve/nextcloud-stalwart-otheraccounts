<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Enrollment;

/** One mailbox the user may use and the external accounts connected to it. */
final readonly class MailboxAccounts
{
    /** @param list<ConnectedAccount> $accounts */
    public function __construct(public EmailAddress $mailbox, public bool $own, public array $accounts) {}
}
