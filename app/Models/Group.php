<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'home_currency', 'description', 'created_by'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memberships()
    {
        return $this->hasMany(GroupMembership::class);
    }

    public function activeMemberships()
    {
        return $this->hasMany(GroupMembership::class)
                    ->whereNull('left_on');
    }

    public function members()
    {
        return $this->hasManyThrough(
            User::class, GroupMembership::class,
            'group_id', 'id', 'id', 'user_id'
        );
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }

    public function importBatches()
    {
        return $this->hasMany(ImportBatch::class);
    }

    /** All user IDs currently active in this group (left_on IS NULL) */
    public function activeMemberIds(): array
    {
        return $this->memberships()
                    ->whereNull('left_on')
                    ->pluck('user_id')
                    ->toArray();
    }
}
