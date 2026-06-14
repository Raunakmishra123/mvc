@extends('layouts.app')

@section('title', 'New Group')

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Create Group</h1>
        <p class="page-subtitle">Set up a new shared expense group</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('groups.index') }}" class="btn btn-secondary">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Cancel
        </a>
    </div>
</div>

<div style="max-width: 600px;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Group Details</div>
                <div class="card-subtitle">Fill in the basics for your new group</div>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('groups.store') }}">
                @csrf

                <div class="form-group" style="margin-bottom: 1.125rem;">
                    <label for="name" class="form-label">Group Name <span class="required">*</span></label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-control"
                        value="{{ old('name') }}"
                        placeholder="e.g. Flat 4B, House 23..."
                        required
                        autofocus
                        maxlength="100"
                    >
                    @error('name')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="margin-bottom: 1.125rem;">
                    <label for="currency" class="form-label">Currency <span class="required">*</span></label>
                    <select id="currency" name="currency" class="form-select" required>
                        <option value="">— Select currency —</option>
                        <option value="INR" {{ old('currency') === 'INR' ? 'selected' : '' }}>INR — Indian Rupee (₹)</option>
                        <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>USD — US Dollar ($)</option>
                        <option value="EUR" {{ old('currency') === 'EUR' ? 'selected' : '' }}>EUR — Euro (€)</option>
                        <option value="GBP" {{ old('currency') === 'GBP' ? 'selected' : '' }}>GBP — British Pound (£)</option>
                    </select>
                    @error('currency')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="description" class="form-label">Description <span style="color: var(--color-text-muted); font-weight: 400;">(optional)</span></label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-control"
                        placeholder="Brief description of this group..."
                        rows="3"
                        maxlength="500"
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <a href="{{ route('groups.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                        </svg>
                        Create Group
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
