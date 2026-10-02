<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\AppInfo;

use OCA\OtherAccounts\Converge\Clock;
use OCA\OtherAccounts\Converge\ConvergeRunner;
use OCA\OtherAccounts\Converge\ConvergeStatusStore;
use OCA\OtherAccounts\Converge\PlanScopeGate;
use OCA\OtherAccounts\Converge\Semaphore;
use OCA\OtherAccounts\Converge\SystemClock;
use OCA\OtherAccounts\Enrollment\Enrollment;
use OCA\OtherAccounts\Http\HttpClient;
use OCA\OtherAccounts\Http\NextcloudHttpClient;
use OCA\OtherAccounts\Mail\ChannelFactory;
use OCA\OtherAccounts\Mail\MailProver;
use OCA\OtherAccounts\Mail\SocketChannelFactory;
use OCA\OtherAccounts\Service\GoogleOAuth;
use OCA\OtherAccounts\Service\NetBoxMailboxes;
use OCA\OtherAccounts\Service\OpenBao;
use OCA\OtherAccounts\Status\UserConfigStatusStore;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IConfig;
use Psr\Container\ContainerInterface;

final class Application extends App implements IBootstrap
{
    public const string APP_ID = 'otheraccounts';

    public function __construct()
    {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerService(Settings::class, static fn(ContainerInterface $c): Settings => Settings::fromConfig(self::get($c, IConfig::class)));
        $context->registerServiceAlias(HttpClient::class, NextcloudHttpClient::class);
        $context->registerServiceAlias(ChannelFactory::class, SocketChannelFactory::class);
        $context->registerServiceAlias(Clock::class, SystemClock::class);
        $context->registerServiceAlias(ConvergeStatusStore::class, UserConfigStatusStore::class);

        $context->registerService(OpenBao::class, static function (ContainerInterface $c): OpenBao {
            $s = self::get($c, Settings::class);

            return new OpenBao(self::get($c, HttpClient::class), $s->get('openbao_address'), $s->get('openbao_mount'), $s->get('approle_role_id'), $s->get('approle_secret_id'));
        });
        $context->registerService(NetBoxMailboxes::class, static function (ContainerInterface $c): NetBoxMailboxes {
            $s = self::get($c, Settings::class);

            return new NetBoxMailboxes(self::get($c, HttpClient::class), $s->get('netbox_url'), self::token($c, $s->get('netbox_token_path')));
        });
        $context->registerService(Semaphore::class, static function (ContainerInterface $c): Semaphore {
            $s = self::get($c, Settings::class);

            return new Semaphore(self::get($c, HttpClient::class), $s->get('semaphore_url'), self::token($c, $s->get('semaphore_token_path')), $s->int('semaphore_project'), $s->int('semaphore_template'));
        });
        $context->registerService(GoogleOAuth::class, static function (ContainerInterface $c): GoogleOAuth {
            $client = self::get($c, OpenBao::class)->read(self::get($c, Settings::class)->googleClientPath());
            if (!isset($client['client_id'], $client['client_secret'])) {
                throw new \RuntimeException('The Google OAuth client is not in OpenBao.');
            }

            return new GoogleOAuth(self::get($c, HttpClient::class), $client['client_id'], $client['client_secret']);
        });
        $context->registerService(Enrollment::class, static fn(ContainerInterface $c): Enrollment => new Enrollment(
            self::get($c, OpenBao::class),
            self::get($c, Settings::class)->get('services_path'),
            self::get($c, NetBoxMailboxes::class),
            new MailProver(self::get($c, ChannelFactory::class)),
        ));
        $context->registerService(ConvergeRunner::class, static fn(ContainerInterface $c): ConvergeRunner => new ConvergeRunner(
            self::get($c, Semaphore::class),
            new PlanScopeGate(),
            self::get($c, ConvergeStatusStore::class),
            self::get($c, Clock::class),
        ));
    }

    public function boot(IBootContext $context): void {}

    /**
     * A typed container lookup.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private static function get(ContainerInterface $c, string $class): object
    {
        $service = $c->get($class);
        if (!$service instanceof $class) {
            throw new \LogicException("Container returned the wrong type for {$class}.");
        }

        return $service;
    }

    /** A service token stored in OpenBao at $path under the `token` field. */
    private static function token(ContainerInterface $c, string $path): string
    {
        return self::get($c, OpenBao::class)->read($path)['token'] ?? throw new \RuntimeException("No token in OpenBao at {$path}.");
    }
}
