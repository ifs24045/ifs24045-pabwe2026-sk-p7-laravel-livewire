<?php

namespace Tests\Feature\Admin;

use App\Features\Admin\Livewire\UserList;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserListTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $admin = User::factory()->admin()->create(['name' => 'Zed Admin']);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_it_lists_users_with_stock_counts_and_summary(): void
    {
        $admin = $this->actingAdmin();
        $ana = User::factory()->create(['name' => 'Ana Member', 'email' => 'ana@example.com']);
        Stock::factory()->count(3)->create(['user_id' => $ana->id, 'quantity' => 2]);

        Livewire::test(UserList::class)
            ->assertSee('Zed Admin')
            ->assertSee('Ana Member')
            ->assertSee('ana@example.com')
            ->assertSeeHtml('id="summary-users"')
            ->assertSeeHtml('id="summary-members"')
            ->assertSeeHtml('id="summary-stocks"')
            ->assertSee(route('admin.users.stocks', $ana), false)
            ->assertDontSee(route('admin.users.stocks', $admin), false);
    }

    public function test_it_searches_by_name_username_and_email(): void
    {
        $this->actingAdmin();
        User::factory()->create(['name' => 'Ana Member', 'username' => 'anaa', 'email' => 'ana@example.com']);
        User::factory()->create(['name' => 'Budi Member', 'username' => 'budi99', 'email' => 'budi@kantor.test']);

        Livewire::test(UserList::class)
            ->set('search', 'Ana')
            ->assertSee('Ana Member')
            ->assertDontSee('Budi Member');

        Livewire::test(UserList::class)
            ->set('search', 'budi99')
            ->assertSee('Budi Member')
            ->assertDontSee('Ana Member');

        Livewire::test(UserList::class)
            ->set('search', 'kantor.test')
            ->assertSee('Budi Member');
    }

    public function test_it_shows_empty_state_when_nothing_matches(): void
    {
        $this->actingAdmin();

        Livewire::test(UserList::class)
            ->set('search', 'tidak-ada-yang-cocok')
            ->assertSee('Tidak ada pengguna yang cocok.');
    }

    public function test_it_paginates_ten_users_per_page(): void
    {
        $this->actingAdmin();
        foreach (range(1, 11) as $i) {
            User::factory()->create(['name' => sprintf('Anggota %02d', $i)]);
        }

        Livewire::test(UserList::class)
            ->assertSee('Zed Admin')
            ->assertSee('Anggota 09')
            ->assertDontSee('Anggota 10')
            ->assertDontSee('Anggota 11');
    }
}