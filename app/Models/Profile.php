<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'contact_number',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Methods per diagram

    public function updateProfile(array $attributes): void
    {
        $this->update($attributes);
    }

    public function getProfileDetails(): array
    {
        return $this->only(['user_id', 'name', 'email', 'contact_number']);
    }
}
