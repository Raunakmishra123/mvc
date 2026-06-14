<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function groups()
    {
        return $this->hasManyThrough(
            Group::class, GroupMembership::class,
            'user_id', 'id', 'id', 'group_id'
        );
    }

    public function memberships()
    {
        return $this->hasMany(GroupMembership::class);
    }

    public function expensesPaid()
    {
        return $this->hasMany(Expense::class, 'paid_by');
    }
}
