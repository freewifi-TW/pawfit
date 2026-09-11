<?php

namespace App\Logging;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;

/**
 * Queue job 生命週期（worker 服務）：job.processed / job.failed。
 * request_id 由 Laravel Context 自動從派發端帶過來，所以能和原本的 HTTP 請求串起來。
 */
final class QueueLogger
{
    /** @var array<string, float> job id → 開始時間 */
    private static array $started = [];

    public static function onProcessing(JobProcessing $event): void
    {
        self::$started[self::key($event->job)] = microtime(true);
    }

    public static function onProcessed(JobProcessed $event): void
    {
        Log::info('job processed', [
            'event' => 'job.processed',
            'job' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'attempts' => $event->job->attempts(),
            'duration_ms' => self::duration(self::key($event->job)),
        ]);
    }

    public static function onFailed(JobFailed $event): void
    {
        Log::error('job failed', [
            'event' => 'job.failed',
            'job' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'attempts' => $event->job->attempts(),
            'duration_ms' => self::duration(self::key($event->job)),
            'exception' => $event->exception,
        ]);
    }

    private static function key(object $job): string
    {
        return method_exists($job, 'getJobId') && $job->getJobId() !== null
            ? (string) $job->getJobId()
            : (string) spl_object_id($job);
    }

    private static function duration(string $key): ?int
    {
        $started = self::$started[$key] ?? null;
        unset(self::$started[$key]);

        return $started === null ? null : (int) round((microtime(true) - $started) * 1000);
    }
}
