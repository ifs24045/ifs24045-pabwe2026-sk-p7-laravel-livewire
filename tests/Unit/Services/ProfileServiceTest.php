<?php

namespace Tests\Unit\Services;

use App\Features\Profile\Services\ProfileService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->service = new ProfileService();
    }

    public function test_update_profile_changes_only_allowed_fields(): void
    {
        $user = User::factory()->create();

        $updated = $this->service->updateProfile($user, [
            'name' => 'Nama Baru',
            'username' => 'username_baru',
            'email' => 'baru@example.com',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->assertSame('Nama Baru', $updated->name);
        $this->assertSame('username_baru', $updated->username);
        $this->assertSame('baru@example.com', $updated->email);
        $this->assertSame(User::ROLE_MEMBER, $updated->role);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => 'username_baru', 'role' => 'member']);
    }

    public function test_update_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'lama12345']);

        $this->assertFalse($this->service->updatePassword($user, 'salah', 'baru12345'));
        $this->assertTrue(Hash::check('lama12345', $user->fresh()->password));
    }

    public function test_update_password_changes_password_when_current_is_correct(): void
    {
        $user = User::factory()->create(['password' => 'lama12345']);

        $this->assertTrue($this->service->updatePassword($user, 'lama12345', 'baru12345'));
        $this->assertTrue(Hash::check('baru12345', $user->fresh()->password));
    }

    public function test_update_photo_stores_the_file(): void
    {
        $user = User::factory()->create();

        $updated = $this->service->updatePhoto($user, UploadedFile::fake()->image('foto.jpg'));

        $this->assertStringStartsWith('profile-photos/', $updated->profile_photo_path);
        Storage::disk('public')->assertExists($updated->profile_photo_path);
    }

    public function test_update_photo_deletes_the_previous_file(): void
    {
        $user = User::factory()->create();
        $first = $this->service->updatePhoto($user, UploadedFile::fake()->image('a.jpg'))->profile_photo_path;

        $second = $this->service->updatePhoto($user, UploadedFile::fake()->image('b.jpg'))->profile_photo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_remove_photo_deletes_file_and_clears_path(): void
    {
        $user = User::factory()->create();
        $path = $this->service->updatePhoto($user, UploadedFile::fake()->image('a.jpg'))->profile_photo_path;

        $updated = $this->service->removePhoto($user);

        $this->assertNull($updated->profile_photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_remove_photo_without_photo_is_harmless(): void
    {
        $user = User::factory()->create(['profile_photo_path' => null]);

        $this->assertNull($this->service->removePhoto($user)->profile_photo_path);
    }
}