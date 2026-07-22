<?php

declare(strict_types=1);

namespace APMG\Commerce\Tests;

use RuntimeException;
use Throwable;

final class TestCase
{
    /** @var array<string, callable(): void> */
    private array $tests = [];

    public function test(string $name, callable $test): void
    {
        $this->tests[$name] = $test;
    }

    public function run(): int
    {
        $failures = 0;

        foreach ($this->tests as $name => $test) {
            try {
                $test();
                fwrite(STDOUT, "PASS {$name}\n");
            } catch (Throwable $exception) {
                $failures++;
                fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
            }
        }

        fwrite(STDOUT, sprintf("%d tests, %d failures.\n", count($this->tests), $failures));

        return $failures === 0 ? 0 : 1;
    }

    public static function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message !== '' ? $message : sprintf(
                'Expected %s, got %s',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    public static function true(bool $actual, string $message = ''): void
    {
        self::same(true, $actual, $message);
    }

    public static function false(bool $actual, string $message = ''): void
    {
        self::same(false, $actual, $message);
    }

    /** @param class-string<Throwable> $exceptionClass */
    public static function throws(string $exceptionClass, callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            if ($exception instanceof $exceptionClass) {
                return;
            }

            throw new RuntimeException(sprintf(
                'Expected %s, got %s: %s',
                $exceptionClass,
                $exception::class,
                $exception->getMessage()
            ));
        }

        throw new RuntimeException("Expected {$exceptionClass} to be thrown");
    }
}
