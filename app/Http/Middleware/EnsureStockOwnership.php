<?php

namespace App\Http\Middleware;

use App\Features\Stock\Models\Stock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStockOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $stock = $request->route('stock');

        if (! $stock instanceof Stock) {
            $stock = Stock::find($stock);
        }

        abort_if($stock === null, 404);

        if ($request->user()?->id !== $stock->user_id) {
            abort(403, 'Stok ini bukan milik Anda.');
        }

        return $next($request);
    }
}