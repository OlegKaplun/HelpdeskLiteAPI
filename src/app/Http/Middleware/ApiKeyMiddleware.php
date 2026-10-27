<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-API-Key');
        $validKey = config('app.api_key');

        if (empty($key) || $key !== $validKey) {
            return response()->json([
                'error' => [
                    'code' => 'Unauthorized',
                    'message' => 'API-ключ неверный или отсутствует',
                ],
            ], 401);
        }

        return $next($request);
    }
}
