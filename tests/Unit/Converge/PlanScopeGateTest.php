<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Tests\Unit\Converge;

use OCA\OtherAccounts\Converge\ConvergeScope;
use OCA\OtherAccounts\Converge\PlanScopeGate;
use OCA\OtherAccounts\Enrollment\EmailAddress;
use OCA\OtherAccounts\Enrollment\Label;
use PHPUnit\Framework\TestCase;

final class PlanScopeGateTest extends TestCase
{
    private static function scope(bool $disconnect = false): ConvergeScope
    {
        $label = Label::fromString('jamesonrgrieve-gmail');
        $owner = EmailAddress::fromString('jameson@thegrieves.ca');

        return $disconnect ? ConvergeScope::disconnect($label, $owner) : ConvergeScope::connect($label, $owner);
    }

    public function testTargetsAreTheLabelsResources(): void
    {
        self::assertContains('-target=stalwart_relay.external["jamesonrgrieve-gmail"]', self::scope()->arguments());
        self::assertContains('-target=nextcloud_mail_aliases.external["jameson@thegrieves.ca"]', self::scope()->arguments());
        self::assertSame([], self::scope()->destroyable());
    }

    public function testInScopeCreatesAndUpdatesApply(): void
    {
        $plan = <<<'PLAN'
              # host_file.stalwart_mailsync_account["jamesonrgrieve-gmail"] will be updated in-place
              # stalwart_relay.external["jamesonrgrieve-gmail"] will be updated in-place
              # host_file.stalwart_mailsync_mbsyncrc will be updated in-place
            Plan: 0 to add, 3 to change, 0 to destroy.
            PLAN;

        self::assertTrue((new PlanScopeGate())->evaluate($plan, self::scope())->apply);
    }

    public function testAnOutOfScopeResourceIsRefused(): void
    {
        $plan = <<<'PLAN'
              # stalwart_relay.external["jamesonrgrieve-gmail"] will be created
              # module.net_routers.opnsense_object.firewall_rule["x"] will be updated in-place
            Plan: 1 to add, 1 to change, 0 to destroy.
            PLAN;
        $verdict = (new PlanScopeGate())->evaluate($plan, self::scope());

        self::assertFalse($verdict->apply);
        self::assertStringContainsString('firewall_rule', $verdict->reason);
    }

    public function testAnotherLabelsRelayIsOutOfScope(): void
    {
        $plan = "  # stalwart_relay.external[\"zephyrwarriorcanada-gmail\"] will be updated in-place\nPlan: 0 to add, 1 to change, 0 to destroy.";

        self::assertFalse((new PlanScopeGate())->evaluate($plan, self::scope())->apply);
    }

    public function testDestroyIsRefusedOnConnectButAllowedForTheLabelOnDisconnect(): void
    {
        $plan = "  # stalwart_relay.external[\"jamesonrgrieve-gmail\"] will be destroyed\nPlan: 0 to add, 0 to change, 1 to destroy.";

        self::assertFalse((new PlanScopeGate())->evaluate($plan, self::scope())->apply);
        self::assertTrue((new PlanScopeGate())->evaluate($plan, self::scope(true))->apply);
    }

    public function testDisconnectStillRefusesDestroyingSharedResources(): void
    {
        $plan = "  # stalwart_mta_expression.outbound_route will be destroyed\nPlan: 0 to add, 0 to change, 1 to destroy.";

        self::assertFalse((new PlanScopeGate())->evaluate($plan, self::scope(true))->apply);
    }

    public function testReplacementIsAlwaysRefused(): void
    {
        $plan = "  # stalwart_relay.external[\"jamesonrgrieve-gmail\"] must be replaced\nPlan: 1 to add, 0 to change, 1 to destroy.";

        self::assertFalse((new PlanScopeGate())->evaluate($plan, self::scope(true))->apply);
    }

    public function testNoChangesAndMissingSummary(): void
    {
        $gate = new PlanScopeGate();

        self::assertFalse($gate->evaluate('No changes. Your infrastructure matches the configuration.', self::scope())->changes);
        self::assertFalse($gate->evaluate('  # stalwart_relay.external["jamesonrgrieve-gmail"] will be created', self::scope())->apply);
        self::assertFalse($gate->evaluate('Plan: 0 to add, 0 to change, 0 to destroy.', self::scope())->apply);
    }
}
