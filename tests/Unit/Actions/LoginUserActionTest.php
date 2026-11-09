<?php

namespace Tests\Unit\Actions;

use App\Features\Auth\Actions\LoginUserAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_username(): void
    {
        User::factory()->create(['username' => 'budi', 'password' => 'rahasia123']);

        $this->assertTrue((new LoginUserAction())->execute('budi', 'rahasia123'));
        $this->assertAuthenticated();
    }

    public function test_login_with_email(): void
    {
        User::factory()->create(['email' => 'budi@example.com', 'password' => 'rahasia123']);

        $this->assertTrue((new LoginUserAction())->execute('budi@example.com', 'rahasia123'));
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['username' => 'budi', 'password' => 'rahasia123']);

        $this->assertFalse((new LoginUserAction())->execute('budi', 'salah'));
        $this->assertGuest();
    }

    public function test_login_fails_for_unknown_user(): void
    {
        $this->assertFalse((new LoginUserAction())->execute('tidak-ada', 'apa-saja'));
        $this->assertGuest();
    }

    public function test_login_with_remember_flag(): void
    {
        User::factory()->create(['username' => 'budi', 'password' => 'rahasia123']);

        $this->assertTrue((new LoginUserAction())->execute('budi', 'rahasia123', true));
    }
}