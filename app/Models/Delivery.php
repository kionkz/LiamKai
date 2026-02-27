<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'employee_id',
        'status',
        'scheduled_delivery',
        'actual_delivery',
        'delivery_address',
        'notes',
    ];

    protected $casts = [
        'scheduled_delivery' => 'datetime',
        'actual_delivery' => 'datetime',
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
