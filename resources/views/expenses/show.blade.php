@extends('layouts.app')

@section('title', $expense->description . ' — Expense Detail')

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">{{ $expense->description }}</h1>
        <p class="page-subtitle">{{ $group->name }} &mdash; Expense Detail</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('groups.expenses.index', $group) }}" class="btn btn-secondary">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back
        </a>
        <a href="{{ route('groups.expenses.edit', [$group, $expense]) }}" class="btn btn-secondary">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Edit
        </a>
        <form method="POST" action="{{ route('groups.expenses.destroy', [$group, $expense]) }}" onsubmit="return confirm('Delete this expense permanently?')" style="display: inline;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Delete
            </button>
        </form>
    </div>
</div>

<!-- Status Flags -->
<div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
    @if($expense->needs_review)
        <span class="needs-review-badge" style="font-size: 0.8rem; padding: 0.3rem 0.75rem;">⚠ Needs Review</span>
    @endif
    @if($expense->excluded_from_balances)
        <span class="badge badge-neutral" style="font-size: 0.8rem; padding: 0.3rem 0.75rem;">Excluded from Balances</span>
    @endif
    @php
        $splitClass = match($expense->split_type) {
            'equal'      => 'split-pill-equal',
            'unequal'    => 'split-pill-unequal',
            'percentage' => 'split-pill-percentage',
            'share'      => 'split-pill-share',
            default      => 'split-pill-equal',
        };
    @endphp
    <span class="split-pill {{ $splitClass }}" style="font-size: 0.8rem; padding: 0.3rem 0.75rem;">{{ ucfirst($expense->split_type) }} Split</span>
</div>

<!-- Core Detail Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Expense Information</div>
    </div>
    <div class="detail-grid">
        <div class="detail-item">
            <div class="detail-label">Date</div>
            <div class="detail-value">{{ \Carbon\Carbon::parse($expense->date)->format('d F Y') }}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Paid By</div>
            <div class="detail-value">{{ $expense->paidBy?->name ?? 'Unknown' }}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Amount (INR)</div>
            <div class="detail-value large amount-positive">₹{{ number_format($expense->amount_inr, 2) }}</div>
        </div>
        @if($expense->original_currency && $expense->original_currency !== 'INR')
        <div class="detail-item">
            <div class="detail-label">Original Amount</div>
            <div class="detail-value">{{ $expense->original_currency }} {{ number_format($expense->original_amount, 2) }}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Exchange Rate</div>
            <div class="detail-value">1 {{ $expense->original_currency }} = ₹{{ number_format($expense->conversion_rate, 4) }}</div>
        </div>
        @endif
        <div class="detail-item">
            <div class="detail-label">Split Type</div>
            <div class="detail-value">{{ ucfirst($expense->split_type) }}</div>
        </div>
        @if($expense->notes)
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-label">Notes</div>
            <div class="detail-value" style="white-space: pre-wrap; color: var(--color-text-secondary);">{{ $expense->notes }}</div>
        </div>
        @endif
    </div>

    @if($expense->original_currency && $expense->original_currency !== 'INR')
        <div class="alert alert-info" style="margin-top: 1.25rem;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>
                <strong>Currency conversion:</strong>
                {{ $expense->original_currency }} {{ number_format($expense->original_amount, 2) }}
                × {{ number_format($expense->conversion_rate, 4) }}
                = ₹{{ number_format($expense->amount_inr, 2) }}
            </span>
        </div>
    @endif
</div>

<!-- Per-Person Splits -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Per-Person Splits</div>
    </div>
    @if($expense->splits->isEmpty())
        <p style="color: var(--color-text-muted); font-size: 0.875rem;">No split details recorded.</p>
    @else
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Share (INR)</th>
                        <th>Raw Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expense->splits as $split)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div class="member-avatar avatar-color-{{ $loop->index % 8 }}" style="width: 28px; height: 28px; font-size: 0.7rem;">
                                        {{ strtoupper(substr($split->user->name ?? '?', 0, 1)) }}
                                    </div>
                                    {{ $split->user->name ?? 'Unknown' }}
                                </div>
                            </td>
                            <td class="amount-cell">
                                <span class="amount-positive">₹{{ number_format($split->share_amount_inr, 2) }}</span>
                            </td>
                            <td style="color: var(--color-text-muted); font-family: 'Courier New', monospace; font-size: 0.85rem;">
                                {{ $split->raw_value ?? '—' }}
                                @if($expense->split_type === 'percentage')
                                    <span style="color: var(--color-text-muted);">%</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<!-- Anomalies -->
@if($expense->anomalies && $expense->anomalies->count() > 0)
<div class="card">
    <div class="card-header">
        <div class="card-title">Import Anomalies</div>
        <span class="badge badge-warning">{{ $expense->anomalies->count() }} found</span>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Severity</th>
                    <th>Description</th>
                    <th>Action Taken</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expense->anomalies as $anomaly)
                    <tr class="anomaly-row-{{ $anomaly->severity }}">
                        <td>
                            <span class="mono" style="font-size: 0.8rem;">{{ $anomaly->anomaly_type }}</span>
                        </td>
                        <td>
                            <span class="badge badge-{{ $anomaly->severity }}">{{ ucfirst($anomaly->severity) }}</span>
                        </td>
                        <td style="max-width: 300px;">{{ $anomaly->description }}</td>
                        <td style="color: var(--color-text-muted); font-size: 0.85rem;">{{ $anomaly->action_taken ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Toggle Actions -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Admin Controls</div>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        @if($expense->needs_review)
            <form method="POST" action="{{ route('groups.expenses.resolve', [$group, $expense]) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-success">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Mark Resolved
                </button>
            </form>
        @endif

        <form method="POST" action="{{ route('groups.expenses.toggleExclude', [$group, $expense]) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn {{ $expense->excluded_from_balances ? 'btn-success' : 'btn-warning' }}">
                @if($expense->excluded_from_balances)
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Include in Balances
                @else
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    Exclude from Balances
                @endif
            </button>
        </form>
    </div>
</div>
@endsection
