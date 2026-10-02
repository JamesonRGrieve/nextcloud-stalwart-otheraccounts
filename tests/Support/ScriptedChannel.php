<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Tests\Support;

use OCA\OtherAccounts\Mail\Channel;
use OCA\OtherAccounts\Mail\ChannelFactory;
use OCA\OtherAccounts\Mail\MailProtocolException;

/** A mail-server double: replies with scripted lines and records what the client wrote. */
final class ScriptedChannel implements Channel, ChannelFactory
{
    /** @var list<string> */
    public array $written = [];
    public bool $tlsStarted = false;
    public bool $closed = false;
    /** @var list<array{host: string, port: int, implicitTls: bool}> */
    public array $opened = [];

    /** @param list<string> $replies */
    public function __construct(private array $replies) {}

    public function open(string $host, int $port, bool $implicitTls): Channel
    {
        $this->opened[] = ['host' => $host, 'port' => $port, 'implicitTls' => $implicitTls];

        return $this;
    }

    public function writeLine(string $line): void
    {
        $this->written[] = $line;
    }

    public function readLine(): string
    {
        return array_shift($this->replies) ?? throw new MailProtocolException('script exhausted');
    }

    public function startTls(): void
    {
        $this->tlsStarted = true;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
