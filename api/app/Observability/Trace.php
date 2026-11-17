<?php

namespace App\Observability;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Trace
{
    /**
     * Attributes for the spans currently on the stack, outermost first.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $fields = [];

    public function begin(Request $request): void
    {
        $this->fields = [];
        Context::flush();

        $incoming = $this->parse($request->headers->get('traceparent'));

        Context::add([
            'trace_id' => $incoming['trace_id'] ?? $this->hex(16),
            'span_id' => $incoming['span_id'] ?? null,
            'parent_span_id' => null,
        ]);
    }

    /**
     * @param  callable(): mixed  $callback
     */
    public function span(string $name, callable $callback): mixed
    {
        $this->ensureTrace();

        $previousSpanId = Context::get('span_id');
        $previousParentSpanId = Context::get('parent_span_id');
        $spanId = $this->hex(8);

        if (! Context::has('request_span_id')) {
            Context::add('request_span_id', $spanId);
        }

        Context::add([
            'span_id' => $spanId,
            'parent_span_id' => is_string($previousSpanId) ? $previousSpanId : null,
        ]);

        $this->fields[] = [];
        $started = hrtime(true);

        try {
            $result = $callback();
            $this->finish($name, $spanId, $started, 'ok');

            return $result;
        } catch (Throwable $e) {
            $this->finish($name, $spanId, $started, 'error', $e);

            throw $e;
        } finally {
            array_pop($this->fields);
            Context::add([
                'span_id' => $previousSpanId,
                'parent_span_id' => $previousParentSpanId,
            ]);
        }
    }

    public function add(string $key, mixed $value): void
    {
        $index = array_key_last($this->fields);

        if ($index === null) {
            return;
        }

        $this->fields[$index][$key] = $value;
    }

    public function stamp(Response $response): Response
    {
        $header = $this->traceparent();

        if ($header !== null) {
            $response->headers->set('traceparent', $header);
        }

        return $response;
    }

    public function traceparent(): ?string
    {
        $traceId = Context::get('trace_id');
        $spanId = Context::get('request_span_id');

        if (! is_string($traceId) || ! is_string($spanId)) {
            return null;
        }

        return sprintf('00-%s-%s-01', $traceId, $spanId);
    }

    private function ensureTrace(): void
    {
        if (is_string(Context::get('trace_id'))) {
            return;
        }

        Context::add([
            'trace_id' => $this->hex(16),
            'span_id' => null,
            'parent_span_id' => null,
        ]);
    }

    private function finish(string $name, string $spanId, int $started, string $status, ?Throwable $error = null): void
    {
        $fields = $this->fields[array_key_last($this->fields)] ?? [];

        EventLog::info('span', [
            'name' => $name,
            'span_id' => $spanId,
            'parent_span_id' => Context::get('parent_span_id'),
            'duration_ms' => round((hrtime(true) - $started) / 1_000_000, 2),
            'status' => $status,
            ...($error === null ? [] : ['error' => $error::class]),
            ...$fields,
        ]);
    }

    /**
     * @return array{trace_id: string, span_id: string}|null
     */
    private function parse(?string $header): ?array
    {
        if ($header === null) {
            return null;
        }

        if (! preg_match('/^00-([0-9a-f]{32})-([0-9a-f]{16})-[0-9a-f]{2}$/', strtolower(trim($header)), $matches)) {
            return null;
        }

        if ($matches[1] === str_repeat('0', 32) || $matches[2] === str_repeat('0', 16)) {
            return null;
        }

        return [
            'trace_id' => $matches[1],
            'span_id' => $matches[2],
        ];
    }

    private function hex(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
