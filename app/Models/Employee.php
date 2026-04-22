<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'address',
        'status',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    // Relationships
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
