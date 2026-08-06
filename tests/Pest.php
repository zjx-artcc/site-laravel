<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toRunInLessThan', function (int $milliseconds) {
    $start = microtime(true);

    // Execute the callable
    ($this->value)();

    $elapsedMs = (microtime(true) - $start) * 1000;

    expect($elapsedMs)
        ->toBeLessThan($milliseconds, "Execution took {$elapsedMs}ms, expected under {$milliseconds}ms");

    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Swap the logger for one that records everything in memory, so tests can
 * assert on what was actually logged — level, message and context.
 *
 * A Monolog TestHandler is used rather than a facade spy because the code under
 * test reaches the logger by several routes (Log::info, Log::log($level, ...)),
 * and what matters is the record that comes out the far end, not which method
 * was called to get there.
 */
function captureLogs(): TestHandler
{
    $handler = new TestHandler(Level::Debug);

    Log::swap(
        new Illuminate\Log\Logger(new Logger('testing', [$handler]))
    );

    return $handler;
}

/**
 * Find the first captured record whose message contains $needle.
 *
 * @return array<string, mixed>|null
 */
function findLog(TestHandler $handler, string $needle): ?array
{
    foreach ($handler->getRecords() as $record) {
        if (str_contains($record['message'], $needle)) {
            return [
                'level' => $record['level_name'],
                'message' => $record['message'],
                'context' => $record['context'],
            ];
        }
    }

    return null;
}
