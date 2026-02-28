<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    protected $table = 'inventory';
    protected $fillable = [
        'product_id',
        'quantity',
        'quantity_on_hand',
        'reorder_point',
        'status',
        'last_restock_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_on_hand' => 'decimal:2',
        'reorder_point' => 'decimal:2',
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id', 'product_id');
    }
}
