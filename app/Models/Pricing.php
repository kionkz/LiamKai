<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pricing extends Model
{
    protected $table = 'pricing';
    protected $fillable = [
        'product_id',
        'retail_price',
        'discount_percent',
        'discounted_price',
        'effective_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'effective_date' => 'date',
        'end_date' => 'date',
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
