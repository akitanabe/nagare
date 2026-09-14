<?php

declare(strict_types=1);

namespace Nagare\Tests\Adapter;

use Nagare\Adapter\Definition;
use Nagare\Terminal;
use Nagare\TerminalExecution;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Adapter\filter;
use function Nagare\Adapter\filterMap;
use function Nagare\Adapter\map;
use function Nagare\Terminal\Aggregation\count;
use function Nagare\Terminal\Aggregation\pivot;
use function Nagare\Terminal\Materialization\associate;
use function Nagare\Terminal\Materialization\entries;
use function Nagare\Terminal\Materialization\keys;
use function Nagare\Terminal\Materialization\values;
use function Nagare\Terminal\Query\first;

final class TerminalIntegrationTest extends TestCase
{
    public function testRejectedInputsKeepEachTerminalEmptyResultAndCountCountsAcceptedValues(): void
    {
        $reject = filter(static fn(mixed $_value): bool => false);

        self::assertSame([], ($reject |> values()->apply())([1, 2]));
        self::assertSame([], ($reject |> keys()->apply())([1, 2]));
        self::assertSame([], ($reject |> entries()->apply())([1, 2]));
        self::assertSame([], ($reject |> associate()->apply())([1, 2]));
        self::assertNull(($reject |> first()->apply())([1, 2]));
        self::assertSame(0, ($reject |> count()->apply())([1, 2]));
        self::assertSame(
            ['first' => null, 'values' => [], 'count' => 0],
            ($reject |> pivot(first: first(), values: values(), count: count())->apply())([1, 2]),
        );
    }

    public function testAssociateUsesOriginalKeysAndLastDuplicateValue(): void
    {
        $source = static function (): iterable {
            yield '123' => 2;
            yield 'duplicate' => 2;
            yield 'duplicate' => 4;
            yield 'rejected' => 3;
        };
        $terminal = filter(static fn(int $value): bool => ($value % 2) === 0)
            |> map(static fn(int $value): string => "v{$value}")
            |> associate()->apply();

        self::assertSame([123 => 'v2', 'duplicate' => 'v4'], $terminal($source()));
    }

    public function testFirstReadsUntilFirstAcceptedValueAndThenStopsCallbacksAndSource(): void
    {
        $reads = 0;
        $calls = 0;
        $source = static function () use (&$reads): iterable {
            foreach ([1, 3, 4, 6] as $value) {
                $reads++;
                yield $value;
            }
        };
        $terminal = filter(static function (int $value) use (&$calls): bool {
            $calls++;

            return ($value % 2) === 0;
        })
            |> first()->apply();

        self::assertSame(4, $terminal($source()));
        self::assertSame(3, $reads);
        self::assertSame(3, $calls);
    }

    public function testFilterMapFirstStopsSourceAndMapperAfterTheFirstNonNullOutput(): void
    {
        $reads = 0;
        $calls = [];
        $terminal = filterMap(static function (int $value) use (&$calls): ?string {
            $calls[] = $value;

            return match ($value) {
                1 => null,
                2 => 'accepted',
                default => throw new RuntimeException('decoy callback was invoked'),
            };
        })
            |> first()->apply();
        $source = static function () use (&$reads): iterable {
            foreach ([1, 2, 3] as $value) {
                $reads++;
                yield $value;
            }
        };

        self::assertSame('accepted', $terminal($source()));
        self::assertSame(2, $reads);
        self::assertSame([1, 2], $calls);
    }

    public function testDefinitionAndTerminalCanBeReusedWithFreshInvocationState(): void
    {
        $calls = 0;
        $adapter = filterMap(static function (int $value) use (&$calls): ?int {
            $calls++;

            return $value > 0 ? $value : null;
        });
        $terminal = $adapter |> values()->apply();

        self::assertSame([1], $terminal([-1, 1]));
        self::assertSame([2, 3], $terminal([2, 3]));
        self::assertSame([4], ($adapter |> values()->apply())([4]));
        self::assertSame(5, $calls);
    }

    public function testCallbackExceptionIdentityIsPreserved(): void
    {
        $failure = new RuntimeException('adapter callback');
        $terminal = filterMap(static function (int $value) use ($failure): ?int {
            throw $failure;
        })
            |> values()->apply();

        try {
            $terminal([1]);
            self::fail('The callback exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
    }

    public function testDownstreamSourceAndFinishExceptionIdentitiesArePreserved(): void
    {
        $downstreamFailure = new RuntimeException('downstream');
        $downstream = map(static fn(int $value): int => $value)
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
            $downstream([1]);
            self::fail('The downstream exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($downstreamFailure, $actual);
        }

        $sourceFailure = new RuntimeException('source');
        $sourceTerminal = map(static fn(int $value): int => $value) |> values()->apply();

        try {
            $sourceTerminal(
                (static function () use ($sourceFailure): iterable {
                    yield 1;
                    throw $sourceFailure;
                })(),
            );
            self::fail('The source exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($sourceFailure, $actual);
        }

        $finishFailure = new RuntimeException('finish');
        $finish = map(static fn(int $value): int => $value)
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
            $finish([1]);
            self::fail('The finish exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($finishFailure, $actual);
        }
    }

    public function testHooksSeeAdaptedValuesInsideAndOriginalValuesOutsideTheAdapter(): void
    {
        $observed = [];
        $adapter = filter(static fn(int $value): bool => $value > 1) |> map(static fn(int $value): int => $value * 10);

        $inside = $adapter
            |> values()->hook(static function (mixed $value, mixed $key) use (&$observed): void {
                $observed[] = ['inside', $key, $value];
            })->apply();
        self::assertSame([20], $inside(['a' => 1, 'b' => 2]));

        $outside = ($adapter |> values()->apply())->hook(static function (mixed $value, mixed $key) use (
            &$observed,
        ): void {
            $observed[] = ['outside', $key, $value];
        });
        self::assertSame([20], $outside(['a' => 1, 'b' => 2]));

        self::assertSame(
            [
                ['inside', 'b', 20],
                ['outside', 'a', 1],
                ['outside', 'b', 2],
            ],
            $observed,
        );
    }

    public function testPivotChildAdaptersRunPerBranchWhileOuterAdapterRunsOncePerSourceValueBeforeFanOut(): void
    {
        $childCalls = [];
        $outerCalls = [];
        $reads = 0;
        $child = static function (string $name) use (&$childCalls): Definition {
            return map(static function (int $value) use ($name, &$childCalls): int {
                $childCalls[] = [$name, $value];

                return $value;
            });
        };
        $pivot = pivot(a: $child('a') |> values()->apply(), b: $child('b') |> first()->apply());
        $terminal = map(static function (int $value) use (&$outerCalls): int {
            $outerCalls[] = $value;

            return $value * 10;
        })
            |> $pivot->apply();
        $source = static function () use (&$reads): iterable {
            foreach ([1, 2] as $value) {
                $reads++;
                yield $value;
            }
        };

        self::assertSame(['a' => [10, 20], 'b' => 10], $terminal($source()));
        self::assertSame(2, $reads);
        self::assertSame([1, 2], $outerCalls);
        self::assertSame([['a', 10], ['b', 10], ['a', 20]], $childCalls);
    }
}
