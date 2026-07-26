<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, '尚未登入。');
        }

        if ($roles !== [] && ! $user->hasRole(...$roles)) {
            throw new HttpException(Response::HTTP_FORBIDDEN, '你沒有權限存取此資源。');
        }

        return $next($request);
    }
}
