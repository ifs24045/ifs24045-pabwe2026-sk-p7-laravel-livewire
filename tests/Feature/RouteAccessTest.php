<?php

namespace Tests\Feature;

use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guest_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_root_redirects_member_to_stocks(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('stocks.index'));
    }

    public function test_root_redirects_admin_to_admin_users(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertRedirect(route('admin.users.index'));
    }

    public function test_guest_is_redirected_to_login_for_protected_pages(): void
    {
        $stock = Stock::factory()->create();

        foreach (['/stocks', "/stocks/{$stock->id}", '/profile', '/admin/users', '/admin/users/1/stocks'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
    }

    public function test_authenticated_user_cannot_open_login_or_register(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect();
        $this->actingAs($user)->get('/register')->assertRedirect();
    }

    public function test_member_can_open_stocks_and_profile_but_not_admin_pages(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/stocks')->assertOk();
        $this->actingAs($member)->get('/profile')->assertOk();
        $this->actingAs($member)->get('/admin/users')->assertForbidden();
        $this->actingAs($member)->get("/admin/users/{$member->id}/stocks")->assertForbidden();
    }

    public function test_admin_can_open_admin_pages_and_profile_but_not_member_stock_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $member->id]);

        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get("/admin/users/{$member->id}/stocks")->assertOk();
        $this->actingAs($admin)->get('/profile')->assertOk();
        $this->actingAs($admin)->get('/stocks')->assertForbidden();
        $this->actingAs($admin)->get("/stocks/{$stock->id}")->assertForbidden();
    }

    public function test_member_can_open_own_stock_detail_but_not_anothers(): void
    {
        $member = User::factory()->create();
        $own = Stock::factory()->create(['user_id' => $member->id]);
        $others = Stock::factory()->create();

        $this->actingAs($member)->get("/stocks/{$own->id}")->assertOk();
        $this->actingAs($member)->get("/stocks/{$others->id}")->assertForbidden();
        $this->actingAs($member)->get('/stocks/999999')->assertNotFound();
    }

    public function test_logout_ends_the_session_and_redirects_to_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }
}