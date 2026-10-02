<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Mail;

/** Opens certificate-verifying TCP/TLS connections to mail providers. */
final readonly class SocketChannelFactory implements ChannelFactory
{
    private const int TIMEOUT_S = 20;

    public function open(string $host, int $port, bool $implicitTls): Channel
    {
        $context = stream_context_create(['ssl' => ['peer_name' => $host, 'verify_peer' => true, 'verify_peer_name' => true]]);
        $scheme = $implicitTls ? 'tls' : 'tcp';
        $stream = stream_socket_client("{$scheme}://{$host}:{$port}", $errno, $error, self::TIMEOUT_S, STREAM_CLIENT_CONNECT, $context);
        if ($stream === false) {
            throw new MailProtocolException("Could not connect to {$host}:{$port}.");
        }
        stream_set_timeout($stream, self::TIMEOUT_S);

        return new SocketChannel($stream);
    }
}
