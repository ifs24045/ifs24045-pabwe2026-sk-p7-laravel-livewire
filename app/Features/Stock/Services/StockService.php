<?php

namespace App\Features\Stock\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class StockService
{
    private const FIELDS = ['name', 'sku', 'description', 'quantity', 'unit', 'price', 'min_stock'];

    private const COVER_DIRECTORY = 'stock-covers';

    public function __construct(private readonly StockRepositoryInterface $stocks)
    {
    }

    public function list(User $user, string $search = '', string $status = '', int $perPage = 10): LengthAwarePaginator
    {
        return $this->stocks->paginateForUser($user->id, $search, $status, $perPage);
    }

    public function create(User $user, array $data, ?UploadedFile $cover = null): Stock
    {
        $fields = Arr::only($data, self::FIELDS);
        $quantity = (int) ($fields['quantity'] ?? 0);
        $minStock = (int) ($fields['min_stock'] ?? 5);

        $fields['user_id'] = $user->id;
        $fields['status'] = StockStatus::fromQuantity($quantity, $minStock)->value;

        if ($cover) {
            $fields['cover_image_path'] = $cover->store(self::COVER_DIRECTORY, 'public');
        }

        return $this->stocks->create($fields);
    }

    public function update(Stock $stock, array $data, ?UploadedFile $cover = null, bool $removeCover = false): Stock
    {
        $fields = Arr::only($data, self::FIELDS);
        $quantity = (int) ($fields['quantity'] ?? $stock->quantity);
        $minStock = (int) ($fields['min_stock'] ?? $stock->min_stock);

        $fields['status'] = StockStatus::fromQuantity($quantity, $minStock)->value;

        if ($cover) {
            $this->deleteCover($stock);
            $fields['cover_image_path'] = $cover->store(self::COVER_DIRECTORY, 'public');
        } elseif ($removeCover) {
            $this->deleteCover($stock);
            $fields['cover_image_path'] = null;
        }

        return $this->stocks->update($stock, $fields);
    }

    public function adjustQuantity(Stock $stock, int $delta): Stock
    {
        $quantity = max(0, $stock->quantity + $delta);

        return $this->stocks->update($stock, [
            'quantity' => $quantity,
            'status' => StockStatus::fromQuantity($quantity, $stock->min_stock)->value,
        ]);
    }

    public function delete(Stock $stock): void
    {
        $this->deleteCover($stock);
        $this->stocks->delete($stock);
    }

    private function deleteCover(Stock $stock): void
    {
        if ($stock->cover_image_path) {
            Storage::disk('public')->delete($stock->cover_image_path);
        }
    }
}