<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Enrollment;

/** A password-provider login (Shaw, Telus classic, any IMAP + SMTP submission host). */
final readonly class ImapCredential
{
    private const string HOST = '/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';
    private const int MIN_PORT = 1;
    private const int MAX_PORT = 65535;

    public function __construct(
        public string $imapHost,
        public int $imapPort,
        public string $smtpHost,
        public int $smtpPort,
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {
        foreach ([$imapHost, $smtpHost] as $host) {
            if (preg_match(self::HOST, strtolower($host)) !== 1) {
                throw new EnrollmentException('Enter a valid mail server hostname.');
            }
        }
        foreach ([$imapPort, $smtpPort] as $port) {
            if ($port < self::MIN_PORT || $port > self::MAX_PORT) {
                throw new EnrollmentException('Enter a valid port.');
            }
        }
        if ($username === '' || $password === '') {
            throw new EnrollmentException('Username and password are required.');
        }
    }

    /** @return array<string, string> the OpenBao fields svc_stalwart.tf reads for a password provider */
    public function toSecret(): array
    {
        return [
            'username' => $this->username,
            'imap_host' => strtolower($this->imapHost),
            'imap_port' => (string) $this->imapPort,
            'smtp_host' => strtolower($this->smtpHost),
            'smtp_port' => (string) $this->smtpPort,
            'password' => $this->password,
        ];
    }
}
