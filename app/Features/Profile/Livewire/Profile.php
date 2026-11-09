<?php

namespace App\Features\Profile\Livewire;

use App\Features\Profile\Services\ProfileService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class Profile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public $photo = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
    }

    public function updateProfile(ProfileService $service): void
    {
        $user = auth()->user();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'alpha_dash', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, strip, dan garis bawah.',
            'username.unique' => 'Username sudah dipakai.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
        ]);

        $service->updateProfile($user, $data);

        session()->flash('profile_status', 'Profil berhasil diperbarui.');
        $this->redirectRoute('profile');
    }

    public function updatePassword(ProfileService $service): void
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        if (! $service->updatePassword(auth()->user(), $this->current_password, $this->password)) {
            $this->addError('current_password', 'Password saat ini salah.');

            return;
        }

        $this->reset(['current_password', 'password', 'password_confirmation']);
        session()->flash('password_status', 'Password berhasil diubah.');
    }

    public function updatePhoto(ProfileService $service): void
    {
        $this->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'photo.required' => 'Pilih foto terlebih dahulu.',
            'photo.image' => 'File harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        $service->updatePhoto(auth()->user(), $this->photo);

        session()->flash('photo_status', 'Foto profil berhasil diperbarui.');
        $this->redirectRoute('profile');
    }

    public function removePhoto(ProfileService $service): void
    {
        $service->removePhoto(auth()->user());

        session()->flash('photo_status', 'Foto profil dihapus.');
        $this->redirectRoute('profile');
    }

    public function render()
    {
        return view('livewire.profile.profile');
    }
}