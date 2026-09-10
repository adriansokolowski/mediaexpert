<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * There is no user model in this project, so the few administrative endpoints
 * are guarded by a single shared secret sent as "X-Admin-Token".
 */
class EnsureAdminToken
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('scheduling.admin_token');

        if (! is_string($expected) || $expected === '') {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Administrative endpoints are disabled: SCHEDULING_ADMIN_TOKEN is not set.');
        }

        if (! hash_equals($expected, (string) $request->header('X-Admin-Token', ''))) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid administrative token.');
        }

        return $next($request);
    }
}
