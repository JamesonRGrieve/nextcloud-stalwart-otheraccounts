<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Settings;

use OCA\OtherAccounts\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

final readonly class PersonalSection implements IIconSection
{
    private const int PRIORITY = 60;

    public function __construct(private IURLGenerator $urls) {}

    public function getID(): string
    {
        return Application::APP_ID;
    }

    public function getName(): string
    {
        return 'Other Accounts';
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function getIcon(): string
    {
        return $this->urls->imagePath('core', 'actions/mail.svg');
    }
}
