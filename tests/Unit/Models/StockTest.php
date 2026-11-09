<?php

namespace Tests\Unit\Models;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    public function test_attributes_are_cast_to_expected_types(): void
    {
        $stock = Stock::factory()->create([
            'quantity' => 7,
            'min_stock' => 5,
            'price' => 12500,
            'status' => StockStatus::Available,
        ])->refresh();

        $this->assertSame(7, $stock->quantity);
        $this->assertSame(5, $stock->min_stock);
        $this->assertSame('12500.00', $stock->price);
        $this->assertSame(StockStatus::Available, $stock->status);
    }

    public function test_status_can_be_assigned_from_string_value(): void
    {
        $stock = Stock::factory()->create(['status' => 'low_stock'])->refresh();

        $this->assertSame(StockStatus::LowStock, $stock->status);
    }

    public function test_stock_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(BelongsTo::class, $stock->user());
        $this->assertTrue($stock->user->is($user));
    }

    public function test_factory_generates_status_consistent_with_quantity(): void
    {
        foreach (range(1, 20) as $ignored) {
            $stock = Stock::factory()->make();

            $this->assertSame(
                StockStatus::fromQuantity($stock->quantity, $stock->min_stock),
                $stock->status
            );
        }
    }
}