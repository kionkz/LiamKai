<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'category',
        'category_id',
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
        return $this->hasMany(Pricing::class)->latest('effective_date')->latest('id');
    }

    public function pricingLogs(): HasMany
    {
        return $this->hasMany(PricingLog::class)->latest('changed_at')->latest('id');
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

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
