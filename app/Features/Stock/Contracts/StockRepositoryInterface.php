<?php

namespace App\Features\Stock\Contracts;

use App\Features\Stock\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface StockRepositoryInterface
{
    public function paginateForUser(int $userId, string $search = '', string $status = '', int $perPage = 10): LengthAwarePaginator;

    public function allForUser(int $userId): Collection;

    public function create(array $data): Stock;

    public function update(Stock $stock, array $data): Stock;

    public function delete(Stock $stock): void;

    public function existsSkuForUser(int $userId, string $sku): bool;
}