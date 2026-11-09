<?php

namespace Tests\Unit\Services;

use App\Features\Admin\Services\AdminUserService;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminUserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AdminUserService::class);
    }

    public function test_summary_counts_users_members_and_stocks(): void
    {
        User::factory()->admin()->create();
        $members = User::factory()->count(2)->create();
        Stock::factory()->count(3)->create(['user_id' => $members[0]->id]);

        $this->assertSame(['users' => 3, 'members' => 2, 'stocks' => 3], $this->service->summary());
    }

    public function test_paginate_users_includes_stock_counts_and_orders_admins_first(): void
    {
        User::factory()->admin()->create(['name' => 'Zed Admin']);
        $ana = User::factory()->create(['name' => 'Ana']);
        User::factory()->create(['name' => 'Budi']);
        Stock::factory()->create(['user_id' => $ana->id, 'quantity' => 3]);
        Stock::factory()->create(['user_id' => $ana->id, 'quantity' => 4]);

        $users = $this->service->paginateUsers()->items();

        $this->assertSame(['Zed Admin', 'Ana', 'Budi'], collect($users)->pluck('name')->all());
        $this->assertSame(2, $users[1]->stocks_count);
        $this->assertEquals(7, $users[1]->stocks_sum_quantity);
        $this->assertSame(0, $users[2]->stocks_count);
        $this->assertNull($users[2]->stocks_sum_quantity);
    }

    public function test_paginate_users_searches_name_username_and_email(): void
    {
        User::factory()->create(['name' => 'Siti Aminah', 'username' => 'siti', 'email' => 'siti@example.com']);
        User::factory()->create(['name' => 'Joko', 'username' => 'joko99', 'email' => 'joko@kantor.test']);

        $this->assertSame(1, $this->service->paginateUsers('Aminah')->total());
        $this->assertSame(1, $this->service->paginateUsers('joko99')->total());
        $this->assertSame(1, $this->service->paginateUsers('kantor.test')->total());
        $this->assertSame(0, $this->service->paginateUsers('tidak ada')->total());
    }

    public function test_paginate_users_respects_per_page(): void
    {
        User::factory()->count(3)->create();

        $page = $this->service->paginateUsers('', 2);

        $this->assertCount(2, $page->items());
        $this->assertSame(2, $page->lastPage());
    }

    public function test_paginate_stocks_for_returns_only_that_users_stocks_with_filters(): void
    {
        $user = User::factory()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Kopi', 'status' => 'available']);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Teh', 'status' => 'out_of_stock']);
        Stock::factory()->create(['name' => 'Milik Orang Lain']);

        $this->assertSame(2, $this->service->paginateStocksFor($user)->total());
        $this->assertSame(1, $this->service->paginateStocksFor($user, 'kopi')->total());
        $this->assertSame(1, $this->service->paginateStocksFor($user, '', 'out_of_stock')->total());
        $this->assertCount(1, $this->service->paginateStocksFor($user, '', '', 1)->items());
    }
}