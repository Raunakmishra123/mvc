<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Computes group balances and settle-up transactions.
 *
 * Pure functions — no HTTP, no Eloquent writes, fully testable.
 *
 * Balance convention:
 *   positive  = the GROUP owes this person money (they overpaid / fronted)
 *   negative  = this person owes the group money
 *   sum = 0   (conservation of money)
 */
class BalanceCalculator
{
    /**
     * Net balance per user for one group.
     * Only expenses NOT excluded_from_balances and with a paid_by are counted.
     */
    public static function groupBalances(int $groupId): array
    {
        $balances = [];

        $add = static function (int $uid, float $amount) use (&$balances): void {
            $balances[$uid] = round(($balances[$uid] ?? 0.0) + $amount, 2);
        };

        // Expenses ─────────────────────────────────────────────────────────────
        // Payer fronted the money → credit them the full amount.
        // Each split member owes their share → debit them.
        $expenses = DB::table('expenses')
            ->where('group_id', $groupId)
            ->where('excluded_from_balances', false)
            ->whereNotNull('paid_by')
            ->select('id', 'paid_by', 'amount_inr')
            ->get();

        foreach ($expenses as $exp) {
            $add($exp->paid_by, $exp->amount_inr);

            $splits = DB::table('expense_splits')
                ->where('expense_id', $exp->id)
                ->select('user_id', 'share_amount_inr')
                ->get();

            foreach ($splits as $s) {
                $add($s->user_id, -$s->share_amount_inr);
            }
        }

        // Settlements ──────────────────────────────────────────────────────────
        // Debtor (paid_by) pays creditor (paid_to):
        //   debtor's balance improves (they owe less)
        //   creditor's balance decreases (they are owed less)
        $settlements = DB::table('settlements')
            ->where('group_id', $groupId)
            ->select('paid_by', 'paid_to', 'amount_inr')
            ->get();

        foreach ($settlements as $s) {
            $add($s->paid_by, $s->amount_inr);
            $add($s->paid_to, -$s->amount_inr);
        }

        return $balances;
    }

    /**
     * Greedy minimal-transactions settle-up plan.
     *
     * Repeatedly match the biggest debtor with the biggest creditor.
     * Produces at most (n-1) transactions, which is optimal for a
     * complete graph — simple, deterministic, easy to explain line by line.
     *
     * @param  array $balances  [user_id => net_balance]
     * @return array            [['from'=>int,'to'=>int,'amount'=>float],...]
     */
    public static function settleUp(array $balances): array
    {
        $eps        = 0.005;
        $creditors  = [];
        $debtors    = [];

        foreach ($balances as $uid => $bal) {
            if ($bal >  $eps) {
                $creditors[] = [(int) $uid, (float) $bal];
            }
            if ($bal < -$eps) {
                $debtors[]   = [(int) $uid, (float) (-$bal)];
            }
        }

        // Sort largest first
        usort($creditors, fn ($a, $b) => $b[1] <=> $a[1]);
        usort($debtors,   fn ($a, $b) => $b[1] <=> $a[1]);

        $txns = [];
        $i    = 0;
        $j    = 0;

        while ($i < count($debtors) && $j < count($creditors)) {
            $amount = round(min($debtors[$i][1], $creditors[$j][1]), 2);

            if ($amount > $eps) {
                $txns[] = [
                    'from'   => $debtors[$i][0],
                    'to'     => $creditors[$j][0],
                    'amount' => $amount,
                ];
            }

            $debtors[$i][1]   = round($debtors[$i][1]   - $amount, 2);
            $creditors[$j][1] = round($creditors[$j][1] - $amount, 2);

            if ($debtors[$i][1]   <= $eps) {
                $i++;
            }
            if ($creditors[$j][1] <= $eps) {
                $j++;
            }
        }

        return $txns;
    }

    /**
     * Full line-item breakdown of every expense/settlement affecting one user.
     * Used for Rohan's "no magic numbers" individual balance view.
     *
     * @return array Each element has 'type'='expense'|'settlement' plus detail fields.
     */
    public static function userBreakdown(int $groupId, int $userId): array
    {
        $lines = [];

        // Expense lines where user is a split member
        $rows = DB::table('expenses as e')
            ->join('expense_splits as es', 'es.expense_id', '=', 'e.id')
            ->leftJoin('users as u', 'u.id', '=', 'e.paid_by')
            ->where('e.group_id', $groupId)
            ->where('es.user_id', $userId)
            ->where('e.excluded_from_balances', false)
            ->select(
                'e.id as expense_id', 'e.description', 'e.expense_date',
                'e.paid_by', 'e.amount_inr', 'e.original_amount',
                'e.original_currency', 'e.exchange_rate', 'e.split_type',
                'es.share_amount_inr', 'es.raw_value',
                'u.name as paid_by_name'
            )
            ->orderBy('e.expense_date')
            ->orderBy('e.id')
            ->get();

        foreach ($rows as $r) {
            $isPayer = ((int) $r->paid_by === $userId);
            // If the user is the payer, the net effect on their balance is
            // (total they fronted) minus (their own share). They are "owed"
            // by everyone else, which nets to +amount_inr - share.
            $effect = $isPayer
                ? round($r->amount_inr - $r->share_amount_inr, 2)
                : round(-$r->share_amount_inr, 2);

            $lines[] = [
                'type'              => 'expense',
                'expense_id'        => $r->expense_id,
                'description'       => $r->description,
                'date'              => $r->expense_date,
                'paid_by_name'      => $r->paid_by_name,
                'is_payer'          => $isPayer,
                'amount_inr'        => $r->amount_inr,
                'original_amount'   => $r->original_amount,
                'original_currency' => $r->original_currency,
                'exchange_rate'     => $r->exchange_rate,
                'split_type'        => $r->split_type,
                'your_share_inr'    => $r->share_amount_inr,
                'effect_on_balance' => $effect,
            ];
        }

        // Settlement lines
        $settlements = DB::table('settlements as s')
            ->leftJoin('users as up', 'up.id', '=', 's.paid_by')
            ->leftJoin('users as ut', 'ut.id', '=', 's.paid_to')
            ->where('s.group_id', $groupId)
            ->where(function ($q) use ($userId) {
                $q->where('s.paid_by', $userId)
                  ->orWhere('s.paid_to', $userId);
            })
            ->select(
                's.id as settlement_id', 's.amount_inr', 's.settlement_date',
                's.notes', 's.paid_by', 's.paid_to',
                'up.name as paid_by_name', 'ut.name as paid_to_name'
            )
            ->orderBy('s.settlement_date')
            ->orderBy('s.id')
            ->get();

        foreach ($settlements as $s) {
            // Payer: paying off their debt → balance improves (positive effect)
            // Payee: receiving money → their credit decreases (negative effect)
            $effect = ((int) $s->paid_by === $userId)
                ? $s->amount_inr
                : -$s->amount_inr;

            $lines[] = [
                'type'             => 'settlement',
                'settlement_id'    => $s->settlement_id,
                'date'             => $s->settlement_date,
                'paid_by_name'     => $s->paid_by_name,
                'paid_to_name'     => $s->paid_to_name,
                'amount_inr'       => $s->amount_inr,
                'notes'            => $s->notes,
                'effect_on_balance' => $effect,
            ];
        }

        usort($lines, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $lines;
    }
}
