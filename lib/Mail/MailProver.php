<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Mail;

/**
 * Proves a credential against the provider before anything is stored: IMAP LOGIN or IMAP
 * AUTHENTICATE XOAUTH2 (what the mailsync uses), and SMTP AUTH PLAIN (what the outbound relay
 * uses). Never logs or echoes a secret.
 */
final readonly class MailProver
{
    private const string TAG = 'a1';
    private const int SMTP_PORT_IMPLICIT_TLS = 465;

    public function __construct(private ChannelFactory $channels) {}

    public function imapPassword(string $host, int $port, string $username, #[\SensitiveParameter] string $password): void
    {
        $this->imap($host, $port, sprintf('%s LOGIN %s %s', self::TAG, self::quote($username), self::quote($password)));
    }

    public function imapXoauth2(string $host, int $port, string $email, #[\SensitiveParameter] string $accessToken): void
    {
        $this->imap($host, $port, sprintf('%s AUTHENTICATE XOAUTH2 %s', self::TAG, self::xoauth2($email, $accessToken)));
    }

    public function smtpPassword(string $host, int $port, string $username, #[\SensitiveParameter] string $password): void
    {
        $implicit = $port === self::SMTP_PORT_IMPLICIT_TLS;
        $channel = $this->channels->open($host, $port, $implicit);
        try {
            self::expectSmtp($channel, '220');
            $channel->writeLine('EHLO otheraccounts.local');
            self::expectSmtp($channel, '250');
            if (!$implicit) {
                $channel->writeLine('STARTTLS');
                self::expectSmtp($channel, '220');
                $channel->startTls();
                $channel->writeLine('EHLO otheraccounts.local');
                self::expectSmtp($channel, '250');
            }
            $channel->writeLine('AUTH PLAIN ' . base64_encode("\0{$username}\0{$password}"));
            self::expectSmtp($channel, '235');
            $channel->writeLine('QUIT');
        } finally {
            $channel->close();
        }
    }

    private function imap(string $host, int $port, #[\SensitiveParameter] string $command): void
    {
        $channel = $this->channels->open($host, $port, true);
        try {
            if (!str_starts_with($channel->readLine(), '* OK')) {
                throw new MailProtocolException('Unexpected IMAP greeting.');
            }
            $channel->writeLine($command);
            while (true) {
                $line = $channel->readLine();
                if (str_starts_with($line, '+')) {
                    $channel->writeLine('');
                    continue;
                }
                if (str_starts_with($line, self::TAG . ' ')) {
                    if (!str_starts_with($line, self::TAG . ' OK')) {
                        throw new MailProtocolException('The mail provider rejected the login.');
                    }
                    break;
                }
            }
            $channel->writeLine('a2 LOGOUT');
        } finally {
            $channel->close();
        }
    }

    /** Reads an SMTP reply (all continuation lines) and requires the final code. */
    private static function expectSmtp(Channel $channel, string $code): void
    {
        do {
            $line = $channel->readLine();
        } while (strlen($line) > 3 && $line[3] === '-');
        if (!str_starts_with($line, $code)) {
            throw new MailProtocolException("The mail provider refused the SMTP step (expected {$code}).");
        }
    }

    private static function quote(string $value): string
    {
        return '"' . addcslashes($value, '"\\') . '"';
    }

    private static function xoauth2(string $email, #[\SensitiveParameter] string $accessToken): string
    {
        return base64_encode("user={$email}\x01auth=Bearer {$accessToken}\x01\x01");
    }
}
