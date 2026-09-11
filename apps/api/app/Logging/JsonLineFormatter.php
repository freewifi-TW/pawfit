<?php

namespace App\Logging;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;
use Throwable;

/**
 * 統一 log 格式（docs/logging.md）：每行一個 JSON，欄位順序固定。
 *
 *   ts, level, service, event, message, request_id, user_id, context{...}, exception{...}
 *
 * - event / request_id / user_id 可放在 Log 的 context 傳入，或由 Laravel Context 共享（會進 record extra）
 * - context 內若有 Throwable，會抽成 exception 區塊並把 event 預設為 exception
 * - 其餘 context 與 extra 全部收進 context 物件（各服務自訂欄位放這裡）
 */
final class JsonLineFormatter extends NormalizerFormatter
{
    public const TS_FORMAT = 'Y-m-d\TH:i:s.uP';

    /** 從 context / extra 抽到最上層的共同欄位 */
    private const TOP_LEVEL = ['event', 'request_id', 'user_id'];

    private const TRACE_FRAMES = 15;

    public function __construct(private readonly ?string $service = null)
    {
        parent::__construct(self::TS_FORMAT);
        $this->setMaxNormalizeDepth(6);
        $this->setMaxNormalizeItemCount(200);
    }

    public function format(LogRecord $record): string
    {
        $context = $record->context;
        $extra = $record->extra;

        $exception = null;
        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                $exception = $this->formatException($value);
                unset($context[$key]);
                break;
            }
        }

        $top = [];
        foreach (self::TOP_LEVEL as $key) {
            $top[$key] = $context[$key] ?? $extra[$key] ?? null;
            unset($context[$key], $extra[$key]);
        }

        if ($top['event'] === null) {
            $top['event'] = $exception ? 'exception' : 'log';
        }

        $merged = $context;
        if ($extra !== []) {
            $merged = array_merge($merged, $extra);
        }
        $line = [
            'ts' => $record->datetime->format(self::TS_FORMAT),
            'level' => strtolower($record->level->getName()),
            'service' => $this->service ?? (string) config('logging.service', 'api'),
            'event' => (string) $top['event'],
            'message' => $record->message,
            'request_id' => $top['request_id'] !== null ? (string) $top['request_id'] : null,
            'user_id' => $top['user_id'] !== null ? (string) $top['user_id'] : null,
            'context' => (object) $this->normalize($merged),
        ];
        if ($exception !== null) {
            $line['exception'] = $exception;
        }

        return $this->toJson($line, true)."\n";
    }

    /** @return array<string, mixed> */
    private function formatException(Throwable $e): array
    {
        $frames = [];
        foreach (array_slice($e->getTrace(), 0, self::TRACE_FRAMES) as $frame) {
            $call = ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '');
            $at = isset($frame['file']) ? $this->shortPath($frame['file']).':'.($frame['line'] ?? 0) : '[internal]';
            $frames[] = $call !== '' ? "{$call} @ {$at}" : $at;
        }

        $out = [
            'class' => $e::class,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $this->shortPath($e->getFile()),
            'line' => $e->getLine(),
            'trace' => $frames,
        ];
        if ($e->getPrevious() !== null) {
            $p = $e->getPrevious();
            $out['previous'] = [
                'class' => $p::class,
                'message' => $p->getMessage(),
                'file' => $this->shortPath($p->getFile()),
                'line' => $p->getLine(),
            ];
        }

        return $out;
    }

    private function shortPath(string $path): string
    {
        $base = rtrim(base_path(), '/\\').DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
