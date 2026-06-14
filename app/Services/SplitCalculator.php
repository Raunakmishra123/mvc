<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Computes per-person INR shares for one expense.
 *
 * Pure function — no DB access, fully unit-testable.
 * Four split types are supported:
 *   equal      — total divided equally among all members
 *   unequal    — caller provides exact INR amount per person
 *   percentage — caller provides % per person (normalised if sum ≠ 100)
 *   share      — caller provides relative weight per person
 *
 * Rounding remainder always goes to the payer (or the member with the
 * lowest user_id if the payer is not a split member), so the shares
 * always sum to EXACTLY amount_inr.
 */
class SplitCalculator
{
    private static function r(float $x): float
    {
        return round($x, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * @param float      $amountInr  Total expense in INR
     * @param string     $splitType  equal|unequal|percentage|share
     * @param int[]      $memberIds  User IDs already filtered for membership
     * @param array      $details    [user_id => raw_value]
     * @param int|null   $payerId    Receives any rounding remainder
     * @return array     [['user_id'=>int,'raw_value'=>float|null,'share_amount_inr'=>float],...]
     */
    public static function compute(
        float  $amountInr,
        string $splitType,
        array  $memberIds,
        array  $details  = [],
        ?int   $payerId  = null
    ): array {
        if (empty($memberIds)) {
            return [];
        }

        $n          = count($memberIds);
        $rawValues  = [];
        $shares     = [];

        switch ($splitType) {
            case 'equal':
                foreach ($memberIds as $uid) {
                    $rawValues[$uid] = null;
                    $shares[$uid]    = self::r($amountInr / $n);
                }
                break;

            case 'percentage':
                // Normalise so percentages always sum to exactly 100
                $totalPct = (float) array_sum(
                    array_map(fn ($uid) => $details[$uid] ?? 0, $memberIds)
                );
                foreach ($memberIds as $uid) {
                    $pct          = $details[$uid] ?? 0.0;
                    $normPct      = $totalPct > 0 ? ($pct / $totalPct * 100) : 0;
                    $rawValues[$uid] = $pct;
                    $shares[$uid]    = self::r($amountInr * $normPct / 100);
                }
                break;

            case 'share':
                $totalWeight = (float) array_sum(
                    array_map(fn ($uid) => $details[$uid] ?? 1, $memberIds)
                );
                if ($totalWeight == 0) {
                    $totalWeight = $n; // fallback: treat all weights as 1
                }
                foreach ($memberIds as $uid) {
                    $w               = $details[$uid] ?? 1;
                    $rawValues[$uid] = $w;
                    $shares[$uid]    = self::r($amountInr * $w / $totalWeight);
                }
                break;

            case 'unequal':
                foreach ($memberIds as $uid) {
                    $val             = $details[$uid] ?? 0.0;
                    $rawValues[$uid] = $val;
                    $shares[$uid]    = self::r((float) $val);
                }
                break;

            default:
                throw new InvalidArgumentException("Unknown split type: {$splitType}");
        }

        // Reconcile rounding so shares sum EXACTLY to amountInr
        $diff = self::r($amountInr - (float) array_sum($shares));
        if (abs($diff) > 0) {
            $target = ($payerId !== null && array_key_exists($payerId, $shares))
                ? $payerId
                : min(array_keys($shares));
            $shares[$target] = self::r($shares[$target] + $diff);
        }

        return array_values(array_map(
            fn ($uid) => [
                'user_id'          => $uid,
                'raw_value'        => $rawValues[$uid],
                'share_amount_inr' => $shares[$uid],
            ],
            $memberIds
        ));
    }
}
