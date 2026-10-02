<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Mail;

/** A connection or protocol failure, or a rejected login, while proving a mailbox credential. */
final class MailProtocolException extends \RuntimeException {}
