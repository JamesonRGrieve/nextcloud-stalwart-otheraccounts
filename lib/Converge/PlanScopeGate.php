<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Converge;

/**
 * Decides whether a parked plan may be applied without the operator: every resource it touches
 * must be one of the scope's targets, it may destroy only what the scope allows, and nothing may
 * be replaced. Anything else leaves the plan parked for a human.
 */
final class PlanScopeGate
{
    private const string ACTION_LINE = '/^\s*#\s+(\S+)\s+(?:will be|must be)\s+(created|updated in-place|destroyed|replaced)/m';
    private const string SUMMARY_LINE = '/Plan:\s+(\d+) to add,\s+(\d+) to change,\s+(\d+) to destroy/';

    public function evaluate(string $planOutput, ConvergeScope $scope): PlanVerdict
    {
        if (str_contains($planOutput, 'No changes.')) {
            return PlanVerdict::noChanges();
        }
        if (preg_match(self::SUMMARY_LINE, $planOutput) !== 1) {
            return PlanVerdict::refuse('The plan output has no summary line.');
        }
        preg_match_all(self::ACTION_LINE, $planOutput, $matches, PREG_SET_ORDER);
        if ($matches === []) {
            return PlanVerdict::refuse('The plan lists no resource actions.');
        }
        foreach ($matches as [, $address, $action]) {
            if (!in_array($address, $scope->targets(), true)) {
                return PlanVerdict::refuse("Out-of-scope resource in plan: {$address}");
            }
            if ($action === 'replaced') {
                return PlanVerdict::refuse("Plan replaces {$address}");
            }
            if ($action === 'destroyed' && !in_array($address, $scope->destroyable(), true)) {
                return PlanVerdict::refuse("Plan destroys {$address}");
            }
        }

        return PlanVerdict::apply();
    }
}
