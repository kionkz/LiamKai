<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'supplier_id',
        'order_date',
        'order_number',
        'total_amount',
        'ordered_quantity',
        'received_quantity',
        'status',
        'expected_delivery_date',
        'actual_delivery_date',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'ordered_quantity' => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
    ];

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
