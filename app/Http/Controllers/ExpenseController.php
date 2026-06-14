<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Services\SplitCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Group $group)
    {
        $this->authoriseMember($group);

        $filter   = request('filter', 'all');
        $query    = $group->expenses()->with('payer', 'splits.user');

        if ($filter === 'review') {
            $query->where('needs_review', true);
        } elseif ($filter === 'excluded') {
            $query->where('excluded_from_balances', true);
        }

        $expenses = $query->orderByDesc('expense_date')->orderByDesc('id')->paginate(20);

        return view('expenses.index', compact('group', 'expenses', 'filter'));
    }

    public function create(Group $group)
    {
        $this->authoriseMember($group);
        $members = $this->activeMembersForGroup($group);
        return view('expenses.create', compact('group', 'members'));
    }

    public function store(Request $request, Group $group)
    {
        $this->authoriseMember($group);

        $data = $request->validate([
            'description'  => ['required', 'string', 'max:500'],
            'expense_date' => ['required', 'date'],
            'paid_by'      => ['required', 'exists:users,id'],
            'split_type'   => ['required', 'in:equal,unequal,percentage,share'],
            'amount_inr'   => ['required', 'numeric'],
            'split_with'   => ['required', 'array', 'min:1'],
            'split_with.*' => ['exists:users,id'],
            'split_values' => ['nullable', 'array'],
            'notes'        => ['nullable', 'string'],
        ]);

        $memberIds  = array_map('intval', $data['split_with']);
        $rawDetails = [];
        foreach (($data['split_values'] ?? []) as $uid => $val) {
            if ($val !== '' && $val !== null) {
                $rawDetails[(int)$uid] = (float)$val;
            }
        }

        $expense = DB::transaction(function () use ($data, $group, $memberIds, $rawDetails) {
            $exp = Expense::create([
                'group_id'          => $group->id,
                'description'       => $data['description'],
                'expense_date'      => $data['expense_date'],
                'paid_by'           => (int)$data['paid_by'],
                'split_type'        => $data['split_type'],
                'original_amount'   => (float)$data['amount_inr'],
                'original_currency' => 'INR',
                'exchange_rate'     => 1.0,
                'amount_inr'        => (float)$data['amount_inr'],
                'notes'             => $data['notes'] ?? null,
                'source'            => 'manual',
                'created_by'        => Auth::id(),
            ]);

            $splits = SplitCalculator::compute(
                (float)$data['amount_inr'],
                $data['split_type'],
                $memberIds,
                $rawDetails,
                (int)$data['paid_by']
            );

            foreach ($splits as $s) {
                ExpenseSplit::create([
                    'expense_id'       => $exp->id,
                    'user_id'          => $s['user_id'],
                    'raw_value'        => $s['raw_value'],
                    'share_amount_inr' => $s['share_amount_inr'],
                ]);
            }

            return $exp;
        });

        return redirect()->route('groups.expenses.show', [$group, $expense])
                         ->with('success', 'Expense added successfully.');
    }

    public function show(Group $group, Expense $expense)
    {
        $this->authoriseMember($group);
        $expense->load('payer', 'splits.user', 'anomalies');
        return view('expenses.show', compact('group', 'expense'));
    }

    public function edit(Group $group, Expense $expense)
    {
        $this->authoriseMember($group);
        // Show all-time members so imported expenses (with historical members) can be edited
        $allMembers = GroupMembership::where('group_id', $group->id)
            ->with('user')
            ->get()
            ->unique('user_id')
            ->values()
            ->pluck('user');
        $expense->load('splits');
        return view('expenses.edit', compact('group', 'expense', 'allMembers'));
    }

    public function update(Request $request, Group $group, Expense $expense)
    {
        $this->authoriseMember($group);

        $data = $request->validate([
            'description'            => ['required', 'string', 'max:500'],
            'expense_date'           => ['required', 'date'],
            'paid_by'                => ['required', 'exists:users,id'],
            'split_type'             => ['required', 'in:equal,unequal,percentage,share'],
            'amount_inr'             => ['required', 'numeric'],
            'split_with'             => ['required', 'array', 'min:1'],
            'split_with.*'           => ['exists:users,id'],
            'split_values'           => ['nullable', 'array'],
            'notes'                  => ['nullable', 'string'],
        ]);

        $memberIds  = array_map('intval', $data['split_with']);
        $rawDetails = [];
        foreach (($data['split_values'] ?? []) as $uid => $val) {
            if ($val !== '' && $val !== null) {
                $rawDetails[(int)$uid] = (float)$val;
            }
        }

        DB::transaction(function () use ($request, $data, $expense, $memberIds, $rawDetails) {
            $expense->splits()->delete();

            $expense->update([
                'description'            => $data['description'],
                'expense_date'           => $data['expense_date'],
                'paid_by'                => (int)$data['paid_by'],
                'split_type'             => $data['split_type'],
                'original_amount'        => (float)$data['amount_inr'],
                'amount_inr'             => (float)$data['amount_inr'],
                'notes'                  => $data['notes'] ?? null,
                'needs_review'           => $request->boolean('needs_review'),
                'excluded_from_balances' => $request->boolean('excluded_from_balances'),
            ]);

            $splits = SplitCalculator::compute(
                (float)$data['amount_inr'],
                $data['split_type'],
                $memberIds,
                $rawDetails,
                (int)$data['paid_by']
            );

            foreach ($splits as $s) {
                ExpenseSplit::create([
                    'expense_id'       => $expense->id,
                    'user_id'          => $s['user_id'],
                    'raw_value'        => $s['raw_value'],
                    'share_amount_inr' => $s['share_amount_inr'],
                ]);
            }
        });

        return redirect()->route('groups.expenses.show', [$group, $expense])
                         ->with('success', 'Expense updated.');
    }

    public function destroy(Group $group, Expense $expense)
    {
        $this->authoriseMember($group);
        $expense->delete();
        return redirect()->route('groups.expenses.index', $group)
                         ->with('success', 'Expense deleted.');
    }

    public function toggleReview(Request $request, Expense $expense)
    {
        $expense->update(['needs_review' => !$expense->needs_review]);
        return back()->with('success', 'Review flag ' . ($expense->needs_review ? 'cleared' : 'set') . '.');
    }

    public function toggleExclude(Request $request, Expense $expense)
    {
        $expense->update(['excluded_from_balances' => !$expense->excluded_from_balances]);
        $msg = $expense->excluded_from_balances
            ? 'Expense included in balances.'
            : 'Expense excluded from balances.';
        return back()->with('success', $msg);
    }

    private function activeMembersForGroup(Group $group)
    {
        return GroupMembership::where('group_id', $group->id)
            ->whereNull('left_on')
            ->with('user')
            ->get()
            ->pluck('user');
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
