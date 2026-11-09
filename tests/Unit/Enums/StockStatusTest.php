<?php

namespace Tests\Unit\Enums;

use App\Features\Stock\Enums\StockStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StockStatusTest extends TestCase
{
    public static function quantityProvider(): array
    {
        return [
            'habis saat nol' => [0, 5, StockStatus::OutOfStock],
            'habis saat negatif' => [-1, 5, StockStatus::OutOfStock],
            'menipis tepat di batas' => [5, 5, StockStatus::LowStock],
            'menipis di bawah batas' => [3, 5, StockStatus::LowStock],
            'tersedia di atas batas' => [6, 5, StockStatus::Available],
        ];
    }

    #[DataProvider('quantityProvider')]
    public function test_from_quantity(int $quantity, int $minStock, StockStatus $expected): void
    {
        $this->assertSame($expected, StockStatus::fromQuantity($quantity, $minStock));
    }

    public function test_label_for_each_case(): void
    {
        $this->assertSame('Tersedia', StockStatus::Available->label());
        $this->assertSame('Stok Menipis', StockStatus::LowStock->label());
        $this->assertSame('Habis', StockStatus::OutOfStock->label());
    }

    public function test_badge_class_for_each_case(): void
    {
        $this->assertStringContainsString('green', StockStatus::Available->badgeClass());
        $this->assertStringContainsString('yellow', StockStatus::LowStock->badgeClass());
        $this->assertStringContainsString('red', StockStatus::OutOfStock->badgeClass());
    }

    public function test_backed_values_and_lookup(): void
    {
        $this->assertSame('available', StockStatus::Available->value);
        $this->assertSame('low_stock', StockStatus::LowStock->value);
        $this->assertSame('out_of_stock', StockStatus::OutOfStock->value);
        $this->assertSame(StockStatus::LowStock, StockStatus::from('low_stock'));
        $this->assertNull(StockStatus::tryFrom('tidak-ada'));
    }
}