<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'name',
        'fname',
        'lname',
        'email',
        'phone',
        'role',
        'address',
        'date_hired',
        'status',
        'can_edit_transactions',
        'view_proof_of_payments',
    ];

    protected $casts = [
        'date_hired'             => 'date',
        'can_edit_transactions'  => 'boolean',
        'view_proof_of_payments' => 'boolean',
    ];

    /**
     * Return the full name derived from fname + lname when available,
     * falling back to the stored `name` column.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->fname) {
            return trim($this->fname . ' ' . ($this->lname ?? ''));
        }

        return (string) $this->attributes['name'];
    }

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
