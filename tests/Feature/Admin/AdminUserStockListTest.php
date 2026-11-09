<?php

namespace Tests\Feature\Admin;

use App\Features\Admin\Livewire\AdminUserStockList;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserStockListTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        return $admin;
    }

    public function test_it_shows_only_the_target_users_stocks(): void
    {
        $this->actingAdmin();
        $member = User::factory()->create(['name' => 'Ana Member', 'username' => 'ana']);
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Kopi Milik Ana']);
        Stock::factory()->create(['name' => 'Teh Orang Lain']);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->assertSee('Stok milik Ana Member')
            ->assertSee('Kopi Milik Ana')
            ->assertDontSee('Teh Orang Lain');
    }

    public function test_it_is_read_only(): void
    {
        $this->actingAdmin();
        $member = User::factory()->create();
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Kopi']);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->assertSee('Kopi')
            ->assertDontSee('Ubah')
            ->assertDontSee('Hapus')
            ->assertDontSee('Tambah Stok');
    }

    public function test_it_searches_and_filters_by_status(): void
    {
        $this->actingAdmin();
        $member = User::factory()->create();
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Kopi Bubuk', 'quantity' => 0, 'status' => 'out_of_stock']);
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Teh Celup', 'quantity' => 99, 'min_stock' => 5, 'status' => 'available']);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->set('search', 'kopi')
            ->assertSee('Kopi Bubuk')
            ->assertDontSee('Teh Celup');

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->set('status', 'available')
            ->assertSee('Teh Celup')
            ->assertDontSee('Kopi Bubuk');
    }

    public function test_unknown_status_filter_is_ignored(): void
    {
        $this->actingAdmin();
        $member = User::factory()->create();
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Barang Satu']);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->set('status', 'ngawur')
            ->assertSee('Barang Satu');
    }

    public function test_it_shows_empty_state(): void
    {
        $this->actingAdmin();
        $member = User::factory()->create();

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->assertSee('Tidak ada stok yang cocok.');
    }

    public function test_it_paginates_ten_stocks_per_page(): void
    {
        $this->actingAdmin();
        $member = User::factory()->create();
        foreach (range(1, 11) as $i) {
            Stock::factory()->create(['user_id' => $member->id, 'name' => sprintf('Barang %02d', $i)]);
        }

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->assertSee('Barang 11')
            ->assertSee('Barang 02')
            ->assertDontSee('Barang 01');
    }

    public function test_an_admin_account_cannot_be_inspected(): void
    {
        $admin = $this->actingAdmin();

        Livewire::test(AdminUserStockList::class, ['user' => $admin])->assertNotFound();
    }
}