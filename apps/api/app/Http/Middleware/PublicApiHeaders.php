<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 公開 JSON API、oEmbed 與 SVG 色票卡共用的回應標頭（FR-7.5、FR-7.7）：
 * - CORS 只開 GET（簡單請求，不需 preflight）
 * - 成功回應允許公開快取；404 等一律不快取
 * - noai / noimageai 與條款指標（反 AI 訓練）
 */
class PublicApiHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('X-Robots-Tag', 'noai, noimageai');
        $response->headers->set('X-Pawfit-Terms', config('pawfit.frontend_url').'/terms#api');
        $response->headers->set('Vary', 'Accept-Encoding');

        if ($response->isSuccessful()) {
            if (! $response->headers->has('Cache-Control') || str_contains((string) $response->headers->get('Cache-Control'), 'no-cache')) {
                $response->headers->set('Cache-Control', 'public, max-age='.config('pawfit.embed.json_max_age'));
            }
        } else {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
