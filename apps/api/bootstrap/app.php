<?php

use App\Http\Middleware\EnsureNotBanned;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\RequestLogging;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 最外層：解析 X-Request-Id、記 http.request / http.response（docs/logging.md）
        $middleware->prepend(RequestLogging::class);

        // same-domain SPA：/api/* 以 session cookie 認證（Sanctum stateful）
        $middleware->statefulApi();

        // 純 API，沒有 login 頁：未登入一律丟 AuthenticationException（→ JSON 401），
        // 不要導向不存在的 route('login')（否則沒帶 Accept: application/json 的請求會變 500）
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'onboarded' => EnsureOnboarded::class,
            'not-banned' => EnsureNotBanned::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 例外紀錄（event=exception）附上請求資訊；request_id / user_id 由 Context 自動帶入
        // （CLI / worker 沒有真正的請求，request() 會是空殼，不附）
        $exceptions->context(fn (Throwable $e, array $context) => app()->runningInConsole()
            ? $context
            : array_merge($context, array_filter([
                'method' => request()->method(),
                'path' => '/'.ltrim(request()->path(), '/'),
                'route' => request()->route()?->getName(),
            ])));
    })->create();
