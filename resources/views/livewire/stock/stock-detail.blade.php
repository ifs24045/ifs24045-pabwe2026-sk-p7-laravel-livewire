<div>
    <a href="{{ route('stocks.index') }}" class="mb-4 inline-block text-sm text-blue-600 hover:underline">
        &larr; Kembali ke daftar
    </a>

    @if (session('status'))
        <div id="stock-flash" class="mb-4 rounded-lg bg-green-100 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-xl bg-white p-6 shadow">
        @if (! $editing)
            <div class="flex flex-col gap-6 md:flex-row">
                <div class="md:w-64 md:shrink-0">
                    @if ($stock->cover_image_path)
                        <img src="{{ asset('storage/' . $stock->cover_image_path) }}" alt="{{ $stock->name }}"
                            class="w-full rounded-lg object-cover">
                    @else
                        <div class="flex h-48 w-full items-center justify-center rounded-lg bg-gray-200 text-sm text-gray-600">
                            Belum ada cover
                        </div>
                    @endif
                </div>

                <div class="flex-1 space-y-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold">{{ $stock->name }}</h1>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $stock->status->badgeClass() }}">
                            {{ $stock->status->label() }}
                        </span>
                    </div>

                    <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-gray-500">SKU</dt>
                            <dd class="font-medium">{{ $stock->sku ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Harga</dt>
                            <dd class="font-medium">
                                {{ $stock->price !== null ? 'Rp ' . number_format((float) $stock->price, 0, ',', '.') : '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Stok minimum</dt>
                            <dd class="font-medium">{{ $stock->min_stock }} {{ $stock->unit }}</dd>
                        </div>
                    </dl>

                    <div>
                        <p class="mb-1 text-sm text-gray-500">Jumlah</p>
                        <div class="flex items-center gap-3">
                            <button id="detail-decrease" type="button" wire:click="adjust(-1)"
                                @disabled($stock->quantity <= 0)
                                class="h-9 w-9 rounded border border-gray-300 text-lg hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40"
                                aria-label="Kurangi jumlah">&minus;</button>
                            <span id="detail-quantity" class="min-w-[6rem] text-center text-xl font-semibold">
                                {{ $stock->quantity }} {{ $stock->unit }}
                            </span>
                            <button id="detail-increase" type="button" wire:click="adjust(1)"
                                class="h-9 w-9 rounded border border-gray-300 text-lg hover:bg-gray-100"
                                aria-label="Tambah jumlah">+</button>
                        </div>
                    </div>

                    <div>
                        <p class="mb-1 text-sm text-gray-500">Deskripsi</p>
                        @if ($stock->description)
                            <div id="detail-description" class="markdown-body text-sm">
                                {!! \Illuminate\Support\Str::markdown($stock->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>
                        @else
                            <p class="text-sm text-gray-400">Belum ada deskripsi.</p>
                        @endif
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button id="detail-edit" type="button" wire:click="startEdit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Ubah
                        </button>
                        <button id="detail-delete" type="button" wire:click="delete"
                            wire:confirm="Hapus stok ini? Data tidak dapat dikembalikan."
                            class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        @else
            <h2 class="mb-4 text-lg font-bold">Ubah Stok</h2>

            <form wire:submit="save" class="space-y-4">
                <div>
                    <label for="detail-name" class="block text-sm font-medium text-gray-700">Nama barang</label>
                    <input id="detail-name" type="text" wire:model="name"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="col-span-2">
                        <label for="detail-sku" class="block text-sm font-medium text-gray-700">SKU</label>
                        <input id="detail-sku" type="text" wire:model="sku"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                        @error('sku') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="detail-quantity-input" class="block text-sm font-medium text-gray-700">Jumlah</label>
                        <input id="detail-quantity-input" type="number" min="0" wire:model="quantity"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                        @error('quantity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="detail-unit" class="block text-sm font-medium text-gray-700">Satuan</label>
                        <input id="detail-unit" type="text" wire:model="unit"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                        @error('unit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="col-span-2">
                        <label for="detail-price" class="block text-sm font-medium text-gray-700">Harga (Rp)</label>
                        <input id="detail-price" type="number" min="0" step="0.01" wire:model="price"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                        @error('price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="col-span-2">
                        <label for="detail-min-stock" class="block text-sm font-medium text-gray-700">Stok minimum</label>
                        <input id="detail-min-stock" type="number" min="0" wire:model="minStock"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                        @error('minStock') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <p class="mb-1 text-sm font-medium text-gray-700">Deskripsi (Markdown)</p>

                    <div x-data="{ ready: false }">
                        <div wire:ignore>
                            <div x-init="
                                try {
                                    const editor = new window.ToastEditor({
                                        el: $el,
                                        initialValue: $wire.description ?? '',
                                        height: '300px',
                                        initialEditType: 'markdown',
                                        previewStyle: 'tab',
                                        usageStatistics: false,
                                        toolbarItems: [
                                            ['heading', 'bold', 'italic', 'strike'],
                                            ['ul', 'ol', 'task'],
                                            ['link', 'code', 'codeblock'],
                                        ],
                                    });
                                    editor.on('change', () => { $wire.description = editor.getMarkdown(); });
                                    ready = true;
                                } catch (e) {
                                    console.error('Toast UI gagal dimuat:', e);
                                }
                            "></div>
                        </div>

                        <textarea id="detail-description-input" rows="6" wire:model="description" x-show="!ready"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm focus:border-blue-500 focus:outline-none"></textarea>
                    </div>

                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="detail-cover" class="block text-sm font-medium text-gray-700">Cover (JPG/PNG/WEBP, maks 2 MB)</label>
                    <input id="detail-cover" type="file" wire:model="cover" accept="image/png,image/jpeg,image/webp"
                        class="mt-1 block w-full text-sm">
                    <div wire:loading wire:target="cover" class="mt-1 text-sm text-gray-500">Mengunggah...</div>
                    @error('cover') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    @if ($cover && $cover->isPreviewable())
                        <img src="{{ $cover->temporaryUrl() }}" alt="Pratinjau cover" class="mt-3 h-32 rounded-lg object-cover">
                    @elseif ($stock->cover_image_path)
                        <img src="{{ asset('storage/' . $stock->cover_image_path) }}" alt="Cover saat ini"
                            class="mt-3 h-32 rounded-lg object-cover">
                        <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                            <input id="detail-remove-cover" type="checkbox" wire:model="removeCover" class="rounded border-gray-300">
                            Hapus cover
                        </label>
                    @endif
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button id="detail-cancel" type="button" wire:click="cancelEdit"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                        Batal
                    </button>
                    <button id="detail-save" type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Simpan
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>