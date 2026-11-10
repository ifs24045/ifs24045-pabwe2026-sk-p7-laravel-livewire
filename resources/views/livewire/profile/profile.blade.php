<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Profil</h1>
        <p class="text-sm text-gray-600">Kelola data akun, password, dan foto profil Anda.</p>
    </div>

    <section class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-semibold">Foto Profil</h2>

        @if (session('photo_status'))
            <div id="photo-flash" class="mb-4 rounded-lg bg-green-100 px-4 py-3 text-sm text-green-800">
                {{ session('photo_status') }}
            </div>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            @if ($photo && $photo->isPreviewable())
                <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau foto" class="h-20 w-20 rounded-full object-cover">
            @elseif (auth()->user()->profile_photo_path)
                <img src="{{ asset('storage/' . auth()->user()->profile_photo_path) }}" alt="Foto profil"
                    class="h-20 w-20 rounded-full object-cover">
            @else
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-2xl font-bold text-blue-700">
                    {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                </div>
            @endif

            <form wire:submit="updatePhoto" class="flex-1 space-y-2">
                <label for="profile-photo" class="block text-sm font-medium text-gray-700 mb-1">Pilih foto profil</label>
                <input id="profile-photo" type="file" wire:model="photo" accept="image/png,image/jpeg,image/webp"
                    class="block w-full text-sm">
                <div wire:loading wire:target="photo" class="text-sm text-gray-500">Mengunggah...</div>
                @error('photo') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <div class="flex gap-2">
                    <button id="profile-photo-save" type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Simpan Foto
                    </button>
                    @if (auth()->user()->profile_photo_path)
                        <button id="profile-photo-remove" type="button" wire:click="removePhoto"
                            wire:confirm="Hapus foto profil?"
                            class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                            Hapus Foto
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </section>

    <section class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-semibold">Data Akun</h2>

        @if (session('profile_status'))
            <div id="profile-flash" class="mb-4 rounded-lg bg-green-100 px-4 py-3 text-sm text-green-800">
                {{ session('profile_status') }}
            </div>
        @endif

        <form wire:submit="updateProfile" class="space-y-4">
            <div>
                <label for="profile-name" class="block text-sm font-medium text-gray-700">Nama</label>
                <input id="profile-name" type="text" wire:model="name"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="profile-username" class="block text-sm font-medium text-gray-700">Username</label>
                <input id="profile-username" type="text" wire:model="username"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="profile-email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="profile-email" type="email" wire:model="email"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <button id="profile-save" type="submit"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Simpan Perubahan
            </button>
        </form>
    </section>

    <section class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-semibold">Ubah Password</h2>

        @if (session('password_status'))
            <div id="password-flash" class="mb-4 rounded-lg bg-green-100 px-4 py-3 text-sm text-green-800">
                {{ session('password_status') }}
            </div>
        @endif

        <form wire:submit="updatePassword" class="space-y-4">
            <div>
                <label for="profile-current-password" class="block text-sm font-medium text-gray-700">Password saat ini</label>
                <input id="profile-current-password" type="password" wire:model="current_password" autocomplete="current-password"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="profile-password" class="block text-sm font-medium text-gray-700">Password baru</label>
                <input id="profile-password" type="password" wire:model="password" autocomplete="new-password"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="profile-password-confirmation" class="block text-sm font-medium text-gray-700">Konfirmasi password baru</label>
                <input id="profile-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
            </div>

            <button id="profile-password-save" type="submit"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Ubah Password
            </button>
        </form>
    </section>
</div>