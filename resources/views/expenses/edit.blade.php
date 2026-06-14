@extends('layouts.app')

@section('title', 'Edit Expense — ' . $expense->description)

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Edit Expense</h1>
        <p class="page-subtitle">{{ $group->name }} &mdash; {{ $expense->description }}</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('groups.expenses.show', [$group, $expense]) }}" class="btn btn-secondary">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Cancel
        </a>
    </div>
</div>

<div style="max-width: 720px;">
    <form method="POST" action="{{ route('groups.expenses.update', [$group, $expense]) }}" id="expense-form">
        @csrf
        @method('PUT')

        <!-- Basic Details -->
        <div class="card" style="margin-bottom: 1.25rem;">
            <div class="card-header">
                <div class="card-title">Expense Details</div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="description" class="form-label">Description <span class="required">*</span></label>
                    <input
                        type="text"
                        id="description"
                        name="description"
                        class="form-control"
                        value="{{ old('description', $expense->description) }}"
                        required
                        maxlength="255"
                    >
                    @error('description')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date" class="form-label">Date <span class="required">*</span></label>
                        <input
                            type="date"
                            id="date"
                            name="date"
                            class="form-control"
                            value="{{ old('date', $expense->date) }}"
                            required
                        >
                        @error('date')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label for="paid_by" class="form-label">Paid By <span class="required">*</span></label>
                        <select id="paid_by" name="paid_by" class="form-select" required>
                            <option value="">— Who paid? —</option>
                            @foreach($activeMembers as $member)
                                <option value="{{ $member->user_id }}" {{ old('paid_by', $expense->paid_by) == $member->user_id ? 'selected' : '' }}>
                                    {{ $member->user->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('paid_by')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="amount" class="form-label">Amount (INR) <span class="required">*</span></label>
                        <input
                            type="number"
                            id="amount"
                            name="amount"
                            class="form-control"
                            value="{{ old('amount', $expense->amount_inr) }}"
                            min="0.01"
                            step="0.01"
                            required
                        >
                        @error('amount')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label for="split_type" class="form-label">Split Type <span class="required">*</span></label>
                        <select id="split_type" name="split_type" class="form-select" required onchange="handleSplitTypeChange(this.value)">
                            <option value="equal"      {{ old('split_type', $expense->split_type) === 'equal'      ? 'selected' : '' }}>Equal</option>
                            <option value="unequal"    {{ old('split_type', $expense->split_type) === 'unequal'    ? 'selected' : '' }}>Unequal Amounts</option>
                            <option value="percentage" {{ old('split_type', $expense->split_type) === 'percentage' ? 'selected' : '' }}>Percentage</option>
                            <option value="share"      {{ old('split_type', $expense->split_type) === 'share'      ? 'selected' : '' }}>Shares / Ratio</option>
                        </select>
                        @error('split_type')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes" class="form-label">Notes <span style="color: var(--color-text-muted); font-weight: 400;">(optional)</span></label>
                    <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $expense->notes) }}</textarea>
                </div>
            </div>
        </div>

        <!-- Split With -->
        @php
            $existingSplitUserIds = $expense->splits->pluck('user_id')->toArray();
        @endphp
        <div class="card" style="margin-bottom: 1.25rem;">
            <div class="card-header">
                <div class="card-title">Split With</div>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem;">
                    @foreach($activeMembers as $member)
                        <label style="display: flex; align-items: center; gap: 0.625rem; padding: 0.75rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); cursor: pointer; transition: var(--transition);">
                            <input
                                type="checkbox"
                                name="split_with[]"
                                value="{{ $member->user_id }}"
                                class="form-check-input split-member-checkbox"
                                {{ in_array($member->user_id, old('split_with', $existingSplitUserIds)) ? 'checked' : '' }}
                            >
                            <div class="member-avatar avatar-color-{{ $loop->index % 8 }}" style="width: 30px; height: 30px; font-size: 0.75rem;">
                                {{ strtoupper(substr($member->user->name, 0, 1)) }}
                            </div>
                            <span style="font-size: 0.875rem; font-weight: 500; color: var(--color-text-primary);">{{ $member->user->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Split Details -->
        <div class="card" id="split-details-card" style="margin-bottom: 1.25rem;">
            <div class="card-header">
                <div>
                    <div class="card-title">Split Details</div>
                    <div class="card-subtitle" id="split-details-hint">Enter values below</div>
                </div>
            </div>
            <div class="card-body">
                @foreach($activeMembers as $member)
                    @php
                        $existingSplit = $expense->splits->firstWhere('user_id', $member->user_id);
                    @endphp
                    <div class="form-group" id="split-detail-{{ $member->user_id }}" style="display: flex; align-items: center; gap: 0.75rem; flex-direction: row;">
                        <div class="member-avatar avatar-color-{{ $loop->index % 8 }}" style="width: 30px; height: 30px; font-size: 0.75rem; flex-shrink: 0;">
                            {{ strtoupper(substr($member->user->name, 0, 1)) }}
                        </div>
                        <label style="font-size: 0.875rem; font-weight: 500; min-width: 100px; color: var(--color-text-secondary);">{{ $member->user->name }}</label>
                        <input
                            type="number"
                            name="split_details[{{ $member->user_id }}]"
                            class="form-control split-detail-input"
                            step="0.01"
                            min="0"
                            value="{{ old('split_details.' . $member->user_id, $existingSplit?->raw_value) }}"
                            style="max-width: 160px;"
                        >
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Admin Overrides -->
        <div class="card" style="margin-bottom: 1.25rem;">
            <div class="card-header">
                <div class="card-title">Admin Overrides</div>
                <span class="badge badge-warning">Override flags</span>
            </div>
            <div class="card-body">
                <label class="form-check" style="padding: 0.75rem; background: rgba(245,158,11,0.05); border: 1px solid rgba(245,158,11,0.15); border-radius: var(--radius-md);">
                    <input
                        type="checkbox"
                        name="needs_review"
                        class="form-check-input"
                        value="1"
                        {{ old('needs_review', $expense->needs_review) ? 'checked' : '' }}
                    >
                    <span class="form-check-label">
                        <span style="font-weight: 600; color: var(--color-warning);">Needs Review</span>
                        <span style="display: block; font-size: 0.78rem; color: var(--color-text-muted); margin-top: 0.1rem;">Flag this expense for manual review (e.g. anomaly or uncertain data)</span>
                    </span>
                </label>

                <label class="form-check" style="padding: 0.75rem; background: rgba(239,68,68,0.04); border: 1px solid rgba(239,68,68,0.15); border-radius: var(--radius-md);">
                    <input
                        type="checkbox"
                        name="excluded_from_balances"
                        class="form-check-input"
                        value="1"
                        {{ old('excluded_from_balances', $expense->excluded_from_balances) ? 'checked' : '' }}
                    >
                    <span class="form-check-label">
                        <span style="font-weight: 600; color: var(--color-danger);">Excluded from Balances</span>
                        <span style="display: block; font-size: 0.78rem; color: var(--color-text-muted); margin-top: 0.1rem;">This expense will not count towards any person's balance calculations</span>
                    </span>
                </label>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
            <a href="{{ route('groups.expenses.show', [$group, $expense]) }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Update Expense
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function handleSplitTypeChange(type) {
    const hint = document.getElementById('split-details-hint');
    const inputs = document.querySelectorAll('.split-detail-input');
    const hints = {
        'equal':      'For equal splits, no input needed.',
        'unequal':    'Enter the exact INR amount for each person.',
        'percentage': 'Enter the percentage (%) for each person. Should sum to 100.',
        'share':      'Enter the share/ratio value for each person.',
    };
    hint.textContent = hints[type] || '';
    if (type === 'equal') {
        inputs.forEach(i => { i.disabled = true; i.placeholder = '—'; });
    } else {
        inputs.forEach(i => { i.disabled = false; i.placeholder = type === 'percentage' ? '0–100' : '0.00'; });
    }
}
handleSplitTypeChange(document.getElementById('split_type').value);
</script>
@endpush
