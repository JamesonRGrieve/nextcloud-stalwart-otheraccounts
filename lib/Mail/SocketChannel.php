<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Mail;

/** A TCP Channel over an open stream (TLS already verified, or upgraded via startTls). */
final class SocketChannel implements Channel
{
    private const int MAX_LINE = 8192;

    /** @var resource|null */
    private mixed $stream;

    /** @param resource $stream */
    public function __construct(mixed $stream)
    {
        $this->stream = $stream;
    }

    public function writeLine(#[\SensitiveParameter] string $line): void
    {
        if (fwrite($this->stream(), $line . "\r\n") === false) {
            throw new MailProtocolException('Write to the mail server failed.');
        }
    }

    public function readLine(): string
    {
        $line = fgets($this->stream(), self::MAX_LINE);
        if ($line === false) {
            throw new MailProtocolException('The mail server closed the connection or timed out.');
        }

        return rtrim($line, "\r\n");
    }

    public function startTls(): void
    {
        if (stream_socket_enable_crypto($this->stream(), true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
            throw new MailProtocolException('STARTTLS negotiation failed.');
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
    }

    /** @return resource */
    private function stream(): mixed
    {
        if (!is_resource($this->stream)) {
            throw new MailProtocolException('Channel is not open.');
        }

        return $this->stream;
    }
}
