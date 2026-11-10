<main class="min-h-screen flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-md bg-white rounded-xl shadow p-8">
        <h1 class="text-2xl font-bold text-center mb-1">Daftar Akun</h1>
        <p class="text-center text-gray-600 mb-6">Buat akun member baru</p>

        <form wire:submit="register" class="space-y-4">
            <div>
                <label for="register-name" class="block text-sm font-medium text-gray-700">Nama</label>
                <input id="register-name" type="text" wire:model="name" autocomplete="name"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="register-username" class="block text-sm font-medium text-gray-700">Username</label>
                <input id="register-username" type="text" wire:model="username" autocomplete="username"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="register-email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="register-email" type="email" wire:model="email" autocomplete="email"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="register-password" class="block text-sm font-medium text-gray-700">Password</label>
                <input id="register-password" type="password" wire:model="password" autocomplete="new-password"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="register-password-confirmation" class="block text-sm font-medium text-gray-700">Konfirmasi Password</label>
                <input id="register-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
            </div>

            <button id="register-submit" type="submit"
                class="w-full rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">
                Daftar
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-700">
            Sudah punya akun?
            <a href="{{ route('login') }}" wire:navigate class="text-blue-700 underline hover:text-blue-800">Masuk</a>
        </p>
    </div>
</main>