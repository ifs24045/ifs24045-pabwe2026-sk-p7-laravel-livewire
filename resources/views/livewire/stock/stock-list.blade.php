<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">Daftar Stok</h1>
            <p class="text-sm text-gray-500">Kelola stok barang milik Anda.</p>
        </div>
                <div class="flex flex-wrap gap-2">
             <button id="stock-ai-button" type="button" wire:click="openAi"
                class="rounded-lg border border-purple-300 bg-purple-50 px-3 py-2 text-sm font-medium text-purple-700 hover:bg-purple-100">
                Generate AI
            </button>
            <button id="stock-template-button" type="button" wire:click="downloadTemplate"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                Template
            </button>
            <button id="stock-import-button" type="button" wire:click="openImport"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                Impor Excel
            </button>
            <button id="stock-export-button" type="button" wire:click="export"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                Ekspor Excel
            </button>
            <button id="stock-add-button" type="button" wire:click="openCreate"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                + Tambah Stok
            </button>
        </div>
    </div>

    @if (session('status'))
        <div id="stock-flash" class="mb-4 rounded-lg bg-green-100 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <input id="stock-search" type="text" wire:model.live.debounce.300ms="search"
            placeholder="Cari nama atau SKU..."
            class="w-full sm:max-w-xs rounded-lg border border-gray-300 bg-white px-3 py-2 focus:border-blue-500 focus:outline-none">

        <select id="stock-status-filter" wire:model.live="status"
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
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($stocks as $stock)
                    <tr wire:key="stock-{{ $stock->id }}">
                        <td class="px-4 py-3">
                            @if ($stock->cover_image_path)
                                <img src="{{ asset('storage/' . $stock->cover_image_path) }}" alt="{{ $stock->name }}"
                                    class="h-10 w-10 rounded object-cover">
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-200 text-xs text-gray-500">N/A</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium">
                             <a href="{{ route('stocks.show', $stock) }}" class="text-blue-700 hover:underline">{{ $stock->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $stock->sku ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="adjust({{ $stock->id }}, -1)"
                                    @disabled($stock->quantity <= 0)
                                    class="h-7 w-7 rounded border border-gray-300 text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40"
                                    aria-label="Kurangi jumlah">&minus;</button>
                                <span class="min-w-[4.5rem] text-center">{{ $stock->quantity }} {{ $stock->unit }}</span>
                                <button type="button" wire:click="adjust({{ $stock->id }}, 1)"
                                    class="h-7 w-7 rounded border border-gray-300 text-gray-700 hover:bg-gray-100"
                                    aria-label="Tambah jumlah">+</button>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            {{ $stock->price !== null ? 'Rp ' . number_format((float) $stock->price, 0, ',', '.') : '-' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $stock->status->badgeClass() }}">
                                {{ $stock->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" wire:click="openEdit({{ $stock->id }})"
                                class="text-blue-600 hover:underline">Ubah</button>
                            <button type="button" wire:click="confirmDelete({{ $stock->id }})"
                                class="ml-3 text-red-600 hover:underline">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                            Tidak ada stok yang cocok.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $stocks->links() }}
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-bold">{{ $editingId ? 'Ubah Stok' : 'Tambah Stok' }}</h2>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label for="stock-name" class="block text-sm font-medium text-gray-700">Nama barang</label>
                        <input id="stock-name" type="text" wire:model="name"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="stock-sku" class="block text-sm font-medium text-gray-700">SKU (opsional)</label>
                        <input id="stock-sku" type="text" wire:model="sku"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                        @error('sku') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="stock-description" class="block text-sm font-medium text-gray-700">Deskripsi (Markdown, opsional)</label>
                        <textarea id="stock-description" rows="3" wire:model="description"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm focus:border-blue-500 focus:outline-none"></textarea>
                        @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="stock-quantity" class="block text-sm font-medium text-gray-700">Jumlah</label>
                            <input id="stock-quantity" type="number" min="0" wire:model="quantity"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                            @error('quantity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="stock-unit" class="block text-sm font-medium text-gray-700">Satuan</label>
                            <input id="stock-unit" type="text" wire:model="unit"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                            @error('unit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="stock-price" class="block text-sm font-medium text-gray-700">Harga (Rp)</label>
                            <input id="stock-price" type="number" min="0" step="0.01" wire:model="price"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                            @error('price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="stock-min-stock" class="block text-sm font-medium text-gray-700">Stok minimum</label>
                            <input id="stock-min-stock" type="number" min="0" wire:model="minStock"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                            @error('minStock') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">
                        Status dihitung otomatis: habis jika jumlah 0, menipis jika jumlah &le; stok minimum.
                    </p>

                    <div class="flex justify-end gap-3 pt-2">
                        <button id="stock-cancel" type="button" wire:click="closeForm"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                            Batal
                        </button>
                        <button id="stock-save" type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($confirmingDeleteId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                <h2 class="mb-2 text-lg font-bold">Hapus stok?</h2>
                <p class="mb-6 text-sm text-gray-600">Data yang dihapus tidak dapat dikembalikan.</p>
                <div class="flex justify-end gap-3">
                    <button id="stock-delete-cancel" type="button" wire:click="cancelDelete"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                        Batal
                    </button>
                    <button id="stock-delete-confirm" type="button" wire:click="delete"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
        @if ($showImport)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
                <h2 class="mb-2 text-lg font-bold">Impor dari Excel</h2>
                <p class="mb-4 text-sm text-gray-600">
                    File .xlsx dengan kolom <code>name, sku, quantity, unit, price, min_stock, description</code>.
                    Kolom <code>status</code> diabaikan karena dihitung otomatis. SKU yang sudah ada dilewati.
                    Unduh <strong>Template</strong> untuk contoh format.
                </p>

                <form wire:submit="import" class="space-y-4">
                    <div>
                        <input id="import-file" type="file" wire:model="importFile" accept=".xlsx"
                            class="block w-full text-sm">
                        <div wire:loading wire:target="importFile" class="mt-1 text-sm text-gray-500">Mengunggah...</div>
                        @error('importFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($importResult)
                        <div id="import-result" class="rounded-lg bg-gray-50 p-3 text-sm">
                            <p class="font-semibold">
                                Berhasil: {{ $importResult['created'] }} &middot; Dilewati: {{ $importResult['skipped'] }}
                            </p>
                            @if (count($importResult['errors']) > 0)
                                <ul class="mt-2 list-disc space-y-1 pl-5 text-red-600">
                                    @foreach ($importResult['errors'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif

                    <div class="flex justify-end gap-3 pt-2">
                        <button id="import-close" type="button" wire:click="closeImport"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                            Tutup
                        </button>
                        <button id="import-submit" type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Impor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
        @if ($showAi)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
                <h2 class="mb-1 text-lg font-bold">Generate Stok dengan AI</h2>
                <p class="mb-4 text-sm text-gray-600">
                    Jelaskan jenis barang yang ingin dibuat. Hasilnya tampil sebagai preview, pilih yang ingin disimpan.
                </p>

                <form wire:submit="generateAi" class="space-y-4">
                    <div>
                        <label for="ai-prompt" class="block text-sm font-medium text-gray-700">Deskripsi</label>
                        <textarea id="ai-prompt" rows="3" wire:model="aiPrompt"
                            placeholder="Contoh: bahan makanan pokok untuk warung kecil"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none"></textarea>
                        @error('aiPrompt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-end gap-3">
                        <div class="w-32">
                            <label for="ai-count" class="block text-sm font-medium text-gray-700">Jumlah item</label>
                            <input id="ai-count" type="number" min="1" max="10" wire:model="aiCount"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                        </div>
                        <button id="ai-generate" type="submit" wire:loading.attr="disabled" wire:target="generateAi"
                            class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="generateAi">Generate</span>
                            <span wire:loading wire:target="generateAi">Memproses...</span>
                        </button>
                    </div>
                    @error('aiCount') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </form>

                @if ($aiError)
                    <div id="ai-error" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $aiError }}</div>
                @endif

                @if (count($aiItems) > 0)
                    <div class="mt-5">
                        <p class="mb-2 text-sm font-semibold">Preview ({{ count($aiItems) }} item)</p>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2"></th>
                                        <th class="px-3 py-2">Nama</th>
                                        <th class="px-3 py-2">SKU</th>
                                        <th class="px-3 py-2">Jumlah</th>
                                        <th class="px-3 py-2">Harga</th>
                                        <th class="px-3 py-2">Min</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($aiItems as $i => $item)
                                        <tr wire:key="ai-item-{{ $i }}">
                                            <td class="px-3 py-2">
                                                <input type="checkbox" wire:model="aiSelected.{{ $i }}" class="rounded border-gray-300">
                                            </td>
                                            <td class="px-3 py-2">
                                                <p class="font-medium">{{ $item['name'] }}</p>
                                                @if (! empty($item['description']))
                                                    <p class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($item['description'], 80) }}</p>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-gray-600">{{ $item['sku'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $item['quantity'] }} {{ $item['unit'] }}</td>
                                            <td class="px-3 py-2">
                                                {{ $item['price'] !== null ? 'Rp ' . number_format((float) $item['price'], 0, ',', '.') : '-' }}
                                            </td>
                                            <td class="px-3 py-2">{{ $item['min_stock'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @error('aiSelected') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="mt-6 flex justify-end gap-3">
                    <button id="ai-close" type="button" wire:click="closeAi"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                        Tutup
                    </button>
                    @if (count($aiItems) > 0)
                        <button id="ai-save" type="button" wire:click="saveAi"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Simpan yang dipilih
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>