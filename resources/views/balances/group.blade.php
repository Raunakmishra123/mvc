@extends('layouts.app')

@section('title', $group->name . ' — Balances')

@section('content')
<div class="page-header animate-slide-up">
    <div class="page-header-left">
        <h1 class="page-title">Group Balances</h1>
        <p class="page-subtitle">Net standings & settle-up plan for <strong>{{ $group->name }}</strong></p>
    </div>
    <div class="page-actions">
        <a href="{{ route('groups.show', $group) }}" class="btn btn-secondary">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Dashboard
        </a>
    </div>
</div>

<div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
    <!-- Net Balances List (Aisha's "one number per person" view) -->
    <div class="card animate-slide-up" style="animation-delay: 0.1s;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Net Standings</h2>
                <p class="card-subtitle">Positive indicates they are owed money; negative indicates they owe money.</p>
            </div>
            @if(abs($balanceSum) > 0.05)
                <span class="badge badge-warning" title="Conservation of money check failed by {{ $balanceSum }}">
                    Sum: {{ $balanceSum }}
                </span>
            @else
                <span class="badge badge-success">Balanced</span>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th style="text-align: right;">Net Balance</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allMembers as $membership)
                        @php
                            $uid = $membership->user_id;
                            $bal = $balances[$uid] ?? 0.0;
                        @endphp
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div class="member-avatar avatar-color-{{ $loop->index % 8 }}" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                        {{ strtoupper(substr($membership->user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <span style="font-weight: 500;">{{ $membership->user->name }}</span>
                                        @if(!$membership->left_on)
                                            <span style="font-size: 0.7rem; opacity: 0.6; display: block;">Active Member</span>
                                        @else
                                            <span style="font-size: 0.7rem; opacity: 0.5; display: block;">Left {{ $membership->left_on->format('d M') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td style="text-align: right; font-weight: 600; font-family: monospace;">
                                @if($bal > 0.005)
                                    <span style="color: var(--color-success);">+₹{{ number_format($bal, 2) }}</span>
                                @elseif($bal < -0.005)
                                    <span style="color: var(--color-danger);">-₹{{ number_format(abs($bal), 2) }}</span>
                                @else
                                    <span style="color: var(--color-text-muted);">₹0.00</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('balances.user', [$group, $membership->user_id]) }}" class="btn btn-sm btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                    Breakdown &rarr;
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Settle-Up Transactions (Greedy Minimal-Transactions Plan) -->
    <div class="card animate-slide-up" style="animation-delay: 0.2s;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Settle-up Plan</h2>
                <p class="card-subtitle">Recommended minimal-transaction settle-up payments.</p>
            </div>
            @if(count($settleUp) > 0)
                <span class="badge badge-accent">{{ count($settleUp) }} payments</span>
            @endif
        </div>

        @if(count($settleUp) === 0)
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 180px; text-align: center;">
                <svg width="40" height="40" fill="none" stroke="var(--color-success)" viewBox="0 0 24 24" style="opacity: 0.7; margin-bottom: 0.75rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div style="font-weight: 600; color: var(--color-text-primary);">Everything is Settled!</div>
                <div style="font-size: 0.8rem; color: var(--color-text-secondary); margin-top: 0.25rem;">No one owes anything to anyone.</div>
            </div>
        @else
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                @foreach($settleUp as $txn)
                    @php
                        $debtor = $users[$txn['from']] ?? null;
                        $creditor = $users[$txn['to']] ?? null;
                    @endphp
                    @if($debtor && $creditor)
                    <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.875rem 1rem; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; flex-direction: column; gap: 0.15rem;">
                            <div style="font-size: 0.85rem; color: var(--color-text-secondary);">
                                <strong style="color: var(--color-text-primary);">{{ $debtor->name }}</strong> owes
                            </div>
                            <div style="font-size: 0.85rem; color: var(--color-text-secondary);">
                                <strong style="color: var(--color-text-primary);">{{ $creditor->name }}</strong>
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.35rem;">
                            <div style="font-weight: 700; font-size: 1.1rem; color: var(--color-warning);">
                                ₹{{ number_format($txn['amount'], 2) }}
                            </div>
                            <a href="{{ route('settlements.index', $group) }}?from={{ $txn['from'] }}&to={{ $txn['to'] }}&amt={{ $txn['amount'] }}" class="btn btn-sm btn-primary" style="font-size: 0.7rem; padding: 0.15rem 0.5rem; height: auto;">
                                Record Payment
                            </a>
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>
            <div style="margin-top: 1.25rem; font-size: 0.75rem; color: var(--color-text-muted); text-align: center; line-height: 1.4;">
                This plan uses a greedy matching algorithm to find the fewest transactions needed to settle all balances.
            </div>
        @endif
    </div>
</div>
@endsection
