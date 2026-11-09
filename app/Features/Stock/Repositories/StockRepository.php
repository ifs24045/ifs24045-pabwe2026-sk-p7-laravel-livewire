<?php

namespace App\Features\Stock\Repositories;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class StockRepository implements StockRepositoryInterface
{
    public function paginateForUser(int $userId, string $search = '', string $status = '', int $perPage = 10): LengthAwarePaginator
    {
        return Stock::query()
            ->where('user_id', $userId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate($perPage);
    }

    public function allForUser(int $userId): Collection
    {
        return Stock::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Stock
    {
        return Stock::create($data);
    }

    public function update(Stock $stock, array $data): Stock
    {
        $stock->update($data);

        return $stock->refresh();
    }

    public function delete(Stock $stock): void
    {
        $stock->delete();
    }
    
    public function existsSkuForUser(int $userId, string $sku): bool
    {
        return Stock::query()
            ->where('user_id', $userId)
            ->where('sku', $sku)
            ->exists();
    }
}