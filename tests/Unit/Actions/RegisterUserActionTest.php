<?php

namespace Tests\Unit\Actions;

use App\Features\Auth\Actions\RegisterUserAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_member_and_logs_in(): void
    {
        $user = (new RegisterUserAction())->execute([
            'name' => 'Budi Santoso',
            'username' => 'budi_s',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
        ]);

        $this->assertSame(User::ROLE_MEMBER, $user->role);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['username' => 'budi_s', 'email' => 'budi@example.com', 'role' => 'member']);
    }

    public function test_register_ignores_role_supplied_in_input(): void
    {
        $user = (new RegisterUserAction())->execute([
            'name' => 'Penyusup',
            'username' => 'penyusup',
            'email' => 'penyusup@example.com',
            'password' => 'rahasia123',
            'role' => 'admin',
        ]);

        $this->assertSame(User::ROLE_MEMBER, $user->role);
        $this->assertFalse($user->isAdmin());
    }
}