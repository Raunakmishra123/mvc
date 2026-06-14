@extends('layouts.app')

@section('title', $group->name . ' — Import CSV')

@section('content')
<div class="page-header animate-slide-up">
    <div class="page-header-left">
        <h1 class="page-title">CSV Expense Import</h1>
        <p class="page-subtitle">Upload expenses sheet and run anomaly detection pipeline for <strong>{{ $group->name }}</strong></p>
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

<div class="grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; align-items: start;">
    <!-- Upload Form -->
    <div class="card animate-slide-up" style="animation-delay: 0.1s;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Upload CSV File</h2>
                <p class="card-subtitle">Select your raw `.csv` file exported from Google Sheets or Excel.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('import.run', $group) }}" enctype="multipart/form-data" class="form-grid" style="display: flex; flex-direction: column; gap: 1.25rem;">
            @csrf

            <div class="form-group">
                <label class="form-label" for="csv_file">Select File</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,.txt" required style="padding-top: 0.6rem; padding-bottom: 0.6rem;">
                <p style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.35rem;">
                    Max size: 5MB. Expected headers: <code>date</code>, <code>paid_by</code>, <code>amount</code>, <code>currency</code>, <code>split_type</code>, <code>split_with</code>, <code>split_details</code>, <code>description</code>.
                </p>
            </div>

            <button type="submit" class="btn btn-primary" style="justify-content: center; width: 100%;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Upload & Import
            </button>
        </form>
    </div>

    <!-- Import Batches History -->
    <div class="card animate-slide-up" style="animation-delay: 0.2s;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Import History</h2>
                <p class="card-subtitle">List of past imports processed for this group.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Filename</th>
                        <th>Imported By</th>
                        <th style="text-align: center;">Rows</th>
                        <th style="text-align: center;">Anomalies</th>
                        <th style="text-align: right;">Report</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                        <tr>
                            <td style="font-size: 0.85rem; color: var(--color-text-secondary); white-space: nowrap;">
                                {{ $batch->imported_at ? $batch->imported_at->format('d M Y, h:i A') : $batch->created_at->format('d M Y, h:i A') }}
                            </td>
                            <td style="font-weight: 500; font-size: 0.9rem; color: var(--color-text-primary);">
                                {{ $batch->filename }}
                            </td>
                            <td>
                                {{ $batch->importer->name }}
                            </td>
                            <td style="text-align: center; font-weight: 600; font-family: monospace;">
                                {{ $batch->row_count }}
                            </td>
                            <td style="text-align: center;">
                                @if($batch->anomaly_count > 0)
                                    <span class="badge badge-warning" style="font-family: monospace; font-weight: 600;">{{ $batch->anomaly_count }}</span>
                                @else
                                    <span class="badge badge-success">0</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('import.report', $batch) }}" class="btn btn-sm btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                    View Report
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 3rem 1.5rem;">
                                No CSV uploads recorded yet. Upload a file above to begin.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
