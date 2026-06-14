<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = [
        'group_id', 'filename', 'imported_by',
        'imported_at', 'row_count', 'anomaly_count', 'status',
    ];

    protected $casts = ['imported_at' => 'datetime'];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function anomalies()
    {
        return $this->hasMany(ImportAnomaly::class, 'batch_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'import_batch_id');
    }
}
