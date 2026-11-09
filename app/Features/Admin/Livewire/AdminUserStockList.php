<?php

namespace App\Features\Admin\Livewire;

use App\Features\Admin\Services\AdminUserService;
use App\Features\Stock\Enums\StockStatus;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class AdminUserStockList extends Component
{
    use WithPagination;

    public User $user;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function mount(User $user): void
    {
        abort_if($user->isAdmin(), 404);

        $this->user = $user;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $status = StockStatus::tryFrom($this->status)?->value ?? '';

        $stocks = app(AdminUserService::class)
            ->paginateStocksFor($this->user, trim($this->search), $status);

        return view('livewire.admin.admin-user-stock-list', [
            'stocks' => $stocks,
            'statuses' => StockStatus::cases(),
        ]);
    }
}