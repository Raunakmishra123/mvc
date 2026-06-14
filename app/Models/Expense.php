<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'description', 'expense_date', 'paid_by',
        'split_type', 'original_amount', 'original_currency', 'exchange_rate',
        'amount_inr', 'notes', 'needs_review', 'review_reason',
        'is_duplicate_of', 'excluded_from_balances', 'source',
        'import_batch_id', 'created_by',
    ];

    protected $casts = [
        'expense_date'           => 'date',
        'needs_review'           => 'boolean',
        'excluded_from_balances' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function splits()
    {
        return $this->hasMany(ExpenseSplit::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function anomalies()
    {
        return $this->hasMany(ImportAnomaly::class);
    }
}
