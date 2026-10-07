<?php

namespace App\Providers;

use App\Logging\OutboundLogger;
use App\Logging\QueueLogger;
use App\Models\User;
use Aws\Handler\Guzzle\GuzzleHandler;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bootLogging();

        // API 回應不包 data 外層（分頁集合仍保留 data/meta）
        JsonResource::withoutWrapping();

        // 管理員：環境變數名單（SASD §2.5）
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('presign', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('reports', fn (Request $r) => Limit::perHour(10)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('public', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        // 公開 JSON API 與嵌入（FR-7.5）：匿名、以 IP 計
        RateLimiter::for('public_api', fn (Request $r) => Limit::perMinute((int) config('pawfit.embed.rate_per_minute'))->by($r->ip()));
    }

    /**
     * 統一 log 格式的事件來源（docs/logging.md）：
     * - http.outbound：Laravel Http client 事件 + AWS SDK（S3）的 Guzzle middleware
     * - job.processed / job.failed：queue 事件
     * http.request / http.response 在 RequestLogging middleware；exception 走 Laravel 例外處理器。
     */
    private function bootLogging(): void
    {
        Event::listen(ResponseReceived::class, [OutboundLogger::class, 'onResponseReceived']);
        Event::listen(ConnectionFailed::class, [OutboundLogger::class, 'onConnectionFailed']);

        Event::listen(JobProcessing::class, [QueueLogger::class, 'onProcessing']);
        Event::listen(JobProcessed::class, [QueueLogger::class, 'onProcessed']);
        Event::listen(JobFailed::class, [QueueLogger::class, 'onFailed']);

        // S3 client 的 HTTP handler 掛上 outbound 紀錄。
        // 自訂 creator 會優先於內建 s3 driver；handler 物件在這裡建立而不放 config（config:cache 無法序列化物件）。
        Storage::extend('s3', function ($app, array $config) {
            $stack = HandlerStack::create();
            OutboundLogger::pushTo($stack, 's3');
            $config['http_handler'] = new GuzzleHandler(new GuzzleClient(['handler' => $stack]));

            return $app['filesystem']->createS3Driver($config);
        });
    }
}
