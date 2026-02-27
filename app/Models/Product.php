<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'category',
        'description',
        'unit_of_measure',
        'base_price',
        'reorder_quantity',
        'expiration_date',
        'status',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'expiration_date' => 'date',
    ];

    // Relationships
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function pricing(): HasMany
    {
        return $this->hasMany(Pricing::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier');
    }
}
