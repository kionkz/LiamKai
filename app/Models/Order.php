<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'fulfillment_type',
        'order_type',
        'total_amount',
        'outstanding_balance',
        'payment_status',
        'fulfillment_status',
        'delivery_status',
        'delivery_address',
        'delivery_date',
        'scheduled_for',
        'actual_fulfillment_at',
        'fulfillment_action',
        'fulfillment_updated_by_user_id',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'delivery_date' => 'date',
        'scheduled_for' => 'datetime',
        'actual_fulfillment_at' => 'datetime',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fulfillmentUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfillment_updated_by_user_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function salesReport(): HasOne
    {
        return $this->hasOne(SalesReport::class);
    }
}
