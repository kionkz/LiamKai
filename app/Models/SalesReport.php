<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReport extends Model
{
    protected $fillable = [
        'order_id',
        'total_sales',
        'quantity_sold',
        'sale_date',
        'customer_type',
        'total_paid',
        'outstanding',
        'notes',
    ];

    protected $casts = [
        'total_sales' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding' => 'decimal:2',
        'sale_date' => 'date',
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
