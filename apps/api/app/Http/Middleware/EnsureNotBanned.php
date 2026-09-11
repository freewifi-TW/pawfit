<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_banned) {
            return response()->json([
                'message' => __('messages.auth.banned'),
                'code' => 'banned',
            ], 403);
        }

        return $next($request);
    }
}
