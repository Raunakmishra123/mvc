@extends('layouts.app')

@section('title', $group->name . ' — Settlements')

@section('content')
<div class="page-header animate-slide-up">
    <div class="page-header-left">
        <h1 class="page-title">Settlements</h1>
        <p class="page-subtitle">Record and view debt-settling payments for <strong>{{ $group->name }}</strong></p>
    </div>
    <div class="page-actions">
        <a href="{{ route('balances.group', $group) }}" class="btn btn-secondary">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            View Balances
        </a>
    </div>
</div>

<div class="grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; align-items: start;">
    <!-- Record Settlement Form -->
    <div class="card animate-slide-up" style="animation-delay: 0.1s;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Record Payment</h2>
                <p class="card-subtitle">Log a direct payment between members.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('settlements.store', $group) }}" class="form-grid" style="display: flex; flex-direction: column; gap: 1rem;">
            @csrf

            <!-- Paid By (Debtor) -->
            <div class="form-group">
                <label class="form-label" for="paid_by">Paid By (Debtor)</label>
                <select name="paid_by" id="paid_by" class="form-select" required>
                    <option value="">— Select Payer —</option>
                    @foreach($members as $m)
                        <option value="{{ $m->user_id }}" {{ request('from') == $m->user_id ? 'selected' : '' }}>
                            {{ $m->user->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Paid To (Creditor) -->
            <div class="form-group">
                <label class="form-label" for="paid_to">Paid To (Creditor)</label>
                <select name="paid_to" id="paid_to" class="form-select" required>
                    <option value="">— Select Payee —</option>
                    @foreach($members as $m)
                        <option value="{{ $m->user_id }}" {{ request('to') == $m->user_id ? 'selected' : '' }}>
                            {{ $m->user->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Amount (INR) -->
            <div class="form-group">
                <label class="form-label" for="amount_inr">Amount (INR)</label>
                <div class="input-prefix-group" style="position: relative; display: flex; align-items: center;">
                    <span style="position: absolute; left: 1rem; font-weight: 600; color: var(--color-text-muted);">₹</span>
                    <input type="number" step="0.01" name="amount_inr" id="amount_inr" class="form-control" style="padding-left: 2rem;" placeholder="0.00" value="{{ request('amt') }}" required>
                </div>
            </div>

            <!-- Date -->
            <div class="form-group">
                <label class="form-label" for="settlement_date">Settlement Date</label>
                <input type="date" name="settlement_date" id="settlement_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <!-- Notes -->
            <div class="form-group">
                <label class="form-label" for="notes">Notes</label>
                <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="e.g. UPI transfer, Cash payment, GPay"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem; justify-content: center;">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Save Settlement
            </button>
        </form>
    </div>

    <!-- Settlements History List -->
    <div class="card animate-slide-up" style="animation-delay: 0.2s;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Settlement History</h2>
                <p class="card-subtitle">List of recorded settlements in this group.</p>
            </div>
            <span class="badge badge-neutral">{{ count($settlements) }} total</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction</th>
                        <th style="text-align: right;">Amount</th>
                        <th>Notes / Source</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($settlements as $s)
                        <tr>
                            <td style="font-size: 0.85rem; color: var(--color-text-secondary); white-space: nowrap;">
                                {{ \Carbon\Carbon::parse($s->settlement_date)->format('d M Y') }}
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                                    <span style="font-weight: 600; color: var(--color-text-primary);">{{ $s->payer->name }}</span>
                                    <span style="color: var(--color-text-muted); font-size: 0.8rem;">paid</span>
                                    <span style="font-weight: 600; color: var(--color-text-primary);">{{ $s->payee->name }}</span>
                                </div>
                            </td>
                            <td style="text-align: right; font-weight: 700; font-family: monospace; color: var(--color-success);">
                                ₹{{ number_format($s->amount_inr, 2) }}
                            </td>
                            <td style="font-size: 0.8rem; color: var(--color-text-secondary);">
                                @if($s->source === 'import')
                                    <span class="badge badge-info" style="font-size: 0.6rem; padding: 0.1rem 0.3rem; vertical-align: middle;">Imported</span>
                                @endif
                                <span>{{ $s->notes ?: '—' }}</span>
                            </td>
                            <td style="text-align: right;">
                                <form method="POST" action="{{ route('settlements.destroy', $s) }}" onsubmit="return confirm('Delete this settlement record? This will revert the balance effect.');" style="display: inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--color-text-muted); padding: 3rem 1.5rem;">
                                No settlements recorded yet. Use the form to record one, or import settlements from a CSV.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
