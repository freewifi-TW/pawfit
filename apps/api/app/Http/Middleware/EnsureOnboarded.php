<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** 需先完成 onboarding（Pawfit ID + 同意條款）才能使用建立內容的 API。 */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->isOnboarded()) {
            return response()->json([
                'message' => '請先完成 Pawfit ID 設定與條款同意。',
                'code' => 'onboarding_required',
            ], 409);
        }

        return $next($request);
    }
}
