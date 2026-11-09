<?php

namespace App\Features\Profile\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    private const PHOTO_DIRECTORY = 'profile-photos';

    public function updateProfile(User $user, array $data): User
    {
        $user->update(Arr::only($data, ['name', 'username', 'email']));

        return $user;
    }

    /**
     * Mengembalikan false jika password saat ini salah.
     */
    public function updatePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (! Hash::check($currentPassword, $user->password)) {
            return false;
        }

        $user->update(['password' => $newPassword]);

        return true;
    }

    public function updatePhoto(User $user, UploadedFile $photo): User
    {
        $this->deletePhotoFile($user);

        $user->update([
            'profile_photo_path' => $photo->store(self::PHOTO_DIRECTORY, 'public'),
        ]);

        return $user;
    }

    public function removePhoto(User $user): User
    {
        $this->deletePhotoFile($user);

        $user->update(['profile_photo_path' => null]);

        return $user;
    }

    private function deletePhotoFile(User $user): void
    {
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }
    }
}