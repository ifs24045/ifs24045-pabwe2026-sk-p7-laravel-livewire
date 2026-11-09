<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Livewire\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function fillValid(\Livewire\Features\SupportTesting\Testable $component): \Livewire\Features\SupportTesting\Testable
    {
        return $component
            ->set('name', 'Budi Santoso')
            ->set('username', 'budi_s')
            ->set('email', 'budi@example.com')
            ->set('password', 'rahasia123')
            ->set('password_confirmation', 'rahasia123');
    }

    public function test_register_page_renders_form_ids(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('id="register-username"', false)
            ->assertSee('id="register-submit"', false);
    }

    public function test_valid_registration_creates_member_and_redirects(): void
    {
        $this->fillValid(Livewire::test(Register::class))
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('stocks.index'));

        $this->assertDatabaseHas('users', ['username' => 'budi_s', 'email' => 'budi@example.com', 'role' => 'member']);
        $this->assertAuthenticated();
    }

    public function test_all_required_fields_are_validated(): void
    {
        Livewire::test(Register::class)
            ->call('register')
            ->assertHasErrors(['name' => 'required', 'username' => 'required', 'email' => 'required', 'password' => 'required']);
    }

    public function test_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'budi_s']);

        $this->fillValid(Livewire::test(Register::class))
            ->call('register')
            ->assertHasErrors(['username' => 'unique']);
    }

    public function test_email_must_be_unique_and_valid(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $this->fillValid(Livewire::test(Register::class))
            ->call('register')
            ->assertHasErrors(['email' => 'unique']);

        $this->fillValid(Livewire::test(Register::class))
            ->set('email', 'bukan-email')
            ->call('register')
            ->assertHasErrors(['email' => 'email']);
    }

    public function test_username_only_allows_alpha_dash_characters(): void
    {
        $this->fillValid(Livewire::test(Register::class))
            ->set('username', 'budi s!')
            ->call('register')
            ->assertHasErrors(['username' => 'alpha_dash']);
    }

    public function test_password_must_be_confirmed_and_long_enough(): void
    {
        $this->fillValid(Livewire::test(Register::class))
            ->set('password_confirmation', 'beda12345')
            ->call('register')
            ->assertHasErrors(['password' => 'confirmed']);

        $this->fillValid(Livewire::test(Register::class))
            ->set('password', 'pendek')
            ->set('password_confirmation', 'pendek')
            ->call('register')
            ->assertHasErrors(['password' => 'min']);
    }
}