<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMembership extends Model
{
    protected $fillable = ['group_id', 'user_id', 'joined_on', 'left_on'];

    protected $casts = ['joined_on' => 'date', 'left_on' => 'date'];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActiveOn($query, string $date)
    {
        return $query->where('joined_on', '<=', $date)
                     ->where(function ($q) use ($date) {
                         $q->whereNull('left_on')
                           ->orWhere('left_on', '>=', $date);
                     });
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->left_on === null;
    }
}
