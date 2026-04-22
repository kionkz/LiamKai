<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingLog extends Model
{
    protected $fillable = [
        'product_id',
        'changed_by_user_id',
        'changed_by_name',
        'old_retail_price',
        'new_retail_price',
        'old_discount_percent',
        'new_discount_percent',
        'old_discounted_price',
        'new_discounted_price',
        'changed_at',
    ];

    protected $casts = [
        'old_retail_price' => 'decimal:2',
        'new_retail_price' => 'decimal:2',
        'old_discount_percent' => 'decimal:2',
        'new_discount_percent' => 'decimal:2',
        'old_discounted_price' => 'decimal:2',
        'new_discounted_price' => 'decimal:2',
        'changed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}