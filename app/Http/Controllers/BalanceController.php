<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use App\Services\BalanceCalculator;
use Illuminate\Support\Facades\Auth;

class BalanceController extends Controller
{
    /**
     * Group-wide balance summary (Aisha's "one number per person" requirement).
     * Shows net balance per person + greedy minimal settle-up transaction list.
     */
    public function group(Group $group)
    {
        $this->authoriseMember($group);

        $balances = BalanceCalculator::groupBalances($group->id);
        $settleUp = BalanceCalculator::settleUp($balances);

        // Load user objects for all IDs referenced in balances / settle-up
        $userIds = array_unique(array_merge(
            array_keys($balances),
            array_column($settleUp, 'from'),
            array_column($settleUp, 'to'),
        ));
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        // All members who have ever been in this group (including former members)
        $allMembers = GroupMembership::where('group_id', $group->id)
            ->with('user')
            ->get()
            ->unique('user_id')
            ->values();

        $balanceSum = round(array_sum($balances), 2);

        return view('balances.group', compact(
            'group', 'balances', 'settleUp', 'users', 'allMembers', 'balanceSum'
        ));
    }

    /**
     * Individual breakdown (Rohan's "no magic numbers" requirement).
     * Every expense line + settlement affecting this user, with running total.
     */
    public function user(Group $group, User $user)
    {
        $this->authoriseMember($group);

        $lines      = BalanceCalculator::userBreakdown($group->id, $user->id);
        $balances   = BalanceCalculator::groupBalances($group->id);
        $netBalance = $balances[$user->id] ?? 0.0;

        // Compute running total per line
        $running = 0.0;
        foreach ($lines as &$line) {
            $running     = round($running + $line['effect_on_balance'], 2);
            $line['running_total'] = $running;
        }
        unset($line);

        return view('balances.user', compact('group', 'user', 'lines', 'netBalance'));
    }

    private function authoriseMember(Group $group): void
    {
        $isMember = GroupMembership::where('group_id', $group->id)
            ->where('user_id', Auth::id())
            ->exists();
        if (!$isMember) {
            abort(403, 'You are not a member of this group.');
        }
    }
}
