<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

/**
 * In-memory sink for AI trace events.
 *
 * Why: PHPUnit previously wrote every AI event into the real
 * storage/logs/ai-*.log files, so operational signal was buried under
 * thousands of `testing.INFO` lines (in one file, 825 of 831 lines were
 * test runs). With the `ai_memory` channel selected in tests, events land in
 * a per-process ring buffer instead: production logs stay clean AND tests
 * can assert on the exact events without touching the filesystem.
 *
 * Selected via AI_LOG_CHANNEL (phpunit.xml sets it to `ai_memory`);
 * production keeps the `ai` daily-file channel.
 */
class AiTraceHandler extends AbstractProcessingHandler
{
    public const CHANNEL = 'ai_memory';

    /** Hard cap so a long test run cannot grow without bound. */
    private const MAX_ENTRIES = 500;

    /** @var array<int, array{time:string,level:string,env:string,message:string,context:string}> */
    private static array $entries = [];

    /**
     * Drop everything recorded so far. Called between tests.
     */
    public static function flush(): void
    {
        self::$entries = [];
    }

    /**
     * Chronological formatted lines, same shape the file channel produces:
     *   [Y-m-d H:i:s] env.LEVEL: message {json-context}
     *
     * Keeping the identical shape means AiLogger::tailForUser() works
     * unchanged on both sinks.
     *
     * @return array<int, string>
     */
    public static function lines(int $limit = 500): array
    {
        $out = [];
        foreach (array_slice(self::$entries, -$limit) as $e) {
            $out[] = '['.$e['time'].'] '.$e['env'].'.'.$e['level'].': '.$e['message'].' '.$e['context'];
        }

        return $out;
    }

    /**
     * Structured view for assertions: decoded event + context per entry.
     *
     * @return array<int, array{event:string, context:array}>
     */
    public static function events(): array
    {
        $out = [];
        foreach (self::$entries as $e) {
            $out[] = [
                'time' => $e['time'],
                'level' => $e['level'],
                'event' => $e['message'],
                'context' => json_decode($e['context'], true) ?: [],
            ];
        }

        return $out;
    }

    protected function write(LogRecord $record): void
    {
        self::$entries[] = [
            'time' => $record->datetime->format('Y-m-d H:i:s'),
            'level' => $record->level->getName(),
            'env' => (string) (config('app.env') ?: 'production'),
            'message' => $record->message,
            'context' => (string) json_encode(
                $record->context,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        ];

        if (count(self::$entries) > self::MAX_ENTRIES) {
            self::$entries = array_slice(self::$entries, -self::MAX_ENTRIES);
        }
    }
}
