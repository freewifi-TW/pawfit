<?php

namespace Tests\Unit;

use App\Logging\JsonLineFormatter;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use Tests\TestCase;

class JsonLineFormatterTest extends TestCase
{
    public function test_outputs_unified_shape_with_defaults(): void
    {
        $line = $this->format(new LogRecord(
            datetime: new DateTimeImmutable('2026-09-11T13:05:53.123456+00:00'),
            channel: 'local',
            level: Level::Info,
            message: 'hello',
            context: ['foo' => 'bar'],
        ));

        $this->assertSame(
            ['ts', 'level', 'service', 'event', 'message', 'request_id', 'user_id', 'context'],
            array_keys($line),
        );
        $this->assertSame('2026-09-11T13:05:53.123456+00:00', $line['ts']);
        $this->assertSame('info', $line['level']);
        $this->assertSame('api', $line['service']);
        $this->assertSame('log', $line['event']);
        $this->assertSame('hello', $line['message']);
        $this->assertNull($line['request_id']);
        $this->assertNull($line['user_id']);
        $this->assertSame(['foo' => 'bar'], $line['context']);
    }

    public function test_lifts_common_fields_from_context_and_extra(): void
    {
        $line = $this->format(new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'local',
            level: Level::Warning,
            message: 'http response',
            context: ['event' => 'http.response', 'status' => 404],
            extra: ['request_id' => 'req-123456789', 'user_id' => 'u-1'],
        ), service: 'worker');

        $this->assertSame('warning', $line['level']);
        $this->assertSame('worker', $line['service']);
        $this->assertSame('http.response', $line['event']);
        $this->assertSame('req-123456789', $line['request_id']);
        $this->assertSame('u-1', $line['user_id']);
        $this->assertSame(['status' => 404], $line['context']);
    }

    public function test_extracts_throwable_into_exception_block(): void
    {
        $previous = new RuntimeException('root cause');
        $e = new RuntimeException('boom', 42, $previous);

        $line = $this->format(new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'local',
            level: Level::Error,
            message: 'boom',
            context: ['exception' => $e, 'path' => '/api/x'],
        ));

        $this->assertSame('exception', $line['event']);
        $this->assertSame(['path' => '/api/x'], $line['context']);
        $this->assertSame(RuntimeException::class, $line['exception']['class']);
        $this->assertSame('boom', $line['exception']['message']);
        $this->assertSame(42, $line['exception']['code']);
        $this->assertStringEndsWith('JsonLineFormatterTest.php', $line['exception']['file']);
        $this->assertIsArray($line['exception']['trace']);
        $this->assertLessThanOrEqual(15, count($line['exception']['trace']));
        $this->assertSame('root cause', $line['exception']['previous']['message']);
    }

    public function test_every_line_is_single_line_json(): void
    {
        $formatter = new JsonLineFormatter;
        $raw = $formatter->format(new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'local',
            level: Level::Debug,
            message: "multi\nline 中文",
            context: ['nested' => ['a' => ['b' => "x\ny"]]],
        ));

        $this->assertStringEndsWith("\n", $raw);
        $this->assertSame(1, substr_count($raw, "\n"));
        $this->assertNotNull(json_decode($raw, true));
    }

    /** @return array<string, mixed> */
    private function format(LogRecord $record, ?string $service = null): array
    {
        $raw = (new JsonLineFormatter($service))->format($record);

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
