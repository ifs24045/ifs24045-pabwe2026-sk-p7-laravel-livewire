<?php

namespace Tests\Unit\Repositories;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private StockRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new StockRepository();
    }

    public function test_paginate_returns_only_stocks_of_the_given_user(): void
    {
        $user = User::factory()->create();
        Stock::factory()->count(3)->create(['user_id' => $user->id]);
        Stock::factory()->count(2)->create();

        $result = $this->repository->paginateForUser($user->id);

        $this->assertSame(3, $result->total());
    }

    public function test_paginate_orders_newest_first(): void
    {
        $user = User::factory()->create();
        $first = Stock::factory()->create(['user_id' => $user->id]);
        $second = Stock::factory()->create(['user_id' => $user->id]);

        $ids = collect($this->repository->paginateForUser($user->id)->items())->pluck('id')->all();

        $this->assertSame([$second->id, $first->id], $ids);
    }

    public function test_paginate_respects_per_page(): void
    {
        $user = User::factory()->create();
        Stock::factory()->count(5)->create(['user_id' => $user->id]);

        $result = $this->repository->paginateForUser($user->id, '', '', 2);

        $this->assertCount(2, $result->items());
        $this->assertSame(5, $result->total());
        $this->assertSame(3, $result->lastPage());
    }

    public function test_paginate_searches_by_name_and_sku(): void
    {
        $user = User::factory()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Kopi Bubuk', 'sku' => 'KOP-001']);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Teh Celup', 'sku' => 'TEH-001']);

        $this->assertSame(1, $this->repository->paginateForUser($user->id, 'kopi')->total());
        $this->assertSame(1, $this->repository->paginateForUser($user->id, 'TEH-0')->total());
        $this->assertSame(0, $this->repository->paginateForUser($user->id, 'tidak ada')->total());
    }

    public function test_paginate_filters_by_status(): void
    {
        $user = User::factory()->create();
        Stock::factory()->create(['user_id' => $user->id, 'status' => 'available']);
        Stock::factory()->create(['user_id' => $user->id, 'status' => 'low_stock']);
        Stock::factory()->create(['user_id' => $user->id, 'status' => 'out_of_stock']);

        $this->assertSame(1, $this->repository->paginateForUser($user->id, '', 'low_stock')->total());
        $this->assertSame(3, $this->repository->paginateForUser($user->id, '', '')->total());
    }

    public function test_all_for_user_returns_user_stocks_ordered_by_name(): void
    {
        $user = User::factory()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Teh']);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Beras']);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Kopi']);
        Stock::factory()->create(['name' => 'Milik Orang Lain']);

        $result = $this->repository->allForUser($user->id);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame(['Beras', 'Kopi', 'Teh'], $result->pluck('name')->all());
    }

    public function test_create_persists_stock(): void
    {
        $user = User::factory()->create();

        $stock = $this->repository->create([
            'user_id' => $user->id,
            'name' => 'Gula Pasir',
            'quantity' => 12,
            'min_stock' => 5,
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'name' => 'Gula Pasir', 'user_id' => $user->id]);
    }

    public function test_update_persists_changes_and_returns_fresh_model(): void
    {
        $stock = Stock::factory()->create(['name' => 'Lama', 'quantity' => 1]);

        $updated = $this->repository->update($stock, ['name' => 'Baru', 'quantity' => 9]);

        $this->assertSame('Baru', $updated->name);
        $this->assertSame(9, $updated->quantity);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'name' => 'Baru', 'quantity' => 9]);
    }

    public function test_delete_removes_stock(): void
    {
        $stock = Stock::factory()->create();

        $this->repository->delete($stock);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_exists_sku_for_user_is_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Stock::factory()->create(['user_id' => $user->id, 'sku' => 'ABC-1']);

        $this->assertTrue($this->repository->existsSkuForUser($user->id, 'ABC-1'));
        $this->assertFalse($this->repository->existsSkuForUser($other->id, 'ABC-1'));
        $this->assertFalse($this->repository->existsSkuForUser($user->id, 'XYZ-9'));
    }
}