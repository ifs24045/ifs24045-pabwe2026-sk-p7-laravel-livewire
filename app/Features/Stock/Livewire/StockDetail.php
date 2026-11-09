<?php

namespace App\Features\Stock\Livewire;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class StockDetail extends Component
{
    use WithFileUploads;

    public Stock $stock;

    public bool $editing = false;

    public string $name = '';

    public string $sku = '';

    public string $description = '';

    public string $quantity = '0';

    public string $unit = 'pcs';

    public string $price = '';

    public string $minStock = '5';

    public $cover = null;

    public bool $removeCover = false;

    public function mount(Stock $stock): void
    {
        Gate::authorize('view', $stock);

        $this->stock = $stock;
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
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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
            'cover.image' => 'Cover harus berupa gambar.',
            'cover.mimes' => 'Cover harus berformat JPG, PNG, atau WEBP.',
            'cover.max' => 'Ukuran cover maksimal 2 MB.',
        ];
    }

    public function startEdit(): void
    {
        Gate::authorize('update', $this->stock);

        $this->resetValidation();
        $this->reset(['cover', 'removeCover']);
        $this->name = $this->stock->name;
        $this->sku = $this->stock->sku ?? '';
        $this->description = $this->stock->description ?? '';
        $this->quantity = (string) $this->stock->quantity;
        $this->unit = $this->stock->unit;
        $this->price = $this->stock->price !== null ? (string) $this->stock->price : '';
        $this->minStock = (string) $this->stock->min_stock;
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->resetValidation();
        $this->reset(['cover', 'removeCover']);
        $this->editing = false;
    }

    public function save(StockService $service): void
    {
        Gate::authorize('update', $this->stock);

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

        $this->stock = $service->update($this->stock, $data, $this->cover, $this->removeCover);

        $this->reset(['cover', 'removeCover']);
        $this->editing = false;
        session()->flash('status', 'Stok berhasil diperbarui.');
    }

    public function adjust(int $delta, StockService $service): void
    {
        Gate::authorize('update', $this->stock);

        $this->stock = $service->adjustQuantity($this->stock, $delta);
    }

    public function delete(StockService $service)
    {
        Gate::authorize('delete', $this->stock);

        $service->delete($this->stock);

        session()->flash('status', 'Stok berhasil dihapus.');

        return $this->redirectRoute('stocks.index');
    }

    public function render()
    {
        return view('livewire.stock.stock-detail');
    }
}