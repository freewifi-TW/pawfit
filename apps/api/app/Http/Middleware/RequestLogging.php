<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 每個 HTTP 請求記兩筆統一格式 log：
 *   http.request  — 進來時（method、path、ip、UA）
 *   http.response — 回應時（status、duration_ms、bytes、route、user_id）
 *
 * request_id 沿用 Caddy 帶進來的 X-Request-Id（沒有就自己產生），放進 Laravel Context，
 * 之後同一請求內所有 log、以及派發的 queue job 都會自動帶著它。
 */
class RequestLogging
{
    /** 不記錄的路徑（健康檢查會被輪詢） */
    private const SKIP = ['up'];

    private const UA_MAX = 200;

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        // 同一個 PHP 程序處理多個請求時（測試、Octane），不要把上一個請求的 user_id 帶進來
        Context::forget('user_id');
        Context::add('request_id', $requestId);

        $skip = in_array($request->path(), self::SKIP, true);
        $started = microtime(true);
        $path = '/'.ltrim($request->path(), '/');

        if (! $skip) {
            Log::info('http request', [
                'event' => 'http.request',
                'method' => $request->method(),
                'path' => $path,
                'query_keys' => array_keys($request->query()),
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), self::UA_MAX, ''),
                'referer' => $request->headers->get('referer'),
                'content_length' => (int) $request->headers->get('content-length', 0),
            ]);
        }

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        $userId = $request->user()?->getAuthIdentifier();
        if ($userId !== null) {
            Context::add('user_id', (string) $userId);
        }

        if (! $skip) {
            $status = $response->getStatusCode();
            Log::log($this->levelFor($status), 'http response', [
                'event' => 'http.response',
                'method' => $request->method(),
                'path' => $path,
                'route' => $this->routeName($request),
                'status' => $status,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'bytes' => $this->responseBytes($response),
            ]);
        }

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');
        if ($incoming !== '' && preg_match('/^[A-Za-z0-9_.:-]{8,128}$/', $incoming) === 1) {
            return $incoming;
        }

        return (string) Str::ulid();
    }

    /** 有命名就用名稱，否則用路由 pattern（路由快取後未命名路由會拿到 generated::xxx，沒有意義） */
    private function routeName(Request $request): ?string
    {
        $route = $request->route();
        if ($route === null) {
            return null;
        }
        $name = $route->getName();

        return $name !== null && ! str_starts_with($name, 'generated::') ? $name : $route->uri();
    }

    private function levelFor(int $status): string
    {
        return match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            default => 'info',
        };
    }

    private function responseBytes(Response $response): ?int
    {
        if ($response instanceof StreamedResponse) {
            return null;
        }
        if ($response instanceof BinaryFileResponse) {
            return (int) $response->getFile()->getSize();
        }
        $content = $response->getContent();

        return $content === false ? null : strlen($content);
    }
}
