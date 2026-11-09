<?php

namespace Tests\Unit\Middleware;

use App\Features\Stock\Models\Stock;
use App\Http\Middleware\EnsureStockOwnership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureStockOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function handle(?User $user, Stock|string $parameter): Response
    {
        $id = $parameter instanceof Stock ? $parameter->id : $parameter;

        $request = Request::create('/stocks/'.$id);
        $request->setUserResolver(fn () => $user);

        $route = new Route('GET', '/stocks/{stock}', fn () => null);
        $route->bind($request);
        $route->setParameter('stock', $parameter);
        $request->setRouteResolver(fn () => $route);

        return (new EnsureStockOwnership())->handle($request, fn () => new Response('lolos'));
    }

    private function assertHttpStatus(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Seharusnya melempar HttpException {$status}.");
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    public function test_owner_passes_when_route_parameter_is_a_model(): void
    {
        $owner = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->assertSame('lolos', $this->handle($owner, $stock)->getContent());
    }

    public function test_owner_passes_when_route_parameter_is_a_raw_id(): void
    {
        $owner = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->assertSame('lolos', $this->handle($owner, (string) $stock->id)->getContent());
    }

    public function test_non_owner_gets_403(): void
    {
        $stock = Stock::factory()->create();
        $other = User::factory()->create();

        $this->assertHttpStatus(403, fn () => $this->handle($other, $stock));
    }

    public function test_guest_gets_403(): void
    {
        $stock = Stock::factory()->create();

        $this->assertHttpStatus(403, fn () => $this->handle(null, $stock));
    }

    public function test_unknown_stock_gets_404(): void
    {
        $user = User::factory()->create();

        $this->assertHttpStatus(404, fn () => $this->handle($user, '999999'));
    }
}