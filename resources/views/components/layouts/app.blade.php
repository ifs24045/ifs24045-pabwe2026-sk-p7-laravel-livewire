{{-- resources/views/components/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900 antialiased">
    @auth
        <div class="min-h-screen md:flex">
            <aside class="bg-white border-b md:border-b-0 md:border-r md:w-64 md:min-h-screen flex flex-col">
                <div class="px-6 py-5 border-b">
                    <p class="text-lg font-bold">{{ config('app.name') }}</p>
                    <p class="text-sm text-gray-600">
                        {{ auth()->user()->name }} &middot; {{ ucfirst(auth()->user()->role) }}
                    </p>
                </div>

                <nav class="p-3 space-y-1 flex-1">
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.users.index') }}"
                            class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                            Pengguna
                        </a>
                    @else
                        <a href="{{ route('stocks.index') }}"
                            class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('stocks.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                            Stok Barang
                        </a>
                    @endif

                    @if (Route::has('profile'))
                        <a href="{{ route('profile') }}"
                            class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('profile') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                            Profil
                        </a>
                    @endif
                </nav>

                <form method="POST" action="{{ route('logout') }}" class="p-3 border-t">
                    @csrf
                    <button id="logout-button" type="submit"
                        class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-red-600 hover:bg-red-50">
                        Keluar
                    </button>
                </form>
            </aside>

            <main class="flex-1 p-4 md:p-8">
                {{ $slot }}
            </main>
        </div>
    @else
            {{ $slot }}
    @endauth
</body>
</html>