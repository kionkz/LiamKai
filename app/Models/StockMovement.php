<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'type',
        'movement_type',
        'reason',
        'reference',
        'reference_id',
        'notes',
        'expiration_date',
        'expired',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'expiration_date' => 'date',
        'expired' => 'boolean',
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
