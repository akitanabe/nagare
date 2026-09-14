<?php

declare(strict_types=1);

namespace Nagare\Tests;

use Nagare\Terminal;
use Nagare\TerminalExecution;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

use function Nagare\Adapter\map;
use function Nagare\Materialization\values;

final class TerminalTest extends TestCase
{
    public function testTerminalDefinitionCanBeReusedForIndependentInputs(): void
    {
        $terminal = values();

        self::assertSame([1], $terminal(['first' => 1]));
        self::assertSame([2], $terminal(['second' => 2]));
    }

    public function testFactoryCreatesFreshExecutionForEachInvocation(): void
    {
        $created = 0;
        $terminal = Terminal::factory(static function () use (&$created): TerminalExecution {
            $created++;

            return new RecordingExecution(new RecordingLog());
        });

        self::assertSame('finished', $terminal([1]));
        self::assertSame('finished', $terminal([2]));
        self::assertSame(2, $created);
    }

    public function testTerminalConstructorIsPrivate(): void
    {
        self::assertTrue(new \ReflectionMethod(Terminal::class, '__construct')->isPrivate());
    }

    public function testTerminalInvocationAcceptsOnlyIterableExecutionInput(): void
    {
        $terminal = values();
        $adapter = map(static fn(int $value): int => $value + 1);

        $this->expectException(TypeError::class);

        /** @phpstan-ignore argument.type, argument.templateType (The runtime contract intentionally rejects adapters as terminal input.) */
        $terminal($adapter);
    }

    public function testTerminalPassesKeysOfAnyTypeAndValuesInInputOrder(): void
    {
        $log = new RecordingLog();
        $terminal = Terminal::factory(static fn(): TerminalExecution => new RecordingExecution($log));
        $key = new \stdClass();
        $source = static function () use ($key): iterable {
            yield $key => 1;
            yield 'two' => 2;
        };

        self::assertSame('finished', $terminal($source()));
        self::assertSame([[$key, 1], ['two', 2]], $log->seen);
    }

    public function testHookObservesEachAcceptedValueBeforeUnderlyingExecution(): void
    {
        $log = new RecordingLog();
        $terminal = Terminal::factory(
            static fn(): TerminalExecution => new RecordingExecution($log),
        )->hook(static function (mixed $value, mixed $key) use ($log): void {
            $log->seen[] = ['hook', $key, $value];
        });

        self::assertSame('finished', $terminal(['first' => 1, 'second' => 2]));
        self::assertSame(
            [
                ['hook', 'first', 1],
                ['first', 1],
                ['hook', 'second', 2],
                ['second', 2],
            ],
            $log->seen,
        );
    }

    public function testHookDoesNotObserveValuesAfterShortCircuitOrAnAlreadyCompleteExecution(): void
    {
        $observed = [];
        $consumed = 0;
        $terminal = Terminal::factory(
            static fn(): TerminalExecution => new CompleteAfterFirstExecution(),
        )->hook(static function (mixed $value, mixed $key) use (&$observed): void {
            $observed[] = [$key, $value];
        });
        $source = static function () use (&$consumed): iterable {
            $consumed++;
            yield 'first' => 1;
            $consumed++;
            yield 'second' => 2;
        };

        self::assertSame(1, $terminal($source()));
        self::assertSame(1, $consumed);
        self::assertSame([['first', 1]], $observed);

        $consumed = 0;
        $observed = [];
        $complete = Terminal::factory(static fn(): TerminalExecution => new class implements TerminalExecution {
            public function accept(mixed $value, mixed $key): void {}

            public function isComplete(): bool
            {
                return true;
            }

            public function finish(): mixed
            {
                return 'complete';
            }
        })->hook(static function (mixed $value, mixed $key) use (&$observed): void {
            $observed[] = [$key, $value];
        });

        self::assertSame(
            'complete',
            $complete(
                (static function () use (&$consumed): iterable {
                    $consumed++;
                    yield 1;
                })(),
            ),
        );
        self::assertSame(0, $consumed);
        self::assertSame([], $observed);
    }

    public function testHookDefinitionCreatesFreshUnderlyingExecutionForEachInvocation(): void
    {
        $created = 0;
        $terminal = Terminal::factory(static function () use (&$created): TerminalExecution {
            $created++;

            return new class implements TerminalExecution {
                private int $accepted = 0;

                public function accept(mixed $value, mixed $key): void
                {
                    $this->accepted++;
                }

                public function isComplete(): bool
                {
                    return false;
                }

                public function finish(): mixed
                {
                    return $this->accepted;
                }
            };
        })->hook(static function (mixed $value, mixed $key): void {});

        self::assertSame(1, $terminal([1]));
        self::assertSame(2, $terminal([1, 2]));
        self::assertSame(2, $created);
    }

    public function testHookPreservesExceptionIdentityAcrossCallbackExecutionSourceAndFinish(): void
    {
        $callbackException = new RuntimeException('callback');
        $log = new RecordingLog();
        $callbackTerminal = Terminal::factory(
            static fn(): TerminalExecution => new RecordingExecution($log),
        )->hook(static function (mixed $value, mixed $key) use ($callbackException): void {
            throw $callbackException;
        });

        try {
            $callbackTerminal([1]);
            self::fail('The callback exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($callbackException, $actual);
        }
        self::assertSame([], $log->seen);

        $acceptException = new RuntimeException('accept');
        $observed = [];
        $acceptTerminal = Terminal::factory(static fn(): TerminalExecution => new class($acceptException) implements
            TerminalExecution {
            public function __construct(
                private RuntimeException $exception,
            ) {}

            public function accept(mixed $value, mixed $key): void
            {
                throw $this->exception;
            }

            public function isComplete(): bool
            {
                return false;
            }

            public function finish(): mixed
            {
                return null;
            }
        })->hook(static function (mixed $value, mixed $key) use (&$observed): void {
            $observed[] = [$key, $value];
        });

        try {
            $acceptTerminal(['key' => 1]);
            self::fail('The underlying exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($acceptException, $actual);
        }
        self::assertSame([['key', 1]], $observed);

        $sourceException = new RuntimeException('source');
        $observed = [];
        $sourceTerminal = Terminal::factory(static fn(): TerminalExecution => new RecordingExecution(
            new RecordingLog(),
        ))->hook(static function (mixed $value, mixed $key) use (&$observed): void {
            $observed[] = [$key, $value];
        });

        try {
            $sourceTerminal(
                (static function () use ($sourceException): iterable {
                    yield 1;
                    throw $sourceException;
                })(),
            );
            self::fail('The source exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($sourceException, $actual);
        }
        self::assertSame([[0, 1]], $observed);

        $finishException = new RuntimeException('finish');
        $observed = [];
        $finishTerminal = Terminal::factory(static fn(): TerminalExecution => new class($finishException) implements
            TerminalExecution {
            public function __construct(
                private RuntimeException $exception,
            ) {}

            public function accept(mixed $value, mixed $key): void {}

            public function isComplete(): bool
            {
                return false;
            }

            public function finish(): mixed
            {
                throw $this->exception;
            }
        })->hook(static function (mixed $value, mixed $key) use (&$observed): void {
            $observed[] = [$key, $value];
        });

        try {
            $finishTerminal(['key' => 1]);
            self::fail('The finish exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($finishException, $actual);
        }
        self::assertSame([['key', 1]], $observed);
    }
}
