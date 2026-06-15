@extends('layouts.app')

@section('title', $group->name . ' — Dashboard')

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">{{ $group->name }}</h1>
        <p class="page-subtitle">
            <span class="badge badge-accent">{{ $group->home_currency }}</span>
            &nbsp;Group Dashboard
        </p>
    </div>
    <div class="page-actions">
        <a href="{{ route('groups.edit', $group) }}" class="btn btn-secondary">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Edit Group
        </a>
    </div>
</div>

<!-- Quick Stats -->
<div class="stat-grid stagger">
    <div class="stat-card">
        <div class="stat-label">Total Expenses</div>
        <div class="stat-value">{{ $group->expenses()->count() }}</div>
        <div class="stat-meta">All time</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Members</div>
        <div class="stat-value">{{ $group->memberships()->where('is_active', true)->count() }}</div>
        <div class="stat-meta">Currently in group</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Settlements</div>
        <div class="stat-value">{{ $group->settlements()->count() }}</div>
        <div class="stat-meta">Recorded payments</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Last Import</div>
        <div class="stat-value" style="font-size: 1rem;">
            @php $lastImport = $group->importBatches()->latest()->first(); @endphp
            {{ $lastImport ? $lastImport->created_at->diffForHumans() : 'Never' }}
        </div>
        <div class="stat-meta">CSV import</div>
    </div>
</div>

<!-- Quick Links -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Quick Navigation</div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem;">
        <a href="{{ route('groups.expenses.index', $group) }}" class="btn btn-secondary" style="flex-direction: column; gap: 0.5rem; padding: 1.25rem; height: auto; text-align: center;">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--color-text-primary);">Expenses</span>
        </a>
        <a href="{{ route('balances.group', $group) }}" class="btn btn-secondary" style="flex-direction: column; gap: 0.5rem; padding: 1.25rem; height: auto; text-align: center;">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
            </svg>
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--color-text-primary);">Balances</span>
        </a>
        <a href="{{ route('settlements.index', $group) }}" class="btn btn-secondary" style="flex-direction: column; gap: 0.5rem; padding: 1.25rem; height: auto; text-align: center;">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--color-text-primary);">Settlements</span>
        </a>
        <a href="{{ route('import.form', $group) }}" class="btn btn-secondary" style="flex-direction: column; gap: 0.5rem; padding: 1.25rem; height: auto; text-align: center;">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--color-text-primary);">Import CSV</span>
        </a>
    </div>
</div>

<!-- Membership Timeline -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Members</div>
            <div class="card-subtitle">Membership timeline</div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Joined</th>
                    <th>Left</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($group->memberships()->with('user')->orderByDesc('joined_on')->get() as $membership)
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.625rem;">
                                <div class="member-avatar avatar-color-{{ $loop->index % 8 }}">
                                    {{ strtoupper(substr($membership->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--color-text-primary); font-size: 0.875rem;">{{ $membership->user->name }}</div>
                                    <div style="font-size: 0.75rem; color: var(--color-text-muted);">{{ $membership->user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ \Carbon\Carbon::parse($membership->joined_on)->format('d M Y') }}</td>
                        <td>{{ $membership->left_on ? \Carbon\Carbon::parse($membership->left_on)->format('d M Y') : '—' }}</td>
                        <td>
                            @if($membership->is_active)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-neutral">Former</span>
                            @endif
                        </td>
                        <td>
                            @if($membership->is_active)
                                <button
                                    type="button"
                                    class="btn btn-sm btn-warning"
                                    onclick="document.getElementById('leave-form-{{ $membership->id }}').classList.toggle('hidden-form')"
                                >
                                    Mark as Left
                                </button>
                                <div id="leave-form-{{ $membership->id }}" class="hidden-form" style="display:none; margin-top: 0.5rem;">
                                    <form method="POST" action="{{ route('groups.members.remove', [$group, $membership->user]) }}" style="display: flex; gap: 0.5rem; align-items: flex-end;">
                                        @csrf
                                        @method('DELETE')
                                        <div class="form-group" style="margin: 0; flex: 1;">
                                            <label class="form-label" style="font-size: 0.75rem;">Left on</label>
                                            <input type="date" name="left_on" class="form-control" style="padding: 0.35rem 0.625rem;" value="{{ date('Y-m-d') }}" required>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-danger">Confirm</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">No members yet</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Add Member Form -->
    <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--color-border);">
        <div class="section-title">Add Member</div>
        <form method="POST" action="{{ route('groups.members.add', $group) }}" style="display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap;">
            @csrf
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label class="form-label">User</label>
                <select name="user_id" class="form-select" required>
                    <option value="">— Select user —</option>
                    @foreach($availableUsers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="min-width: 160px;">
                <label class="form-label">Joined on</label>
                <input type="date" name="joined_on" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            <div style="padding-bottom: 0;">
                <button type="submit" class="btn btn-primary">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Member
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
