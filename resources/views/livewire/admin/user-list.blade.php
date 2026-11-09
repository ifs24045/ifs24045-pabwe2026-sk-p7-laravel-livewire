<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Pengguna</h1>
        <p class="text-sm text-gray-500">Daftar pengguna dan ringkasan jumlah stok (hanya baca).</p>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-4 shadow">
            <p class="text-sm text-gray-500">Total pengguna</p>
            <p id="summary-users" class="text-2xl font-bold">{{ $summary['users'] }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow">
            <p class="text-sm text-gray-500">Member</p>
            <p id="summary-members" class="text-2xl font-bold">{{ $summary['members'] }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow">
            <p class="text-sm text-gray-500">Total item stok</p>
            <p id="summary-stocks" class="text-2xl font-bold">{{ $summary['stocks'] }}</p>
        </div>
    </div>

    <div class="mb-4">
        <input id="user-search" type="text" wire:model.live.debounce.300ms="search"
            placeholder="Cari nama, username, atau email..."
            class="w-full sm:max-w-sm rounded-lg border border-gray-300 bg-white px-3 py-2 focus:border-blue-500 focus:outline-none">
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Username</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Jumlah item</th>
                    <th class="px-4 py-3">Total unit</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $user->username }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $user->isAdmin() ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ ucfirst($user->role) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $user->stocks_count }}</td>
                        <td class="px-4 py-3">{{ (int) $user->stocks_sum_quantity }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @unless ($user->isAdmin())
                                <a href="{{ route('admin.users.stocks', $user) }}"
                                    class="text-blue-600 hover:underline">Lihat stok</a>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-500">Tidak ada pengguna yang cocok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>