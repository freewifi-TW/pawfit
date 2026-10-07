<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature flag 閘門（SASD §0.1 原則 1：程式先部署、功能再啟用）。
 * 用法：middleware('feature:friends')，對應 config('pawfit.features.friends')；未啟用一律 404。
 */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless((bool) config("pawfit.features.{$feature}", false), 404);

        return $next($request);
    }
}
