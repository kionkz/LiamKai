<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginSession extends Model
{
    protected $fillable = [
        'user_id',
        'login_time',
        'logout_time',
    ];

    protected $casts = [
        'login_time'  => 'datetime',
        'logout_time' => 'datetime',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Methods per diagram

    public function startSession(): void
    {
        $this->update(['login_time' => now()]);
    }

    public function endSession(): void
    {
        $this->update(['logout_time' => now()]);
    }

    public function getSessionDuration(): int
    {
        if (!$this->logout_time) {
            return (int) now()->diffInSeconds($this->login_time);
        }

        return (int) $this->logout_time->diffInSeconds($this->login_time);
    }
}
