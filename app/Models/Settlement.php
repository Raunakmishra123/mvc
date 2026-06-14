<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'paid_by', 'paid_to', 'amount_inr',
        'settlement_date', 'notes', 'source', 'import_batch_id', 'created_by',
    ];

    protected $casts = ['settlement_date' => 'date'];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function payee()
    {
        return $this->belongsTo(User::class, 'paid_to');
    }
}
