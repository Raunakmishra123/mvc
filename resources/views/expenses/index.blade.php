@extends('layouts.app')

@section('title', 'Expenses — ' . $group->name)

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Expenses</h1>
        <p class="page-subtitle">{{ $group->name }} &mdash; All recorded expenses</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('groups.expenses.create', $group) }}" class="btn btn-primary">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Expense
        </a>
    </div>
</div>

<!-- Filter Tabs -->
<div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
    <div class="filter-tabs">
        <a href="{{ route('groups.expenses.index', $group) }}" class="filter-tab {{ !request('filter') ? 'active' : '' }}">
            All
            <span class="badge badge-neutral" style="font-size: 0.65rem; padding: 0.1rem 0.4rem;">{{ $totalCount ?? $expenses->total() }}</span>
        </a>
        <a href="{{ route('groups.expenses.index', [$group, 'filter' => 'needs-review']) }}" class="filter-tab {{ request('filter') === 'needs-review' ? 'active' : '' }}">
            Needs Review
        </a>
        <a href="{{ route('groups.expenses.index', [$group, 'filter' => 'excluded']) }}" class="filter-tab {{ request('filter') === 'excluded' ? 'active' : '' }}">
            Excluded
        </a>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    @if($expenses->isEmpty())
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <h3>No expenses found</h3>
            <p>
                @if(request('filter'))
                    No expenses match this filter. <a href="{{ route('groups.expenses.index', $group) }}">View all expenses</a>
                @else
                    Add your first expense or import from a CSV file.
                @endif
            </p>
            @if(!request('filter'))
                <a href="{{ route('groups.expenses.create', $group) }}" class="btn btn-primary">Add Expense</a>
            @endif
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-clickable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Paid By</th>
                        <th>Amount (INR)</th>
                        <th>Split</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expenses as $expense)
                        <tr onclick="window.location='{{ route('groups.expenses.show', [$group, $expense]) }}'" class="{{ $expense->needs_review ? 'review-flag' : '' }}">
                            <td class="nowrap">
                                <span style="font-size: 0.82rem;">{{ \Carbon\Carbon::parse($expense->date)->format('d M Y') }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 500; color: var(--color-text-primary); max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" class="{{ $expense->excluded_from_balances ? 'excluded-badge' : '' }}">
                                    {{ $expense->description }}
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div class="member-avatar avatar-color-{{ ($expense->paidBy?->id ?? 0) % 8 }}" style="width: 26px; height: 26px; font-size: 0.68rem;">
                                        {{ strtoupper(substr($expense->paidBy?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <span style="font-size: 0.85rem;">{{ $expense->paidBy?->name ?? 'Unknown' }}</span>
                                </div>
                            </td>
                            <td class="amount-cell">
                                <span class="amount-positive">₹{{ number_format($expense->amount_inr, 2) }}</span>
                                @if($expense->original_currency !== 'INR' && $expense->original_currency)
                                    <span class="amount-secondary">{{ $expense->original_currency }} {{ number_format($expense->original_amount, 2) }}</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $splitClass = match($expense->split_type) {
                                        'equal'      => 'split-pill-equal',
                                        'unequal'    => 'split-pill-unequal',
                                        'percentage' => 'split-pill-percentage',
                                        'share'      => 'split-pill-share',
                                        default      => 'split-pill-equal',
                                    };
                                @endphp
                                <span class="split-pill {{ $splitClass }}">{{ ucfirst($expense->split_type) }}</span>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.3rem; align-items: flex-start;">
                                    @if($expense->needs_review)
                                        <span class="needs-review-badge">Review</span>
                                    @endif
                                    @if($expense->excluded_from_balances)
                                        <span class="badge badge-neutral">Excluded</span>
                                    @endif
                                    @if(!$expense->needs_review && !$expense->excluded_from_balances)
                                        <span class="badge badge-success">OK</span>
                                    @endif
                                </div>
                            </td>
                            <td onclick="event.stopPropagation()">
                                <div style="display: flex; gap: 0.4rem;">
                                    <a href="{{ route('groups.expenses.edit', [$group, $expense]) }}" class="btn btn-sm btn-secondary" title="Edit">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('groups.expenses.destroy', [$group, $expense]) }}" onsubmit="return confirm('Delete this expense?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($expenses->hasPages())
            <div style="padding: 0.75rem 1rem; border-top: 1px solid var(--color-border);">
                {{ $expenses->appends(request()->query())->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
