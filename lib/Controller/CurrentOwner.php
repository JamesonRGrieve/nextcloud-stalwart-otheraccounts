<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Controller;

use OCA\OtherAccounts\Enrollment\EmailAddress;
use OCA\OtherAccounts\Enrollment\EnrollmentException;
use OCP\IUserSession;

/** The signed-in user's own mailbox address (their Nextcloud email, from SSO). */
final readonly class CurrentOwner
{
    public function __construct(private IUserSession $session) {}

    public function address(): EmailAddress
    {
        $email = $this->session->getUser()?->getEMailAddress();
        if ($email === null || $email === '') {
            throw new EnrollmentException('Your Nextcloud account has no email address.');
        }

        return EmailAddress::fromString($email);
    }
}
