<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseSplit extends Model
{
    protected $fillable = ['expense_id', 'user_id', 'raw_value', 'share_amount_inr'];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
