<?php

namespace App\Features\Stock\Livewire;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\DTO\GeneratedStockItem;
use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockExcelService;
use App\Features\Stock\Services\StockService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('components.layouts.app')]
class StockList extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public bool $showForm = false;

    public bool $showImport = false;

    public bool $showAi = false;

    public ?int $editingId = null;

    public ?int $confirmingDeleteId = null;

    public string $name = '';

    public string $sku = '';

    public string $description = '';

    public string $quantity = '0';

    public string $unit = 'pcs';

    public string $price = '';

    public string $minStock = '5';

    public $importFile = null;

    public ?array $importResult = null;

    public string $aiPrompt = '';

    public string $aiCount = '5';

    public ?string $aiError = null;

    public array $aiItems = [];

    public array $aiSelected = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'minStock' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Nama barang wajib diisi.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.integer' => 'Jumlah harus berupa bilangan bulat.',
            'quantity.min' => 'Jumlah tidak boleh negatif.',
            'unit.required' => 'Satuan wajib diisi.',
            'price.numeric' => 'Harga harus berupa angka.',
            'price.min' => 'Harga tidak boleh negatif.',
            'minStock.required' => 'Stok minimum wajib diisi.',
            'minStock.integer' => 'Stok minimum harus berupa bilangan bulat.',
            'minStock.min' => 'Stok minimum tidak boleh negatif.',
        ];
    }

    public function openCreate(): void
    {
        Gate::authorize('create', Stock::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $stock = $this->ownedStock($id);

        $this->resetValidation();
        $this->editingId = $stock->id;
        $this->name = $stock->name;
        $this->sku = $stock->sku ?? '';
        $this->description = $stock->description ?? '';
        $this->quantity = (string) $stock->quantity;
        $this->unit = $stock->unit;
        $this->price = $stock->price !== null ? (string) $stock->price : '';
        $this->minStock = (string) $stock->min_stock;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(StockService $service): void
    {
        $this->validate();

        $data = [
            'name' => trim($this->name),
            'sku' => $this->sku !== '' ? $this->sku : null,
            'description' => $this->description !== '' ? $this->description : null,
            'quantity' => (int) $this->quantity,
            'unit' => $this->unit,
            'price' => $this->price !== '' ? $this->price : null,
            'min_stock' => (int) $this->minStock,
        ];

        if ($this->editingId !== null) {
            $service->update($this->ownedStock($this->editingId), $data);
            $message = 'Stok berhasil diperbarui.';
        } else {
            Gate::authorize('create', Stock::class);
            $service->create(auth()->user(), $data);
            $message = 'Stok berhasil ditambahkan.';
        }

        $this->closeForm();
        session()->flash('status', $message);
    }

    public function adjust(int $id, int $delta, StockService $service): void
    {
        $service->adjustQuantity($this->ownedStock($id), $delta);
    }

    public function confirmDelete(int $id): void
    {
        $this->ownedStock($id, 'delete');
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(StockService $service): void
    {
        if ($this->confirmingDeleteId === null) {
            return;
        }

        $service->delete($this->ownedStock($this->confirmingDeleteId, 'delete'));

        $this->confirmingDeleteId = null;
        $this->resetPage();
        session()->flash('status', 'Stok berhasil dihapus.');
    }

    public function export(StockExcelService $excel, StockRepositoryInterface $stocks)
    {
        Gate::authorize('viewAny', Stock::class);

        $items = $stocks->allForUser(auth()->id());

        return response()->streamDownload(function () use ($excel, $items) {
            $excel->export($items, 'php://output');
        }, 'stok-barang-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadTemplate(StockExcelService $excel)
    {
        Gate::authorize('viewAny', Stock::class);

        return response()->streamDownload(function () use ($excel) {
            $excel->template('php://output');
        }, 'template-stok-barang.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function openImport(): void
    {
        Gate::authorize('create', Stock::class);

        $this->resetValidation();
        $this->reset(['importFile', 'importResult']);
        $this->showImport = true;
    }

    public function closeImport(): void
    {
        $this->resetValidation();
        $this->reset(['importFile', 'importResult', 'showImport']);
    }

    public function import(StockExcelService $excel): void
    {
        Gate::authorize('create', Stock::class);

        $this->validate([
            'importFile' => ['required', 'file', 'extensions:xlsx', 'max:2048'],
        ], [
            'importFile.required' => 'Pilih file Excel (.xlsx) terlebih dahulu.',
            'importFile.extensions' => 'File harus berformat .xlsx.',
            'importFile.max' => 'Ukuran file maksimal 2 MB.',
        ]);

        $this->importResult = $excel->import($this->importFile->getRealPath(), auth()->user());

        $this->reset('importFile');
        $this->resetPage();
    }

    public function openAi(): void
    {
        Gate::authorize('create', Stock::class);

        $this->resetAi();
        $this->showAi = true;
    }

    public function closeAi(): void
    {
        $this->resetAi();
        $this->showAi = false;
    }

    public function generateAi(AiClientInterface $ai): void
    {
        Gate::authorize('create', Stock::class);

        $this->validate([
            'aiPrompt' => ['required', 'string', 'min:5', 'max:500'],
            'aiCount' => ['required', 'integer', 'between:1,10'],
        ], [
            'aiPrompt.required' => 'Deskripsi wajib diisi.',
            'aiPrompt.min' => 'Deskripsi minimal 5 karakter.',
            'aiPrompt.max' => 'Deskripsi maksimal 500 karakter.',
            'aiCount.required' => 'Jumlah item wajib diisi.',
            'aiCount.integer' => 'Jumlah item harus berupa bilangan bulat.',
            'aiCount.between' => 'Jumlah item harus antara 1 dan 10.',
        ]);

        $this->aiError = null;
        $this->aiItems = [];
        $this->aiSelected = [];

        $limiterKey = 'ai-generate:'.auth()->id();

        if (RateLimiter::tooManyAttempts($limiterKey, 10)) {
            $this->aiError = 'Terlalu banyak permintaan AI. Coba lagi sebentar lagi.';

            return;
        }

        RateLimiter::hit($limiterKey, 60);

        try {
            $items = $ai->generate(trim($this->aiPrompt), (int) $this->aiCount);
        } catch (RuntimeException $e) {
            $this->aiError = $e->getMessage();

            return;
        }

        $this->aiItems = array_map(fn (GeneratedStockItem $item) => $item->toArray(), $items);
        $this->aiSelected = array_fill(0, count($this->aiItems), true);
    }

    public function saveAi(StockService $service): void
    {
        Gate::authorize('create', Stock::class);

        $selected = array_keys(array_filter($this->aiSelected));

        if ($selected === []) {
            $this->addError('aiSelected', 'Pilih minimal satu item untuk disimpan.');

            return;
        }

        $this->validate([
            'aiItems.*.name' => ['required', 'string', 'max:255'],
            'aiItems.*.sku' => ['nullable', 'string', 'max:100'],
            'aiItems.*.quantity' => ['required', 'integer', 'min:0'],
            'aiItems.*.unit' => ['required', 'string', 'max:50'],
            'aiItems.*.price' => ['nullable', 'numeric', 'min:0'],
            'aiItems.*.min_stock' => ['required', 'integer', 'min:0'],
            'aiItems.*.description' => ['nullable', 'string', 'max:5000'],
        ]);

        $created = 0;

        foreach ($selected as $index) {
            if (! isset($this->aiItems[$index])) {
                continue;
            }

            $service->create(auth()->user(), $this->aiItems[$index]);
            $created++;
        }

        $this->closeAi();
        $this->resetPage();
        session()->flash('status', "{$created} stok dari AI berhasil disimpan.");
    }

    public function render()
    {
        $status = StockStatus::tryFrom($this->status)?->value ?? '';

        $stocks = app(StockService::class)->list(auth()->user(), trim($this->search), $status);

        return view('livewire.stock.stock-list', [
            'stocks' => $stocks,
            'statuses' => StockStatus::cases(),
        ]);
    }

    private function ownedStock(int $id, string $ability = 'update'): Stock
    {
        $stock = Stock::findOrFail($id);

        Gate::authorize($ability, $stock);

        return $stock;
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->sku = '';
        $this->description = '';
        $this->quantity = '0';
        $this->unit = 'pcs';
        $this->price = '';
        $this->minStock = '5';
    }

    private function resetAi(): void
    {
        $this->resetValidation();
        $this->aiPrompt = '';
        $this->aiCount = '5';
        $this->aiError = null;
        $this->aiItems = [];
        $this->aiSelected = [];
    }
}