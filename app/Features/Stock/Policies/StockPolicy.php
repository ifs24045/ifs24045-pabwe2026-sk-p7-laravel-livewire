<?php

namespace App\Features\Stock\Policies;

use App\Features\Stock\Models\Stock;
use App\Models\User;

class StockPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return ! $user->isAdmin();
    }

    public function view(User $user, Stock $stock): bool
    {
        return $user->id === $stock->user_id;
    }

    public function update(User $user, Stock $stock): bool
    {
        return $user->id === $stock->user_id;
    }

    public function delete(User $user, Stock $stock): bool
    {
        return $user->id === $stock->user_id;
    }
}