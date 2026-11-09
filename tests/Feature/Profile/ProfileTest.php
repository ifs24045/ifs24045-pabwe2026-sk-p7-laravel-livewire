<?php

namespace Tests\Feature\Profile;

use App\Features\Profile\Livewire\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    private function actingMember(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->actingAs($user);

        return $user;
    }

    // ---------- Tampilan ----------

    public function test_form_is_filled_from_the_authenticated_user(): void
    {
        $this->actingMember(['name' => 'Budi', 'username' => 'budi', 'email' => 'budi@example.com']);

        Livewire::test(Profile::class)
            ->assertSet('name', 'Budi')
            ->assertSet('username', 'budi')
            ->assertSet('email', 'budi@example.com')
            ->assertSet('current_password', '')
            ->assertSet('password', '');
    }

    public function test_admin_can_open_the_profile_component(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Profile::class)->assertOk();
    }

    public function test_photo_remove_button_is_only_shown_when_a_photo_exists(): void
    {
        $this->actingMember(['profile_photo_path' => null]);

        Livewire::test(Profile::class)->assertDontSee('Hapus Foto');
    }

    public function test_existing_photo_is_displayed_with_remove_button(): void
    {
        $this->actingMember(['profile_photo_path' => 'profile-photos/saya.jpg']);

        Livewire::test(Profile::class)
            ->assertSee('storage/profile-photos/saya.jpg', false)
            ->assertSee('Hapus Foto');
    }

    // ---------- Data akun ----------

    public function test_valid_update_saves_data_and_redirects(): void
    {
        $user = $this->actingMember(['name' => 'Lama', 'username' => 'lama', 'email' => 'lama@example.com']);

        Livewire::test(Profile::class)
            ->set('name', 'Nama Baru')
            ->set('username', 'nama_baru')
            ->set('email', 'baru@example.com')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertRedirect(route('profile'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id, 'name' => 'Nama Baru', 'username' => 'nama_baru',
            'email' => 'baru@example.com', 'role' => 'member',
        ]);
        $this->assertSame('Profil berhasil diperbarui.', session('profile_status'));
    }

    public function test_keeping_own_username_and_email_is_allowed(): void
    {
        $this->actingMember(['username' => 'budi', 'email' => 'budi@example.com']);

        Livewire::test(Profile::class)
            ->set('name', 'Nama Lain')
            ->call('updateProfile')
            ->assertHasNoErrors();
    }

    public function test_username_and_email_must_be_unique_among_other_users(): void
    {
        User::factory()->create(['username' => 'dipakai', 'email' => 'dipakai@example.com']);
        $this->actingMember();

        Livewire::test(Profile::class)
            ->set('username', 'dipakai')
            ->set('email', 'dipakai@example.com')
            ->call('updateProfile')
            ->assertHasErrors(['username' => 'unique', 'email' => 'unique']);
    }

    public function test_profile_form_is_validated(): void
    {
        $this->actingMember();

        Livewire::test(Profile::class)
            ->set('name', '')
            ->set('username', '')
            ->set('email', '')
            ->call('updateProfile')
            ->assertHasErrors(['name' => 'required', 'username' => 'required', 'email' => 'required']);

        Livewire::test(Profile::class)
            ->set('username', 'ada spasi!')
            ->set('email', 'bukan-email')
            ->call('updateProfile')
            ->assertHasErrors(['username' => 'alpha_dash', 'email' => 'email']);
    }

    // ---------- Password ----------

    public function test_password_can_be_changed_with_the_correct_current_password(): void
    {
        $user = $this->actingMember(['password' => 'lama12345']);

        Livewire::test(Profile::class)
            ->set('current_password', 'lama12345')
            ->set('password', 'baru12345')
            ->set('password_confirmation', 'baru12345')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSet('current_password', '')
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '')
            ->assertSee('Password berhasil diubah.');

        $this->assertTrue(Hash::check('baru12345', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->actingMember(['password' => 'lama12345']);

        Livewire::test(Profile::class)
            ->set('current_password', 'salah-banget')
            ->set('password', 'baru12345')
            ->set('password_confirmation', 'baru12345')
            ->call('updatePassword')
            ->assertHasErrors('current_password')
            ->assertSee('Password saat ini salah.');

        $this->assertTrue(Hash::check('lama12345', $user->fresh()->password));
    }

    public function test_password_form_is_validated(): void
    {
        $this->actingMember(['password' => 'lama12345']);

        Livewire::test(Profile::class)
            ->call('updatePassword')
            ->assertHasErrors(['current_password' => 'required', 'password' => 'required']);

        Livewire::test(Profile::class)
            ->set('current_password', 'lama12345')
            ->set('password', 'pendek')
            ->set('password_confirmation', 'pendek')
            ->call('updatePassword')
            ->assertHasErrors(['password' => 'min']);

        Livewire::test(Profile::class)
            ->set('current_password', 'lama12345')
            ->set('password', 'baru12345')
            ->set('password_confirmation', 'beda12345')
            ->call('updatePassword')
            ->assertHasErrors(['password' => 'confirmed']);
    }

    // ---------- Foto ----------

    public function test_photo_can_be_uploaded(): void
    {
        $user = $this->actingMember();

        Livewire::test(Profile::class)
            ->set('photo', UploadedFile::fake()->image('foto.jpg'))
            ->call('updatePhoto')
            ->assertHasNoErrors()
            ->assertRedirect(route('profile'));

        $path = $user->fresh()->profile_photo_path;

        $this->assertStringStartsWith('profile-photos/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('Foto profil berhasil diperbarui.', session('photo_status'));
    }

    public function test_uploading_a_new_photo_deletes_the_previous_one(): void
    {
        Storage::disk('public')->put('profile-photos/lama.jpg', 'x');
        $user = $this->actingMember(['profile_photo_path' => 'profile-photos/lama.jpg']);

        Livewire::test(Profile::class)
            ->set('photo', UploadedFile::fake()->image('baru.png'))
            ->call('updatePhoto')
            ->assertHasNoErrors();

        Storage::disk('public')->assertMissing('profile-photos/lama.jpg');
        Storage::disk('public')->assertExists($user->fresh()->profile_photo_path);
    }

    public function test_photo_is_validated(): void
    {
        $this->actingMember();

        Livewire::test(Profile::class)
            ->call('updatePhoto')
            ->assertHasErrors(['photo' => 'required']);

        Livewire::test(Profile::class)
            ->set('photo', UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf'))
            ->call('updatePhoto')
            ->assertHasErrors(['photo' => 'image']);

        Livewire::test(Profile::class)
            ->set('photo', UploadedFile::fake()->image('animasi.gif'))
            ->call('updatePhoto')
            ->assertHasErrors(['photo' => 'mimes']);

        Livewire::test(Profile::class)
            ->set('photo', UploadedFile::fake()->image('besar.jpg')->size(3000))
            ->call('updatePhoto')
            ->assertHasErrors(['photo' => 'max']);
    }

    public function test_photo_can_be_removed(): void
    {
        Storage::disk('public')->put('profile-photos/saya.jpg', 'x');
        $user = $this->actingMember(['profile_photo_path' => 'profile-photos/saya.jpg']);

        Livewire::test(Profile::class)
            ->call('removePhoto')
            ->assertRedirect(route('profile'));

        $this->assertNull($user->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing('profile-photos/saya.jpg');
        $this->assertSame('Foto profil dihapus.', session('photo_status'));
    }
}