<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gives a job a consistent, greppable run log: one debug line when it starts,
 * one summary line with duration and counters when it finishes, and one error
 * line if it blows up.
 *
 * Every line carries the same `run_id`, so a single run of a scheduled job can
 * be pulled out of the log even when runs overlap:
 *
 *     grep '"run_id":"0193...' storage/logs/laravel.log
 *
 * Typical use:
 *
 *     $this->startRun(['month' => $this->month]);
 *     ...
 *     $this->countMetric('sessions_stored');
 *     ...
 *     $this->finishRun();
 */
trait LogsJobRun
{
    protected ?string $jobRunId = null;

    protected ?float $jobRunStartedAt = null;

    /** @var array<string, mixed> */
    protected array $jobRunMetrics = [];

    /**
     * Level for the "completed" summary line. Jobs that run every minute should
     * override this to `debug` so they do not drown the log.
     */
    protected function jobRunCompletionLevel(): string
    {
        return 'info';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function startRun(array $context = []): void
    {
        $this->jobRunId = (string) Str::uuid();
        $this->jobRunStartedAt = microtime(true);
        $this->jobRunMetrics = [];

        Log::debug($this->jobRunName().' started', $this->jobRunContext($context));
    }

    /**
     * Increment a counter reported in the completion summary.
     */
    protected function countMetric(string $key, int $by = 1): void
    {
        $this->jobRunMetrics[$key] = ($this->jobRunMetrics[$key] ?? 0) + $by;
    }

    /**
     * Set (rather than increment) a value reported in the completion summary.
     */
    protected function setMetric(string $key, mixed $value): void
    {
        $this->jobRunMetrics[$key] = $value;
    }

    /**
     * Per-phase detail, only emitted when the app is running at LOG_LEVEL=debug.
     *
     * @param  array<string, mixed>  $context
     */
    protected function runDebug(string $message, array $context = []): void
    {
        Log::debug($this->jobRunName().': '.$message, $this->jobRunContext($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function runWarning(string $message, array $context = []): void
    {
        Log::warning($this->jobRunName().': '.$message, $this->jobRunContext($context));
    }

    /**
     * The summary line. This is the one that proves the job ran at all, so it
     * is emitted on success even when nothing changed.
     *
     * @param  array<string, mixed>  $context
     */
    protected function finishRun(array $context = []): void
    {
        Log::log(
            $this->jobRunCompletionLevel(),
            $this->jobRunName().' completed',
            $this->jobRunContext($context)
        );
    }

    /**
     * The run stopped early for a known, non-exceptional reason — an upstream
     * API returning an error, a malformed payload. Logged at error level like
     * a failure, but without an exception trace that would say nothing useful.
     *
     * @param  array<string, mixed>  $context
     */
    protected function abortRun(string $reason, array $context = []): void
    {
        Log::error($this->jobRunName().' aborted: '.$reason, $this->jobRunContext($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function failRun(Throwable $e, array $context = []): void
    {
        Log::error($this->jobRunName().' failed: '.$e->getMessage(), $this->jobRunContext(array_merge($context, [
            'exception' => get_class($e),
            'origin' => $e->getFile().':'.$e->getLine(),
            'trace' => $e->getTraceAsString(),
        ])));
    }

    protected function jobRunName(): string
    {
        return class_basename(static::class);
    }

    /**
     * Base context attached to every line of the run.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function jobRunContext(array $extra = []): array
    {
        $context = [
            'job' => static::class,
            'run_id' => $this->jobRunId,
        ];

        if ($this->jobRunStartedAt !== null) {
            $context['duration_ms'] = round((microtime(true) - $this->jobRunStartedAt) * 1000, 1);
        }

        return array_merge($context, $this->jobRunMetrics, $extra);
    }
}
