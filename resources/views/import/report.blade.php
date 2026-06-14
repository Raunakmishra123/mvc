@extends('layouts.app')

@section('title', 'Import Anomaly Report — Batch #' . $batch->id)

@section('content')
<div class="page-header animate-slide-up">
    <div class="page-header-left">
        <h1 class="page-title">Import Anomaly Report</h1>
        <p class="page-subtitle">File: <strong>{{ $batch->filename }}</strong> &bull; Processed for <strong>{{ $batch->group->name }}</strong></p>
    </div>
    <div class="page-actions">
        <a href="{{ route('import.form', $batch->group) }}" class="btn btn-secondary">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Import
        </a>
        <a href="{{ route('balances.group', $batch->group) }}" class="btn btn-primary">
            View Group Balances
        </a>
    </div>
</div>

<!-- Import Summary Stats -->
<div class="stat-grid stagger animate-slide-up" style="animation-delay: 0.05s;">
    <div class="stat-card">
        <div class="stat-label">Total Rows</div>
        <div class="stat-value">{{ $batch->row_count }}</div>
        <div class="stat-meta">Processed from CSV</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Anomalies</div>
        <div class="stat-value" style="color: {{ $batch->anomaly_count > 0 ? 'var(--color-warning)' : 'var(--color-success)' }};">
            {{ $batch->anomaly_count }}
        </div>
        <div class="stat-meta">Across all records</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">High Severity</div>
        <div class="stat-value" style="color: {{ $highCount > 0 ? 'var(--color-danger)' : 'var(--color-text-muted)' }};">
            {{ $highCount }}
        </div>
        <div class="stat-meta">Requires attention</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Needs Human Review</div>
        <div class="stat-value" style="color: {{ $needsReviewCount > 0 ? 'var(--color-info)' : 'var(--color-text-muted)' }};">
            {{ $needsReviewCount }}
        </div>
        <div class="stat-meta">Flagged expenses</div>
    </div>
</div>

<!-- Anomaly Details Ledger -->
<div class="card animate-slide-up" style="animation-delay: 0.15s; padding: 1.5rem 0 0 0;">
    <div class="card-header" style="padding: 0 1.5rem 1rem 1.5rem;">
        <div>
            <h2 class="card-title">Anomaly Log</h2>
            <p class="card-subtitle">Showing details of all warnings, auto-corrections, and review flags applied to individual rows.</p>
        </div>
    </div>

    @if($batch->anomaly_count === 0)
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 1.5rem; text-align: center;">
            <svg width="48" height="48" fill="none" stroke="var(--color-success)" viewBox="0 0 24 24" style="margin-bottom: 1rem; opacity: 0.85;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <h3 style="font-weight: 600; font-size: 1.1rem; color: var(--color-text-primary);">Perfect Import!</h3>
            <p style="font-size: 0.85rem; color: var(--color-text-secondary); max-width: 320px; margin-top: 0.25rem;">
                No anomalies were detected. All spreadsheet records were clean and have been imported successfully.
            </p>
        </div>
    @else
        <div style="display: flex; flex-direction: column; gap: 0;">
            @foreach($anomaliesByRow as $rowNumber => $anomalies)
                @php
                    $firstAnomaly = $anomalies->first();
                    $raw = $firstAnomaly->raw_row;
                @endphp
                <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--color-border); background: rgba(255, 255, 255, 0.01);">
                    <!-- Row Header with Raw Data Preview -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.75rem;">
                        <div>
                            <span class="badge badge-neutral" style="font-weight: 700; font-family: monospace; font-size: 0.8rem; padding: 0.2rem 0.5rem;">
                                CSV Row #{{ $rowNumber }}
                            </span>
                            <span style="font-size: 0.85rem; color: var(--color-text-secondary); margin-left: 0.5rem; font-weight: 500;">
                                {{ $raw['description'] ?? 'No description' }}
                            </span>
                        </div>
                        
                        <!-- Raw Row Data Pills -->
                        <div style="display: flex; flex-wrap: wrap; gap: 0.35rem; font-size: 0.7rem; font-family: monospace;">
                            @if(isset($raw['date']))
                                <span style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--color-border); padding: 0.15rem 0.4rem; border-radius: 4px;" title="Date">
                                    Date: {{ $raw['date'] }}
                                </span>
                            @endif
                            @if(isset($raw['paid_by']))
                                <span style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--color-border); padding: 0.15rem 0.4rem; border-radius: 4px;" title="Paid By">
                                    Payer: {{ $raw['paid_by'] }}
                                </span>
                            @endif
                            @if(isset($raw['amount']))
                                <span style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--color-border); padding: 0.15rem 0.4rem; border-radius: 4px;" title="Amount">
                                    Amt: {{ $raw['amount'] }} {{ $raw['currency'] ?? 'INR' }}
                                </span>
                            @endif
                            @if(isset($raw['split_type']))
                                <span style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--color-border); padding: 0.15rem 0.4rem; border-radius: 4px;" title="Split Type">
                                    Split: {{ $raw['split_type'] }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- List of Anomalies on this row -->
                    <div style="display: flex; flex-direction: column; gap: 0.65rem; margin-top: 0.5rem; padding-left: 0.5rem; border-left: 2px solid rgba(255, 255, 255, 0.05);">
                        @foreach($anomalies as $anomaly)
                            <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.75rem 1rem; display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;">
                                <div style="display: flex; flex-direction: column; gap: 0.25rem; flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                        <span class="badge" style="
                                            @if($anomaly->severity === 'high') background: rgba(239, 68, 68, 0.15); color: var(--color-danger); border: 1px solid rgba(239, 68, 68, 0.3);
                                            @elseif($anomaly->severity === 'low') background: rgba(245, 158, 11, 0.15); color: var(--color-warning); border: 1px solid rgba(245, 158, 11, 0.3);
                                            @else background: rgba(59, 130, 246, 0.15); color: var(--color-info); border: 1px solid rgba(59, 130, 246, 0.3);
                                            @endif
                                            font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
                                        ">
                                            {{ $anomaly->severity }}
                                        </span>
                                        <code style="font-size: 0.75rem; font-weight: 600; color: var(--color-text-primary); font-family: monospace;">
                                            {{ $anomaly->anomaly_type }}
                                        </code>
                                    </div>
                                    <p style="font-size: 0.85rem; color: var(--color-text-primary); line-height: 1.4; margin-top: 0.15rem;">
                                        {{ $anomaly->description }}
                                    </p>
                                    <div style="font-size: 0.78rem; color: var(--color-text-secondary); display: flex; align-items: center; gap: 0.25rem; margin-top: 0.15rem;">
                                        <span style="font-weight: 600; color: var(--color-text-muted);">Action taken:</span>
                                        <span>{{ $anomaly->action_taken }}</span>
                                    </div>
                                </div>

                                <!-- Review / Resolution Links -->
                                @if($anomaly->needs_human_review)
                                    <div style="flex-shrink: 0; align-self: center;">
                                        @if($anomaly->expense_id)
                                            <a href="{{ route('groups.expenses.edit', [$batch->group, $anomaly->expense_id]) }}" class="btn btn-sm btn-secondary" style="font-size: 0.75rem; white-space: nowrap; padding: 0.35rem 0.75rem;">
                                                Resolve Expense
                                            </a>
                                        @elseif($anomaly->settlement_id)
                                            <a href="{{ route('settlements.index', $batch->group) }}" class="btn btn-sm btn-secondary" style="font-size: 0.75rem; white-space: nowrap; padding: 0.35rem 0.75rem;">
                                                Review Settlements
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
