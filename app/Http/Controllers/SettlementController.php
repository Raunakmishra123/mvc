<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettlementController extends Controller
{
    public function index(Group $group)
    {
        $this->authoriseMember($group);

        $settlements = Settlement::where('group_id', $group->id)
            ->with('payer', 'payee')
            ->orderByDesc('settlement_date')
            ->orderByDesc('id')
            ->get();

        // All-time members for the "from/to" dropdowns
        $members = GroupMembership::where('group_id', $group->id)
            ->with('user')
            ->get()
            ->unique('user_id')
            ->values();

        return view('settlements.index', compact('group', 'settlements', 'members'));
    }

    public function store(Request $request, Group $group)
    {
        $this->authoriseMember($group);

        $data = $request->validate([
            'paid_by'         => ['required', 'exists:users,id'],
            'paid_to'         => ['required', 'exists:users,id', 'different:paid_by'],
            'amount_inr'      => ['required', 'numeric', 'gt:0'],
            'settlement_date' => ['required', 'date'],
            'notes'           => ['nullable', 'string'],
        ]);

        Settlement::create(array_merge($data, [
            'group_id'   => $group->id,
            'source'     => 'manual',
            'created_by' => Auth::id(),
        ]));

        return back()->with('success', 'Settlement recorded. Balances have been updated.');
    }

    public function destroy(Settlement $settlement)
    {
        $settlement->delete();
        return back()->with('success', 'Settlement deleted.');
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
