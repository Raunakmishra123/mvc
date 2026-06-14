<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportAnomaly extends Model
{
    protected $fillable = [
        'batch_id', 'row_number', 'raw_row', 'anomaly_type',
        'severity', 'description', 'action_taken',
        'expense_id', 'settlement_id', 'needs_human_review',
    ];

    protected $casts = [
        'raw_row'            => 'array',
        'needs_human_review' => 'boolean',
    ];

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
