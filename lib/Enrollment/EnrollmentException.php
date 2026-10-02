<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Enrollment;

/** A user-correctable enrollment refusal; its message is safe to show the user. */
final class EnrollmentException extends \RuntimeException {}
