<?php

use App\Features\Admin\Livewire\AdminUserStockList;
use App\Features\Admin\Livewire\UserList;
use App\Features\Auth\Livewire\Login;
use App\Features\Auth\Livewire\Register;
use App\Features\Profile\Livewire\Profile;
use App\Features\Stock\Livewire\StockDetail;
use App\Features\Stock\Livewire\StockList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:1000-per-second')->group(function () {
    Route::get('/', function () {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        return redirect()->route(Auth::user()->isAdmin() ? 'admin.users.index' : 'stocks.index');
    });

    Route::middleware('guest')->group(function () {
        Route::get('/login', Login::class)->name('login');
        Route::get('/register', Register::class)->name('register');
    });

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->middleware('auth')->name('logout');

    Route::get('/profile', Profile::class)->middleware('auth')->name('profile');

    Route::middleware(['auth', 'role:member'])->group(function () {
        Route::get('/stocks', StockList::class)->name('stocks.index');
        Route::get('/stocks/{stock}', StockDetail::class)->name('stocks.show')->middleware('owns.stock');
    });

    Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', UserList::class)->name('users.index');
        Route::get('/users/{user}/stocks', AdminUserStockList::class)->name('users.stocks');
    });
    Route::get('/', function () {
    return redirect('/login');
    });
});