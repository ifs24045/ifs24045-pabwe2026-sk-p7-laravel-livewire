<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->service = app(StockService::class);
    }

    public function test_list_returns_only_the_users_stocks_with_filters(): void
    {
        $user = User::factory()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Kopi', 'quantity' => 20, 'min_stock' => 5, 'status' => 'available']);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Teh', 'quantity' => 0, 'min_stock' => 5, 'status' => 'out_of_stock']);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Gula', 'quantity' => 20, 'min_stock' => 5, 'status' => 'available']);
        Stock::factory()->create();

        $this->assertSame(3, $this->service->list($user)->total());
        $this->assertSame(1, $this->service->list($user, 'kop')->total());
        $this->assertSame(1, $this->service->list($user, '', 'out_of_stock')->total());
        $this->assertCount(2, $this->service->list($user, '', '', 2)->items());
    }

    public function test_create_computes_status_and_assigns_owner(): void
    {
        $user = User::factory()->create();

        $stock = $this->service->create($user, [
            'name' => 'Kopi',
            'sku' => 'K-1',
            'description' => 'Kopi bubuk',
            'quantity' => 3,
            'unit' => 'pcs',
            'price' => 1000,
            'min_stock' => 5,
            'user_id' => 999,
            'status' => 'available',
            'cover_image_path' => 'hack.jpg',
        ]);

        $this->assertSame($user->id, $stock->user_id);
        $this->assertSame(StockStatus::LowStock, $stock->status);
        $this->assertNull($stock->cover_image_path);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'name' => 'Kopi', 'user_id' => $user->id]);
    }

    public function test_create_applies_defaults(): void
    {
        $user = User::factory()->create();

        $stock = $this->service->create($user, ['name' => 'Garam'])->refresh();

        $this->assertSame(0, $stock->quantity);
        $this->assertSame(5, $stock->min_stock);
        $this->assertSame('pcs', $stock->unit);
        $this->assertSame(StockStatus::OutOfStock, $stock->status);
    }

    public function test_create_stores_cover_image(): void
    {
        $user = User::factory()->create();

        $stock = $this->service->create(
            $user,
            ['name' => 'Beras', 'quantity' => 10, 'min_stock' => 5],
            UploadedFile::fake()->image('cover.jpg')
        );

        $this->assertNotNull($stock->cover_image_path);
        $this->assertStringStartsWith('stock-covers/', $stock->cover_image_path);
        Storage::disk('public')->assertExists($stock->cover_image_path);
    }

    public function test_update_recalculates_status_from_new_quantity(): void
    {
        $stock = Stock::factory()->create(['quantity' => 50, 'min_stock' => 5, 'status' => 'available']);

        $updated = $this->service->update($stock, ['name' => 'Baru', 'quantity' => 3]);

        $this->assertSame('Baru', $updated->name);
        $this->assertSame(3, $updated->quantity);
        $this->assertSame(StockStatus::LowStock, $updated->status);
    }

    public function test_update_recalculates_status_from_new_min_stock_only(): void
    {
        $stock = Stock::factory()->create(['quantity' => 8, 'min_stock' => 5, 'status' => 'available']);

        $updated = $this->service->update($stock, ['min_stock' => 10]);

        $this->assertSame(StockStatus::LowStock, $updated->status);
    }

    public function test_update_keeps_existing_cover_when_no_new_cover_given(): void
    {
        Storage::disk('public')->put('stock-covers/lama.jpg', 'x');
        $stock = Stock::factory()->create(['cover_image_path' => 'stock-covers/lama.jpg']);

        $updated = $this->service->update($stock, ['name' => 'Ganti Nama']);

        $this->assertSame('stock-covers/lama.jpg', $updated->cover_image_path);
        Storage::disk('public')->assertExists('stock-covers/lama.jpg');
    }

    public function test_update_replaces_cover_and_deletes_old_file(): void
    {
        Storage::disk('public')->put('stock-covers/lama.jpg', 'x');
        $stock = Stock::factory()->create(['cover_image_path' => 'stock-covers/lama.jpg']);

        $updated = $this->service->update($stock, [], UploadedFile::fake()->image('baru.jpg'));

        $this->assertNotSame('stock-covers/lama.jpg', $updated->cover_image_path);
        Storage::disk('public')->assertMissing('stock-covers/lama.jpg');
        Storage::disk('public')->assertExists($updated->cover_image_path);
    }

    public function test_update_removes_cover_when_requested(): void
    {
        Storage::disk('public')->put('stock-covers/lama.jpg', 'x');
        $stock = Stock::factory()->create(['cover_image_path' => 'stock-covers/lama.jpg']);

        $updated = $this->service->update($stock, [], null, true);

        $this->assertNull($updated->cover_image_path);
        Storage::disk('public')->assertMissing('stock-covers/lama.jpg');
    }

    public function test_update_remove_cover_on_stock_without_cover_is_harmless(): void
    {
        $stock = Stock::factory()->create(['cover_image_path' => null]);

        $updated = $this->service->update($stock, [], null, true);

        $this->assertNull($updated->cover_image_path);
    }

    public function test_adjust_quantity_increases_and_updates_status(): void
    {
        $stock = Stock::factory()->create(['quantity' => 3, 'min_stock' => 5, 'status' => 'low_stock']);

        $updated = $this->service->adjustQuantity($stock, 10);

        $this->assertSame(13, $updated->quantity);
        $this->assertSame(StockStatus::Available, $updated->status);
    }

    public function test_adjust_quantity_never_goes_below_zero(): void
    {
        $stock = Stock::factory()->create(['quantity' => 2, 'min_stock' => 5, 'status' => 'low_stock']);

        $updated = $this->service->adjustQuantity($stock, -5);

        $this->assertSame(0, $updated->quantity);
        $this->assertSame(StockStatus::OutOfStock, $updated->status);
    }

    public function test_delete_removes_stock_and_cover_file(): void
    {
        Storage::disk('public')->put('stock-covers/hapus.jpg', 'x');
        $stock = Stock::factory()->create(['cover_image_path' => 'stock-covers/hapus.jpg']);

        $this->service->delete($stock);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
        Storage::disk('public')->assertMissing('stock-covers/hapus.jpg');
    }

    public function test_delete_stock_without_cover(): void
    {
        $stock = Stock::factory()->create(['cover_image_path' => null]);

        $this->service->delete($stock);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }
}