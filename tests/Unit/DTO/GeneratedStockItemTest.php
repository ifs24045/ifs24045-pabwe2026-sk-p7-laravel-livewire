<?php

namespace Tests\Unit\DTO;

use App\Features\Ai\DTO\GeneratedStockItem;
use PHPUnit\Framework\TestCase;

class GeneratedStockItemTest extends TestCase
{
    public function test_from_array_builds_item_from_valid_data(): void
    {
        $item = GeneratedStockItem::fromArray([
            'name' => '  Beras Premium  ',
            'sku' => 'BRS-001',
            'quantity' => 10,
            'unit' => 'karung',
            'price' => 68000.5,
            'min_stock' => 3,
            'description' => '**Beras** kualitas super',
        ]);

        $this->assertNotNull($item);
        $this->assertSame('Beras Premium', $item->name);
        $this->assertSame('BRS-001', $item->sku);
        $this->assertSame(10, $item->quantity);
        $this->assertSame('karung', $item->unit);
        $this->assertSame(68000.5, $item->price);
        $this->assertSame(3, $item->minStock);
        $this->assertSame('**Beras** kualitas super', $item->description);
    }

    public function test_from_array_returns_null_when_name_is_missing_blank_or_not_scalar(): void
    {
        $this->assertNull(GeneratedStockItem::fromArray([]));
        $this->assertNull(GeneratedStockItem::fromArray(['name' => '   ']));
        $this->assertNull(GeneratedStockItem::fromArray(['name' => ['bukan', 'teks']]));
    }

    public function test_from_array_applies_defaults_when_only_name_is_given(): void
    {
        $item = GeneratedStockItem::fromArray(['name' => 'Gula']);

        $this->assertNotNull($item);
        $this->assertNull($item->sku);
        $this->assertSame(0, $item->quantity);
        $this->assertSame('pcs', $item->unit);
        $this->assertNull($item->price);
        $this->assertSame(5, $item->minStock);
        $this->assertNull($item->description);
    }

    public function test_from_array_sanitizes_invalid_values(): void
    {
        $item = GeneratedStockItem::fromArray([
            'name' => 12345,
            'sku' => ['bukan', 'teks'],
            'quantity' => 'abc',
            'price' => 'abc',
            'min_stock' => -5,
        ]);

        $this->assertNotNull($item);
        $this->assertSame('12345', $item->name);
        $this->assertNull($item->sku);
        $this->assertSame(0, $item->quantity);
        $this->assertNull($item->price);
        $this->assertSame(0, $item->minStock);
    }

    public function test_from_array_clamps_negative_price_and_quantity_to_zero(): void
    {
        $item = GeneratedStockItem::fromArray([
            'name' => 'Teh',
            'quantity' => -10,
            'price' => -500,
        ]);

        $this->assertNotNull($item);
        $this->assertSame(0, $item->quantity);
        $this->assertSame(0.0, $item->price);
    }

    public function test_from_array_truncates_overlong_text(): void
    {
        $item = GeneratedStockItem::fromArray([
            'name' => str_repeat('a', 300),
            'sku' => str_repeat('b', 150),
            'unit' => str_repeat('c', 80),
            'description' => str_repeat('d', 6000),
        ]);

        $this->assertNotNull($item);
        $this->assertSame(255, mb_strlen($item->name));
        $this->assertSame(100, mb_strlen($item->sku));
        $this->assertSame(50, mb_strlen($item->unit));
        $this->assertSame(5000, mb_strlen($item->description));
    }

    public function test_to_array_uses_database_column_names(): void
    {
        $item = new GeneratedStockItem('Kopi', 'KOP-1', 25, 'pcs', 22000.0, 5, 'Kopi bubuk');

        $this->assertSame([
            'name' => 'Kopi',
            'sku' => 'KOP-1',
            'quantity' => 25,
            'unit' => 'pcs',
            'price' => 22000.0,
            'min_stock' => 5,
            'description' => 'Kopi bubuk',
        ], $item->toArray());
    }
}