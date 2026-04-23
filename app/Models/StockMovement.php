<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'inventory_id',
        'product_id',
        'quantity',
        'type',
        'movement_type',
        'movement_date',
        'reason',
        'reference',
        'reference_id',
        'source_type',
        'source_id',
        'notes',
        'expiration_date',
        'expired',
    ];

    protected $casts = [
        'quantity'        => 'decimal:2',
        'movement_date'   => 'date',
        'expiration_date' => 'date',
        'expired'         => 'boolean',
    ];

    // Relationships

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }
}
