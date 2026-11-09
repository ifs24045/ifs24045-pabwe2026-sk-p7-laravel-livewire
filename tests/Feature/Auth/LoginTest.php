<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Livewire\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_required_form_ids(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('id="login-username"', false)
            ->assertSee('id="login-password"', false)
            ->assertSee('id="login-submit"', false);
    }

    public function test_member_is_redirected_to_stocks_after_login(): void
    {
        User::factory()->create(['username' => 'member', 'password' => 'member']);

        Livewire::test(Login::class)
            ->set('username', 'member')
            ->set('password', 'member')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(route('stocks.index'));

        $this->assertAuthenticated();
    }

    public function test_admin_is_redirected_to_admin_users_after_login(): void
    {
        User::factory()->admin()->create(['username' => 'admin', 'password' => 'admin']);

        Livewire::test(Login::class)
            ->set('username', 'admin')
            ->set('password', 'admin')
            ->call('authenticate')
            ->assertRedirect(route('admin.users.index'));
    }

    public function test_login_with_email_and_remember_me(): void
    {
        User::factory()->create(['email' => 'budi@example.com', 'password' => 'rahasia123']);

        Livewire::test(Login::class)
            ->set('username', 'budi@example.com')
            ->set('password', 'rahasia123')
            ->set('remember', true)
            ->call('authenticate')
            ->assertRedirect(route('stocks.index'));
    }

    public function test_wrong_credentials_show_error_and_do_not_authenticate(): void
    {
        User::factory()->create(['username' => 'member', 'password' => 'member']);

        Livewire::test(Login::class)
            ->set('username', 'member')
            ->set('password', 'salah')
            ->call('authenticate')
            ->assertHasErrors('username')
            ->assertSee('Username/email atau password salah.')
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_both_fields_are_required(): void
    {
        Livewire::test(Login::class)
            ->call('authenticate')
            ->assertHasErrors(['username' => 'required', 'password' => 'required']);
    }
}