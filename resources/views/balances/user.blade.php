@extends('layouts.app')

@section('title', $user->name . ' — Balance History')

@section('content')
<div class="page-header animate-slide-up">
    <div class="page-header-left">
        <h1 class="page-title">Balance Breakdown: {{ $user->name }}</h1>
        <p class="page-subtitle">Itemized audit history of all expenses and settlements in <strong>{{ $group->name }}</strong></p>
    </div>
    <div class="page-actions">
        <a href="{{ route('balances.group', $group) }}" class="btn btn-secondary">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Balances
        </a>
    </div>
</div>

<!-- Net balance banner -->
<div class="card animate-slide-up" style="background: rgba(255, 255, 255, 0.02); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding: 1.25rem 1.5rem;">
    <div>
        <div style="font-size: 0.85rem; color: var(--color-text-secondary); font-weight: 500;">Current Net Position</div>
        <div style="font-size: 1.8rem; font-weight: 700; margin-top: 0.15rem; font-family: monospace;">
            @if($netBalance > 0.005)
                <span style="color: var(--color-success);">+₹{{ number_format($netBalance, 2) }}</span>
            @elseif($netBalance < -0.005)
                <span style="color: var(--color-danger);">-₹{{ number_format(abs($netBalance), 2) }}</span>
            @else
                <span style="color: var(--color-text-muted);">₹0.00</span>
            @endif
        </div>
    </div>
    <div style="font-size: 0.85rem; color: var(--color-text-secondary); max-width: 320px; line-height: 1.4;">
        @if($netBalance > 0.005)
            Other members owe {{ $user->name }} a total of <strong>₹{{ number_format($netBalance, 2) }}</strong>.
        @elseif($netBalance < -0.005)
            {{ $user->name }} owes a total of <strong>₹{{ number_format(abs($netBalance), 2) }}</strong> to other members.
        @else
            All debts are fully settled.
        @endif
    </div>
</div>

<!-- Statement card -->
<div class="card animate-slide-up" style="animation-delay: 0.1s; padding: 0;">
    <div class="card-header" style="padding: 1.5rem 1.5rem 0.5rem 1.5rem;">
        <div>
            <h2 class="card-title">Transaction Ledger</h2>
            <p class="card-subtitle">Chronological ledger showing calculations and running balances. No magic numbers.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" style="vertical-align: middle;">
            <thead>
                <tr>
                    <th style="padding-left: 1.5rem; width: 110px;">Date</th>
                    <th>Details / Calculation</th>
                    <th style="text-align: right;">Total Amount</th>
                    <th style="text-align: right;">Your Share</th>
                    <th style="text-align: right;">Net Effect</th>
                    <th style="text-align: right; padding-right: 1.5rem; width: 140px;">Running Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lines as $line)
                    @php
                        $isExpense = ($line['type'] === 'expense');
                    @endphp
                    <tr style="background: {{ $isExpense ? 'transparent' : 'rgba(16, 185, 129, 0.02)' }};">
                        <!-- Date -->
                        <td style="padding-left: 1.5rem; font-size: 0.85rem; color: var(--color-text-secondary); white-space: nowrap;">
                            {{ \Carbon\Carbon::parse($line['date'])->format('d M Y') }}
                        </td>
                        
                        <!-- Details & Calculations -->
                        <td>
                            @if($isExpense)
                                <div style="font-weight: 600; color: var(--color-text-primary); font-size: 0.9rem;">
                                    {{ $line['description'] }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.15rem;">
                                    Paid by <strong>{{ $line['paid_by_name'] }}</strong>
                                    &bull; Split type: <span class="badge badge-neutral" style="font-size: 0.65rem; padding: 0.1rem 0.35rem; display: inline-block;">{{ $line['split_type'] }}</span>
                                    @if($line['original_currency'] !== 'INR')
                                        &bull; Converted from {{ $line['original_currency'] }} {{ number_format($line['original_amount'], 2) }} @ rate of {{ number_format($line['exchange_rate'], 2) }}
                                    @endif
                                </div>
                            @else
                                <div style="font-weight: 600; color: var(--color-text-success); font-size: 0.9rem; display: flex; align-items: center; gap: 0.35rem;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Settlement Payment
                                </div>
                                <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.15rem;">
                                    <strong>{{ $line['paid_by_name'] }}</strong> paid <strong>{{ $line['paid_to_name'] }}</strong>
                                    @if(!empty($line['notes']))
                                        &bull; <em>"{{ $line['notes'] }}"</em>
                                    @endif
                                </div>
                            @endif
                        </td>

                        <!-- Total Amount -->
                        <td style="text-align: right; font-family: monospace; font-size: 0.85rem;">
                            ₹{{ number_format($line['amount_inr'], 2) }}
                        </td>

                        <!-- Your Share -->
                        <td style="text-align: right; font-family: monospace; font-size: 0.85rem;">
                            @if($isExpense)
                                ₹{{ number_format($line['your_share_inr'], 2) }}
                            @else
                                &mdash;
                            @endif
                        </td>

                        <!-- Net Effect -->
                        <td style="text-align: right; font-family: monospace; font-weight: 600;">
                            @if($line['effect_on_balance'] > 0.005)
                                <span style="color: var(--color-success);">+₹{{ number_format($line['effect_on_balance'], 2) }}</span>
                            @elseif($line['effect_on_balance'] < -0.005)
                                <span style="color: var(--color-danger);">-₹{{ number_format(abs($line['effect_on_balance']), 2) }}</span>
                            @else
                                <span style="color: var(--color-text-muted);">₹0.00</span>
                            @endif
                        </td>

                        <!-- Running Balance -->
                        <td style="text-align: right; padding-right: 1.5rem; font-family: monospace; font-weight: 700; font-size: 0.95rem;">
                            @if($line['running_total'] > 0.005)
                                <span style="color: var(--color-success);">+₹{{ number_format($line['running_total'], 2) }}</span>
                            @elseif($line['running_total'] < -0.005)
                                <span style="color: var(--color-danger);">-₹{{ number_format(abs($line['running_total']), 2) }}</span>
                            @else
                                <span style="color: var(--color-text-muted);">₹0.00</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 3rem 1.5rem;">
                            No recorded transactions affecting {{ $user->name }} in this group.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
