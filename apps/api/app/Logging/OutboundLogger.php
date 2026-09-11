<?php

namespace App\Logging;

use Closure;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * 對外呼叫（http.outbound）的統一紀錄：
 * - Laravel Http client（Google OAuth）：走 ResponseReceived / ConnectionFailed 事件
 * - AWS SDK / S3（MinIO、R2）：走 Guzzle middleware（見 AppServiceProvider）
 *
 * 只記 method、host、path、status、duration；不記 query string 與 body，避免把簽名或 token 寫進 log。
 */
final class OutboundLogger
{
    public static function onResponseReceived(ResponseReceived $event): void
    {
        $transfer = $event->response->transferStats;

        self::write(
            client: 'http',
            method: $event->request->method(),
            url: $event->request->url(),
            status: $event->response->status(),
            durationMs: $transfer ? (int) round($transfer->getTransferTime() * 1000) : null,
        );
    }

    public static function onConnectionFailed(ConnectionFailed $event): void
    {
        self::write(
            client: 'http',
            method: $event->request->method(),
            url: $event->request->url(),
            status: null,
            durationMs: null,
            error: $event->exception->getMessage(),
        );
    }

    /** Guzzle handler stack middleware（AWS SDK 用） */
    public static function guzzleMiddleware(string $client = 's3'): Closure
    {
        return static function (callable $handler) use ($client): Closure {
            return static function (RequestInterface $request, array $options) use ($handler, $client): PromiseInterface {
                $started = microtime(true);

                return $handler($request, $options)->then(
                    static function (ResponseInterface $response) use ($request, $started, $client) {
                        self::write(
                            client: $client,
                            method: $request->getMethod(),
                            url: (string) $request->getUri(),
                            status: $response->getStatusCode(),
                            durationMs: (int) round((microtime(true) - $started) * 1000),
                        );

                        return $response;
                    },
                    static function ($reason) use ($request, $started, $client) {
                        self::write(
                            client: $client,
                            method: $request->getMethod(),
                            url: (string) $request->getUri(),
                            status: null,
                            durationMs: (int) round((microtime(true) - $started) * 1000),
                            error: $reason instanceof Throwable ? $reason->getMessage() : (string) $reason,
                        );

                        return Create::rejectionFor($reason);
                    },
                );
            };
        };
    }

    public static function pushTo(HandlerStack $stack, string $client = 's3'): void
    {
        $stack->push(self::guzzleMiddleware($client), 'pawfit.outbound_log');
    }

    private static function write(string $client, string $method, string $url, ?int $status, ?int $durationMs, ?string $error = null): void
    {
        $parts = parse_url($url) ?: [];
        $level = match (true) {
            $error !== null, $status !== null && $status >= 500 => 'error',
            $status !== null && $status >= 400 => 'warning',
            default => 'info',
        };

        Log::log($level, 'outbound '.strtoupper($method).' '.($parts['host'] ?? ''), array_filter([
            'event' => 'http.outbound',
            'client' => $client,
            'method' => strtoupper($method),
            'host' => $parts['host'] ?? null,
            'path' => $parts['path'] ?? null,
            'status' => $status,
            'duration_ms' => $durationMs,
            'error' => $error,
        ], static fn ($v) => $v !== null));
    }
}
