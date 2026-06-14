@extends('layouts.app')

@section('title', 'Edit ' . $group->name)

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Edit Group</h1>
        <p class="page-subtitle">Update settings for {{ $group->name }}</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('groups.show', $group) }}" class="btn btn-secondary">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back
        </a>
    </div>
</div>

<div style="max-width: 600px; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Edit Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Group Details</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('groups.update', $group) }}">
                @csrf
                @method('PUT')

                <div class="form-group" style="margin-bottom: 1.125rem;">
                    <label for="name" class="form-label">Group Name <span class="required">*</span></label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-control"
                        value="{{ old('name', $group->name) }}"
                        placeholder="Group name"
                        required
                        maxlength="100"
                    >
                    @error('name')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="margin-bottom: 1.125rem;">
                    <label for="currency" class="form-label">Currency <span class="required">*</span></label>
                    <select id="currency" name="currency" class="form-select" required>
                        <option value="INR" {{ old('currency', $group->currency) === 'INR' ? 'selected' : '' }}>INR — Indian Rupee (₹)</option>
                        <option value="USD" {{ old('currency', $group->currency) === 'USD' ? 'selected' : '' }}>USD — US Dollar ($)</option>
                        <option value="EUR" {{ old('currency', $group->currency) === 'EUR' ? 'selected' : '' }}>EUR — Euro (€)</option>
                        <option value="GBP" {{ old('currency', $group->currency) === 'GBP' ? 'selected' : '' }}>GBP — British Pound (£)</option>
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
                        rows="3"
                        placeholder="Brief description..."
                        maxlength="500"
                    >{{ old('description', $group->description) }}</textarea>
                    @error('description')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <a href="{{ route('groups.show', $group) }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Danger Zone -->
    <div class="danger-zone">
        <div class="danger-zone-title">Danger Zone</div>
        <p>Deleting this group is permanent and cannot be undone. All expenses, settlements, and import history will be permanently removed.</p>
        <form method="POST" action="{{ route('groups.destroy', $group) }}" onsubmit="return confirm('Are you absolutely sure? This action cannot be undone.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Delete Group Permanently
            </button>
        </form>
    </div>
</div>
@endsection
