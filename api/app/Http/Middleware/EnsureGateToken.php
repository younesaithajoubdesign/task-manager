<?php

namespace App\Http\Middleware;

use App\Services\AccessGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only with the gate token as a Bearer token
 * (or always, while no password is configured).
 */
class EnsureGateToken
{
    public function __construct(private AccessGate $gate) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->gate->accepts($request->bearerToken())) {
            return response()->json(['message' => 'Password required.'], 401);
        }

        return $next($request);
    }
}
