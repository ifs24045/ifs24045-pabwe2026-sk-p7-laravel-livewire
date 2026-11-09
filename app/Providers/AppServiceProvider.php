<?php

namespace App\Providers;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\Services\OpenAiStockGeneratorService;
use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Policies\StockPolicy;
use App\Features\Stock\Repositories\StockRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StockRepositoryInterface::class, StockRepository::class);
        $this->app->bind(AiClientInterface::class, OpenAiStockGeneratorService::class);
    }

    public function boot(): void
    {
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        Gate::policy(Stock::class, StockPolicy::class);

        RateLimiter::for('1000-per-second', function (Request $request) {
            return Limit::perSecond(1000)->by($request->user()?->id ?: $request->ip());
        });
    }
}