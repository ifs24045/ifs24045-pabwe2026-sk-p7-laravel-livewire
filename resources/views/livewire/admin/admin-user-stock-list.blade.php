<div>
    <a href="{{ route('admin.users.index') }}" class="mb-4 inline-block text-sm text-blue-600 hover:underline">
        &larr; Kembali ke daftar pengguna
    </a>

    <div class="mb-6">
        <h1 class="text-2xl font-bold">Stok milik {{ $user->name }}</h1>
        <p class="text-sm text-gray-500">{{ '@' . $user->username }} &middot; tampilan hanya baca.</p>
    </div>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <input id="admin-stock-search" type="text" wire:model.live.debounce.300ms="search"
            placeholder="Cari nama atau SKU..."
            class="w-full sm:max-w-xs rounded-lg border border-gray-300 bg-white px-3 py-2 focus:border-blue-500 focus:outline-none">

        <select id="admin-stock-status" wire:model.live="status"
            class="w-full sm:w-48 rounded-lg border border-gray-300 bg-white px-3 py-2 focus:border-blue-500 focus:outline-none">
            <option value="">Semua status</option>
            @foreach ($statuses as $item)
                <option value="{{ $item->value }}">{{ $item->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Cover</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">SKU</th>
                    <th class="px-4 py-3">Jumlah</th>
                    <th class="px-4 py-3">Harga</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($stocks as $stock)
                    <tr wire:key="admin-stock-{{ $stock->id }}">
                        <td class="px-4 py-3">
                            @if ($stock->cover_image_path)
                                <img src="{{ asset('storage/' . $stock->cover_image_path) }}" alt="{{ $stock->name }}"
                                    class="h-10 w-10 rounded object-cover">
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-200 text-xs text-gray-500">N/A</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium">{{ $stock->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $stock->sku ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $stock->quantity }} {{ $stock->unit }}</td>
                        <td class="px-4 py-3">
                            {{ $stock->price !== null ? 'Rp ' . number_format((float) $stock->price, 0, ',', '.') : '-' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $stock->status->badgeClass() }}">
                                {{ $stock->status->label() }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-500">Tidak ada stok yang cocok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $stocks->links() }}
    </div>
</div>