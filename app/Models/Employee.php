<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'position',
        'role',
        'address',
        'status',
    ];

    // Relationships
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
