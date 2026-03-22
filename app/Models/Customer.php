<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'type',
        'email',
        'phone',
        'address',
        'credit_limit',
        'current_balance',
        'notes',
        'status',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    public function setPhoneAttribute($value): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (str_starts_with($digits, '09') && strlen($digits) === 11) {
            $digits = '63' . substr($digits, 1);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '63' . $digits;
        }

        $this->attributes['phone'] = str_starts_with($digits, '63') ? '+' . $digits : (string) $value;
    }

    // Relationships
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
