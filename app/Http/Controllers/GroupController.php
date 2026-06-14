<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $groups = Group::whereHas('memberships', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['memberships.user'])->latest()->get();

        return view('groups.index', compact('groups'));
    }

    public function create()
    {
        return view('groups.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'home_currency' => ['required', 'string', 'in:INR,USD,EUR,GBP'],
            'description'   => ['nullable', 'string'],
        ]);

        $group = Group::create(array_merge($data, ['created_by' => Auth::id()]));

        // Creator becomes first active member
        GroupMembership::create([
            'group_id'  => $group->id,
            'user_id'   => Auth::id(),
            'joined_on' => today()->toDateString(),
        ]);

        return redirect()->route('groups.show', $group)
                         ->with('success', 'Group created! Add your flatmates below.');
    }

    public function show(Group $group)
    {
        $this->authoriseMember($group);
        $group->load(['memberships.user', 'expenses' => function ($q) {
            $q->latest('expense_date')->limit(5);
        }, 'settlements', 'importBatches']);

        $activeMemberIds = $group->activeMemberIds();
        $availableUsers = User::whereNotIn('id', $activeMemberIds)->orderBy('name')->get();
        return view('groups.show', compact('group', 'availableUsers'));
    }

    public function edit(Group $group)
    {
        $this->authoriseMember($group);
        return view('groups.edit', compact('group'));
    }

    public function update(Request $request, Group $group)
    {
        $this->authoriseMember($group);
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'home_currency' => ['required', 'string', 'in:INR,USD,EUR,GBP'],
            'description'   => ['nullable', 'string'],
        ]);
        $group->update($data);
        return redirect()->route('groups.show', $group)->with('success', 'Group updated.');
    }

    public function destroy(Group $group)
    {
        $this->authoriseMember($group);
        $group->delete();
        return redirect()->route('groups.index')->with('success', 'Group deleted.');
    }

    public function addMember(Request $request, Group $group)
    {
        $this->authoriseMember($group);
        $data = $request->validate([
            'user_id'   => ['required', 'exists:users,id'],
            'joined_on' => ['required', 'date'],
        ]);

        // Prevent duplicate active membership
        $existing = GroupMembership::where('group_id', $group->id)
            ->where('user_id', $data['user_id'])
            ->whereNull('left_on')
            ->first();

        if ($existing) {
            return back()->withErrors(['user_id' => 'That person is already an active member.']);
        }

        GroupMembership::create([
            'group_id'  => $group->id,
            'user_id'   => $data['user_id'],
            'joined_on' => $data['joined_on'],
        ]);

        $name = User::find($data['user_id'])->name;
        return back()->with('success', "{$name} added to group from {$data['joined_on']}.");
    }

    public function removeMember(Request $request, Group $group, User $user)
    {
        $this->authoriseMember($group);
        $data = $request->validate(['left_on' => ['required', 'date']]);

        $membership = GroupMembership::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->whereNull('left_on')
            ->firstOrFail();

        $membership->update(['left_on' => $data['left_on']]);

        return back()->with('success', "{$user->name} marked as leaving on {$data['left_on']}.");
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
