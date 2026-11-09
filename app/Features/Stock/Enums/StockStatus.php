<?php

namespace App\Features\Stock\Enums;

enum StockStatus: string
{
    case Available = 'available';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::LowStock => 'Stok Menipis',
            self::OutOfStock => 'Habis',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Available => 'bg-green-100 text-green-800',
            self::LowStock => 'bg-yellow-100 text-yellow-800',
            self::OutOfStock => 'bg-red-100 text-red-800',
        };
    }

    public static function fromQuantity(int $quantity, int $minStock): self
    {
        if ($quantity <= 0) {
            return self::OutOfStock;
        }

        if ($quantity <= $minStock) {
            return self::LowStock;
        }

        return self::Available;
    }
}