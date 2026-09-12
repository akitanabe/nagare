<?php

declare(strict_types=1);

namespace Nagare\Tests\Aggregation;

use Nagare\TerminalExecution;
use Nagare\Tests\CompleteAfterFirstExecution;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Aggregation\combine;
use function Nagare\Aggregation\fold;
use function Nagare\Materialization\values;
use function Nagare\Selection\first;
use function Nagare\Transformation\map;

final class CombineTest extends TestCase
{
    public function testPlanCombinationAppliesEachTransformToValuesFromOneInput(): void
    {
        $terminal = combine(
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

    public function testCombineBroadcastsOnePassInputToNamedTerminalsInOrder(): void
    {
        $source = static function (): iterable {
            yield 'first' => 1;
            yield 'second' => 2;
        };

        $combined = combine(
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
            $combined($source()),
        );
    }

    public function testCombineStopsRequestingSourceWhenEveryTerminalIsComplete(): void
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
            combine(first: first(), anotherFirst: first())($source()),
        );
    }

    public function testCombineDoesNotSendLaterValuesToACompletedTerminal(): void
    {
        $executions = [];
        $shortCircuit = new \Nagare\Terminal(static function () use (&$executions): TerminalExecution {
            $execution = new CompleteAfterFirstExecution();
            $executions[] = $execution;

            return $execution;
        });

        self::assertSame(
            [
                'shortCircuit' => 1,
                'values' => [1, 2],
            ],
            combine(shortCircuit: $shortCircuit, values: values())([
                'first' => 1,
                'second' => 2,
            ]),
        );
        self::assertCount(1, $executions);
        self::assertSame(1, $executions[0]->acceptedCount());
    }

    public function testReusingCombinedTerminalKeepsEachInputIndependent(): void
    {
        $combined = combine(values: values(), total: fold(
            0,
            static fn(int $carry, int $value): int => $carry + $value,
        ));

        self::assertSame(
            [
                'values' => [1],
                'total' => 1,
            ],
            $combined(['first' => 1]),
        );
        self::assertSame(
            [
                'values' => [2],
                'total' => 2,
            ],
            $combined(['second' => 2]),
        );
    }

    public function testCombineReturnsEachTerminalDefaultForEmptyInput(): void
    {
        self::assertSame(
            [
                'first' => null,
                'values' => [],
                'total' => 10,
            ],
            combine(
                first: first(),
                values: values(),
                total: fold(10, static fn(int $carry, int $value): int => $carry + $value),
            )([]),
        );
    }

    public function testCombinePropagatesChildCallbackExceptions(): void
    {
        $failure = new RuntimeException('callback failure');
        $combined = combine(total: fold(0, static function (int $carry, int $value) use ($failure): int {
            if ($value === 2) {
                throw $failure;
            }

            return $carry + $value;
        }));

        $thrown = null;
        try {
            $combined([1, 2]);
            self::fail('The callback exception was not thrown.');
        } catch (RuntimeException $caught) {
            $thrown = $caught;
        }

        self::assertSame($failure, $thrown);
    }

    public function testCombinePropagatesSourceIteratorExceptions(): void
    {
        $failure = new RuntimeException('iterator failure');
        $source = static function () use ($failure): iterable {
            yield 'first' => 1;
            throw $failure;
        };

        $thrown = null;
        try {
            combine(values: values())($source());
            self::fail('The iterator exception was not thrown.');
        } catch (RuntimeException $caught) {
            $thrown = $caught;
        }

        self::assertSame($failure, $thrown);
    }
}
