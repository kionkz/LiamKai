<?php

namespace App\Support;

use App\Models\Product;

class ProductSku
{
    public static function forProduct(Product $product): string
    {
        $categoryCode = self::codeFromText($product->category ?: $product->productCategory?->name ?: 'GEN', 3);
        $productCode = self::codeFromText($product->name ?: 'ITEM', 4);

        return sprintf('%s-%s-%04d', $categoryCode, $productCode, $product->id);
    }

    private static function codeFromText(string $text, int $length): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', strtoupper($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $code = '';

        foreach ($words as $word) {
            $code .= $word[0] ?? '';
            if (strlen($code) >= $length) {
                break;
            }
        }

        if (strlen($code) < $length) {
            $code .= preg_replace('/[^A-Z0-9]/', '', strtoupper($text));
        }

        return substr(str_pad($code ?: 'SKU', $length, 'X'), 0, $length);
    }
}
