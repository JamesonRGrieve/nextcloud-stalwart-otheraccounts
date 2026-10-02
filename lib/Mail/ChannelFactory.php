<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Mail;

interface ChannelFactory
{
    /** @param bool $implicitTls TLS from the first byte (993/465); otherwise plaintext until STARTTLS. */
    public function open(string $host, int $port, bool $implicitTls): Channel;
}
