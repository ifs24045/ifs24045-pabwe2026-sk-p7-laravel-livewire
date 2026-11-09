<?php

namespace Tests\Unit\Models;

use App\Features\Stock\Models\Stock;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_admin_reflects_role(): void
    {
        $this->assertTrue(User::factory()->admin()->make()->isAdmin());
        $this->assertFalse(User::factory()->make()->isAdmin());
    }

    public function test_factory_defaults_to_member_role(): void
    {
        $user = User::factory()->create();

        $this->assertSame(User::ROLE_MEMBER, $user->role);
        $this->assertNull($user->profile_photo_path);
    }

    public function test_password_is_hashed_automatically(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_hidden_attributes_are_not_serialized(): void
    {
        $array = User::factory()->create()->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    public function test_email_verified_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(CarbonInterface::class, $user->email_verified_at);
    }

    public function test_user_has_many_stocks(): void
    {
        $user = User::factory()->create();
        Stock::factory()->count(3)->create(['user_id' => $user->id]);
        Stock::factory()->create();

        $this->assertInstanceOf(HasMany::class, $user->stocks());
        $this->assertCount(3, $user->stocks);
    }
}