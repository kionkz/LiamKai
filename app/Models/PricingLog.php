<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingLog extends Model
{
    protected $fillable = [
        'product_id',
        'wholesale_price',
        'retail_price',
        'quantity',
        'product_supplier_id',
        'old_retail_price',
        'new_retail_price',
        'old_discount_percent',
        'new_discount_percent',
        'old_discounted_price',
        'new_discounted_price',
        'changed_at',
    ];

    protected $casts = [
        'wholesale_price'      => 'decimal:2',
        'retail_price'         => 'decimal:2',
        'quantity'             => 'decimal:2',
        'old_retail_price'     => 'decimal:2',
        'new_retail_price'     => 'decimal:2',
        'old_discount_percent' => 'decimal:2',
        'new_discount_percent' => 'decimal:2',
        'old_discounted_price' => 'decimal:2',
        'new_discounted_price' => 'decimal:2',
        'changed_at'           => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'product_supplier_id');
    }
}