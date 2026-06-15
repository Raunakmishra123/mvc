@extends('layouts.app')

@section('title', 'My Groups')

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">My Groups</h1>
        <p class="page-subtitle">Manage your shared expense groups</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('groups.create') }}" class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Group
        </a>
    </div>
</div>

@if($groups->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                </svg>
            </div>
            <h3>No groups yet</h3>
            <p>Create your first expense-sharing group to get started tracking costs with your flatmates.</p>
            <a href="{{ route('groups.create') }}" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create a Group
            </a>
        </div>
    </div>
@else
    <div class="groups-grid stagger">
        @foreach($groups as $group)
            @php
                $activeMembers = $group->memberships->where('is_active', true);
                $avatarColors = ['avatar-color-0','avatar-color-1','avatar-color-2','avatar-color-3','avatar-color-4','avatar-color-5','avatar-color-6','avatar-color-7'];
            @endphp
            <a href="{{ route('groups.show', $group) }}" class="group-card animate-slide-up">
                <div>
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem;">
                        <div class="group-card-name">{{ $group->name }}</div>
                        <span class="badge badge-accent">{{ $group->home_currency }}</span>
                    </div>
                    @if($group->description)
                        <p style="font-size: 0.82rem; color: var(--color-text-muted); margin-top: 0.4rem; line-height: 1.5;">{{ Str::limit($group->description, 80) }}</p>
                    @endif
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <!-- Avatar stack -->
                        <div class="avatar-stack">
                            @foreach($activeMembers->take(5) as $i => $membership)
                                <div class="avatar {{ $avatarColors[$i % count($avatarColors)] }}" title="{{ $membership->user->name }}">
                                    {{ strtoupper(substr($membership->user->name, 0, 1)) }}
                                </div>
                            @endforeach
                            @if($activeMembers->count() > 5)
                                <div class="avatar" style="background: var(--color-surface-hover); color: var(--color-text-muted); font-size: 0.65rem;">
                                    +{{ $activeMembers->count() - 5 }}
                                </div>
                            @endif
                        </div>
                        <span style="font-size: 0.8rem; color: var(--color-text-muted);">
                            {{ $activeMembers->count() }} {{ Str::plural('member', $activeMembers->count()) }}
                        </span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; color: var(--color-text-muted);">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                        </svg>
                        {{ $group->expenses_count ?? $group->expenses()->count() }} expenses
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 0.75rem; border-top: 1px solid var(--color-border);">
                    <span style="font-size: 0.78rem; color: var(--color-text-muted);">
                        Created {{ $group->created_at->diffForHumans() }}
                    </span>
                    <span style="font-size: 0.82rem; font-weight: 500; color: var(--color-accent); display: flex; align-items: center; gap: 0.3rem;">
                        View
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
