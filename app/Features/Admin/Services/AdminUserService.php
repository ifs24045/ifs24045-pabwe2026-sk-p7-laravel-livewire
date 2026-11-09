<?php

namespace App\Features\Admin\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminUserService
{
    public function __construct(private readonly StockRepositoryInterface $stocks)
    {
    }

    /**
     * @return array{users: int, members: int, stocks: int}
     */
    public function summary(): array
    {
        return [
            'users' => User::count(),
            'members' => User::where('role', User::ROLE_MEMBER)->count(),
            'stocks' => Stock::count(),
        ];
    }

    public function paginateUsers(string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        return User::query()
            ->withCount('stocks')
            ->withSum('stocks', 'quantity')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('role')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function paginateStocksFor(User $user, string $search = '', string $status = '', int $perPage = 10): LengthAwarePaginator
    {
        return $this->stocks->paginateForUser($user->id, $search, $status, $perPage);
    }
}