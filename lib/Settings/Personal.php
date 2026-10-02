<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Settings;

use OCA\OtherAccounts\AppInfo\Application;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

final readonly class Personal implements ISettings
{
    private const int PRIORITY = 10;

    /** @return TemplateResponse<Http::STATUS_OK, array{}> */
    public function getForm(): TemplateResponse
    {
        Util::addScript(Application::APP_ID, 'personal');
        Util::addStyle(Application::APP_ID, 'personal');

        return new TemplateResponse(Application::APP_ID, 'personal');
    }

    public function getSection(): string
    {
        return Application::APP_ID;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }
}
