<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'remaining_quantity',
        'type',
        'movement_type',
        'reason',
        'reference',
        'reference_id',
        'source_stock_movement_id',
        'performed_by_user_id',
        'notes',
        'expiration_date',
        'expired',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'remaining_quantity' => 'decimal:2',
        'expiration_date' => 'date',
        'expired' => 'boolean',
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_stock_movement_id');
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
