<?php

namespace App\Support;

use App\Models\Pricing;
use App\Models\Product;

class PricingMath
{
    public static function normalizeDiscountPercent(float|int|string|null $value): float
    {
        $numeric = (float) ($value ?? 0);

        if ($numeric < 0) {
            return 0.0;
        }

        if ($numeric > 100) {
            return 100.0;
        }

        return round($numeric, 2);
    }

    public static function calculateDiscountedPrice(float|int|string|null $retailPrice, float|int|string|null $discountPercent): float
    {
        $retail = max(0, (float) ($retailPrice ?? 0));
        $discount = self::normalizeDiscountPercent($discountPercent);

        return round($retail * (1 - ($discount / 100)), 2);
    }

    public static function resolveOrderPrice(Product $product, string $customerType): float
    {
        /** @var Pricing|null $pricing */
        $pricing = $product->pricing->first();

        if (!$pricing) {
            return round((float) ($product->base_price ?? 0), 2);
        }

        $retailPrice = (float) ($pricing->retail_price ?? $product->base_price ?? 0);
        $discountedPrice = (float) ($pricing->discounted_price ?? self::calculateDiscountedPrice($retailPrice, $pricing->discount_percent));

        if ($customerType === 'wholesale') {
            return round($discountedPrice > 0 ? $discountedPrice : $retailPrice, 2);
        }

        return round($retailPrice, 2);
    }
}