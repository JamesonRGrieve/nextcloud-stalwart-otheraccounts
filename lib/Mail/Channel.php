<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Mail;

/** A line-oriented mail-protocol connection (IMAP/SMTP), the seam the prover talks through. */
interface Channel
{
    public function writeLine(#[\SensitiveParameter] string $line): void;

    /** One server line without its CRLF; throws on EOF/timeout. */
    public function readLine(): string;

    /** Upgrade a plaintext connection to TLS (SMTP STARTTLS). */
    public function startTls(): void;

    public function close(): void;
}
