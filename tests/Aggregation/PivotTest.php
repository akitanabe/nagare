<?php

declare(strict_types=1);

namespace Nagare\Tests\Aggregation;

use Nagare\TerminalExecution;
use Nagare\Tests\CompleteAfterFirstExecution;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Aggregation\fold;
use function Nagare\Aggregation\pivot;
use function Nagare\Materialization\values;
use function Nagare\Query\first;
use function Nagare\Transformation\map;

final class PivotTest extends TestCase
{
    public function testPivotWithoutTerminalsDoesNotReadTheSource(): void
    {
        $sourceLog = [];
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'read';
            yield 1;
        };

        self::assertSame([], pivot()($source()));
        self::assertSame([], $sourceLog);
    }

    public function testPlanPivotAppliesEachTransformToValuesFromOneInput(): void
    {
        $terminal = pivot(
            a: map(static fn(int $value): int => $value + 1)
                |> map(static fn(int $value): int => $value * 2)
                |> values()->apply(),
        );

        $source = static function (): iterable {
            yield 1;
            yield 2;
            yield 3;
        };

        self::assertSame(['a' => [4, 6, 8]], $source() |> $terminal);
    }

    public function testPivotBroadcastsOnePassInputToNamedTerminalsInOrder(): void
    {
        $source = static function (): iterable {
            yield 'first' => 1;
            yield 'second' => 2;
        };

        $pivoted = pivot(
            first: first(),
            values: values(),
            total: fold(0, static fn(int $carry, int $value): int => $carry + $value),
        );

        self::assertSame(
            [
                'first' => 1,
                'values' => [1, 2],
                'total' => 3,
            ],
            $pivoted($source()),
        );
    }

    public function testPivotStopsRequestingSourceWhenEveryTerminalIsComplete(): void
    {
        $source = static function (): iterable {
            yield 'first' => 'value';
            throw new RuntimeException('a second element was requested');
        };

        self::assertSame(
            [
                'first' => 'value',
                'anotherFirst' => 'value',
            ],
            pivot(first: first(), anotherFirst: first())($source()),
        );
    }

    public function testPivotDoesNotSendLaterValuesToACompletedTerminal(): void
    {
        $executions = [];
        $shortCircuit = \Nagare\Terminal::factory(static function () use (&$executions): TerminalExecution {
            $execution = new CompleteAfterFirstExecution();
            $executions[] = $execution;

            return $execution;
        });

        self::assertSame(
            [
                'shortCircuit' => 1,
                'values' => [1, 2],
            ],
            pivot(shortCircuit: $shortCircuit, values: values())([
                'first' => 1,
                'second' => 2,
            ]),
        );
        self::assertCount(1, $executions);
        self::assertSame(1, $executions[0]->acceptedCount());
    }

    public function testReusingPivotedTerminalKeepsEachInputIndependent(): void
    {
        $pivoted = pivot(values: values(), total: fold(0, static fn(int $carry, int $value): int => $carry + $value));

        self::assertSame(
            [
                'values' => [1],
                'total' => 1,
            ],
            $pivoted(['first' => 1]),
        );
        self::assertSame(
            [
                'values' => [2],
                'total' => 2,
            ],
            $pivoted(['second' => 2]),
        );
    }

    public function testPivotReturnsEachTerminalDefaultForEmptyInput(): void
    {
        self::assertSame(
            [
                'first' => null,
                'values' => [],
                'total' => 10,
            ],
            pivot(
                first: first(),
                values: values(),
                total: fold(10, static fn(int $carry, int $value): int => $carry + $value),
            )([]),
        );
    }

    public function testPivotPropagatesChildCallbackExceptions(): void
    {
        $failure = new RuntimeException('callback failure');
        $pivoted = pivot(total: fold(0, static function (int $carry, int $value) use ($failure): int {
            if ($value === 2) {
                throw $failure;
            }

            return $carry + $value;
        }));

        $thrown = null;
        try {
            $pivoted([1, 2]);
            self::fail('The callback exception was not thrown.');
        } catch (RuntimeException $caught) {
            $thrown = $caught;
        }

        self::assertSame($failure, $thrown);
    }

    public function testPivotPropagatesSourceIteratorExceptions(): void
    {
        $failure = new RuntimeException('iterator failure');
        $source = static function () use ($failure): iterable {
            yield 'first' => 1;
            throw $failure;
        };

        $thrown = null;
        try {
            pivot(values: values())($source());
            self::fail('The iterator exception was not thrown.');
        } catch (RuntimeException $caught) {
            $thrown = $caught;
        }

        self::assertSame($failure, $thrown);
    }
}
