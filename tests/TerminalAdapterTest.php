<?php

declare(strict_types=1);

namespace Nagare\Tests;

use Nagare\Terminal;
use Nagare\TerminalExecution;
use Nagare\Tests\TerminalAdapter\AdapterLog;
use Nagare\Tests\TerminalAdapter\CallbackAdapter;
use Nagare\Tests\TerminalAdapter\StatefulAdapter;
use Nagare\Tests\TerminalAdapter\StringLengthAdapter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Terminal\Materialization\values;

final class TerminalAdapterTest extends TestCase
{
    public function testThirdPartyAdapterCanConnectDifferentInputAndOutputTypes(): void
    {
        $log = new AdapterLog();
        $adapter = new StringLengthAdapter();
        $terminal = $adapter
            |> Terminal::factory(static fn(): TerminalExecution => new class($log) implements TerminalExecution {
                /** @param AdapterLog $log */
                public function __construct(
                    private AdapterLog $log,
                ) {}

                public function accept(mixed $value, mixed $key): void
                {
                    $this->log->seen[] = [$key, $value];
                }

                public function isComplete(): bool
                {
                    return false;
                }

                public function finish(): mixed
                {
                    return 'finished';
                }
            })->apply();

        self::assertSame('finished', $terminal(['first' => 'one', 'second' => 'four']));
        self::assertSame([['first', 3], ['second', 4]], $log->seen);
    }

    public function testUnderlyingExecutionIsFreshForEachInvocation(): void
    {
        $created = 0;
        $adapter = new StringLengthAdapter();
        $terminal = $adapter
            |> Terminal::factory(static function () use (&$created): TerminalExecution {
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
            })->apply();

        self::assertSame(1, $terminal(['first' => 'one']));
        self::assertSame(2, $terminal(['first' => 'one', 'second' => 'two']));
        self::assertSame(2, $created);
    }

    public function testStatefulAdapterCreatesFreshWrapperForEachInvocation(): void
    {
        $adapter = new StatefulAdapter();
        $terminal = $adapter |> values()->apply();

        self::assertSame([[1, 'first'], [2, 'second']], $terminal(['first', 'second']));
        self::assertSame([[1, 'third']], $terminal(['third']));
        self::assertSame(2, $adapter->applyCount());
    }

    public function testAdapterPreservesOpaqueKeysAndResultIdentity(): void
    {
        $key = new \stdClass();
        $result = new \stdClass();
        $log = new AdapterLog();
        $terminal = new StringLengthAdapter()
            |> Terminal::factory(static fn(): TerminalExecution => new class($log, $result) implements
                TerminalExecution {
                /** @param AdapterLog $log */
                public function __construct(
                    private AdapterLog $log,
                    private \stdClass $result,
                ) {}

                public function accept(mixed $value, mixed $key): void
                {
                    $this->log->seen[] = [$key, $value];
                }

                public function isComplete(): bool
                {
                    return false;
                }

                public function finish(): mixed
                {
                    return $this->result;
                }
            })->apply();

        $actual = $terminal(
            (static function () use ($key): iterable {
                yield $key => 'four';
            })(),
        );

        self::assertSame($result, $actual);
        self::assertCount(1, $log->seen);
        self::assertSame($key, $log->seen[0][0]);
        self::assertSame(4, $log->seen[0][1]);
    }

    public function testAlreadyCompleteExecutionSkipsSourceAndAdapterCallbackAndShortCircuitStopsSource(): void
    {
        $sourceReads = 0;
        $callbackCalls = 0;
        $adapter = new CallbackAdapter(static function () use (&$callbackCalls): void {
            $callbackCalls++;
        });
        $complete = new \stdClass();
        $terminal = $adapter
            |> Terminal::factory(static fn(): TerminalExecution => new class($complete) implements TerminalExecution {
                public function __construct(
                    private \stdClass $result,
                ) {}

                public function accept(mixed $value, mixed $key): void {}

                public function isComplete(): bool
                {
                    return true;
                }

                public function finish(): mixed
                {
                    return $this->result;
                }
            })->apply();

        self::assertSame(
            $complete,
            $terminal(
                (static function () use (&$sourceReads): iterable {
                    $sourceReads++;
                    yield 'value';
                })(),
            ),
        );
        self::assertSame(0, $sourceReads);
        self::assertSame(0, $callbackCalls);

        $sourceReads = 0;
        $callbackCalls = 0;
        $shortCircuit = new CallbackAdapter(static function () use (&$callbackCalls): void {
            $callbackCalls++;
        })
            |> Terminal::factory(static fn(): TerminalExecution => new CompleteAfterFirstExecution())->apply();

        self::assertSame(
            1,
            $shortCircuit(
                (static function () use (&$sourceReads): iterable {
                    $sourceReads++;
                    yield 'first';
                    $sourceReads++;
                    yield 'second';
                })(),
            ),
        );
        self::assertSame(1, $sourceReads);
        self::assertSame(1, $callbackCalls);
    }

    public function testAdapterCallbackDownstreamSourceAndFinishExceptionsPreserveIdentity(): void
    {
        $callbackFailure = new RuntimeException('adapter callback');
        $callbackAdapter = new CallbackAdapter(static function () use ($callbackFailure): void {
            throw $callbackFailure;
        });
        $cb = $callbackAdapter |> values()->apply();

        try {
            $cb(['value']);
            self::fail('The adapter callback exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($callbackFailure, $actual);
        }

        $downstreamFailure = new RuntimeException('downstream accept');
        $downstreamTerminal = new CallbackAdapter(static function (): void {})
            |> Terminal::factory(static fn(): TerminalExecution => new class($downstreamFailure) implements
                TerminalExecution {
                public function __construct(
                    private RuntimeException $failure,
                ) {}

                public function accept(mixed $value, mixed $key): void
                {
                    throw $this->failure;
                }

                public function isComplete(): bool
                {
                    return false;
                }

                public function finish(): mixed
                {
                    return null;
                }
            })->apply();

        try {
            $downstreamTerminal(['value']);
            self::fail('The downstream exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($downstreamFailure, $actual);
        }

        $sourceFailure = new RuntimeException('source');
        $sourceAdapter = new CallbackAdapter(static function (): void {});
        $src = $sourceAdapter |> values()->apply();

        try {
            $src(
                (static function () use ($sourceFailure): iterable {
                    yield 'value';
                    throw $sourceFailure;
                })(),
            );
            self::fail('The source exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($sourceFailure, $actual);
        }

        $finishFailure = new RuntimeException('finish');
        $finishTerminal = new CallbackAdapter(static function (): void {})
            |> Terminal::factory(static fn(): TerminalExecution => new class($finishFailure) implements
                TerminalExecution {
                public function __construct(
                    private RuntimeException $failure,
                ) {}

                public function accept(mixed $value, mixed $key): void {}

                public function isComplete(): bool
                {
                    return false;
                }

                public function finish(): mixed
                {
                    throw $this->failure;
                }
            })->apply();

        try {
            $finishTerminal(['value']);
            self::fail('The finish exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($finishFailure, $actual);
        }
    }
}
