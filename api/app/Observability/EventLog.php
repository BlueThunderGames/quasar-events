<?php

namespace App\Observability;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

class EventLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $event, array $context = []): void
    {
        self::write('info', $event, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $event, array $context = []): void
    {
        self::write('warning', $event, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(string $level, string $event, array $context): void
    {
        Log::log($level, $event, [
            ...$context,
            'trace_id' => Context::get('trace_id'),
            'span_id' => Context::get('span_id'),
            'parent_span_id' => Context::get('parent_span_id'),
        ]);
    }
}
