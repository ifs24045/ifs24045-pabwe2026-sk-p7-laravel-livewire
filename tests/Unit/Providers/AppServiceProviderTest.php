<?php

namespace Tests\Unit\Providers;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\Services\OpenAiStockGeneratorService;
use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Policies\StockPolicy;
use App\Features\Stock\Repositories\StockRepository;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_repository_interface_is_bound_to_implementation(): void
    {
        $this->assertInstanceOf(StockRepository::class, app(StockRepositoryInterface::class));
    }

    public function test_ai_client_interface_is_bound_to_openai_service(): void
    {
        $this->assertInstanceOf(OpenAiStockGeneratorService::class, app(AiClientInterface::class));
    }

    public function test_stock_policy_is_registered(): void
    {
        $this->assertInstanceOf(StockPolicy::class, Gate::getPolicyFor(Stock::class));
    }

    public function test_rate_limiter_is_limited_per_ip_for_guests(): void
    {
        $limiter = RateLimiter::limiter('1000-per-second');
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.5']);

        $limit = $limiter($request);

        $this->assertSame(1000, $limit->maxAttempts);
        $this->assertSame(1, $limit->decaySeconds);
        $this->assertSame('10.0.0.5', $limit->key);
    }

    public function test_rate_limiter_is_limited_per_user_when_authenticated(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.5']);
        $request->setUserResolver(fn () => $user);

        $limit = RateLimiter::limiter('1000-per-second')($request);

        $this->assertEquals($user->id, $limit->key);
    }

    public function test_urls_use_http_when_force_https_is_disabled(): void
    {
        config(['app.force_https' => false]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('http://', url('/uji'));
    }

    public function test_urls_use_https_when_force_https_is_enabled(): void
    {
        config(['app.force_https' => true]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', URL::to('/uji'));
    }
}