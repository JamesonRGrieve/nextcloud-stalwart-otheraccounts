<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\AppInfo;

use OCP\IConfig;

/**
 * The `otheraccounts` block of Nextcloud's system config (written by the pipeline). It holds only
 * the AppRole credential and non-secret locations; the NetBox and Semaphore tokens and the Google
 * OAuth client are read from OpenBao at runtime.
 */
final readonly class Settings
{
    private const string KEY = 'otheraccounts';
    private const array REQUIRED = [
        'openbao_address', 'openbao_mount', 'approle_role_id', 'approle_secret_id', 'services_path',
        'netbox_url', 'netbox_token_path', 'semaphore_url', 'semaphore_token_path', 'semaphore_project', 'semaphore_template',
    ];

    /** @param array<string, string> $values */
    private function __construct(private array $values) {}

    public static function fromConfig(IConfig $config): self
    {
        $raw = $config->getSystemValue(self::KEY, []);
        $values = [];
        foreach (self::REQUIRED as $name) {
            $value = is_array($raw) ? ($raw[$name] ?? null) : null;
            if (!is_scalar($value) || (string) $value === '') {
                throw new \RuntimeException("Other Accounts is not configured: system config '" . self::KEY . ".{$name}' is missing.");
            }
            $values[$name] = (string) $value;
        }

        return new self($values);
    }

    public function get(string $name): string
    {
        return $this->values[$name] ?? throw new \OutOfBoundsException($name);
    }

    public function int(string $name): int
    {
        return (int) $this->get($name);
    }

    public function googleClientPath(): string
    {
        return $this->get('services_path') . '/google_oauth_client';
    }
}
