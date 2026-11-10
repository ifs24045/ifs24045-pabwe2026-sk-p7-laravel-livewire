<div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow p-8">
        <h1 class="text-2xl font-bold text-center mb-1">{{ config('app.name') }}</h1>
        <p class="text-center text-gray-600 mb-6">Silakan masuk ke akun Anda</p>

        <form wire:submit="authenticate" class="space-y-4">
            <div>
                <label for="login-username" class="block text-sm font-medium text-gray-700">Username atau Email</label>
                <input id="login-username" type="text" wire:model="username" autocomplete="username"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="login-password" class="block text-sm font-medium text-gray-700">Password</label>
                <input id="login-password" type="password" wire:model="password" autocomplete="current-password"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model="remember" class="rounded border-gray-300">
                Ingat saya
            </label>

            <button id="login-submit" type="submit"
                class="w-full rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">
                Masuk
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-700">
            Belum punya akun?
            <a href="{{ route('register') }}" wire:navigate class="text-blue-700 underline hover:text-blue-800">Daftar</a>
        </p>
    </div>
</div>