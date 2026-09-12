<?php

declare(strict_types=1);

namespace Nagare\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

use function Nagare\combine;
use function Nagare\first;
use function Nagare\map;
use function Nagare\mapping;
use function Nagare\values;

final class TransformTest extends TestCase
{
    public function testMappedValuesComposeLeftToRightBeforeValuesTerminal(): void
    {
        $firstCalls = 0;
        $secondCalls = 0;
        $transform = map(static function (int $value) use (&$firstCalls): int {
            $firstCalls++;

            return $value + 1;
        })
            |> map(static function (int $value) use (&$secondCalls): int {
                $secondCalls++;

                return $value * 2;
            });

        self::assertSame([0, 0], [$firstCalls, $secondCalls]);

        self::assertSame(8, $transform->transform(3));
        self::assertSame([1, 1], [$firstCalls, $secondCalls]);

        $terminal = $transform |> values()->apply();

        self::assertSame(['a' => 4, 'b' => 6, 'c' => 8], $terminal(['a' => 1, 'b' => 2, 'c' => 3]));
    }

    public function testDefinitionsDoNotExecuteTransformCallbacksUntilTerminalExecution(): void
    {
        $calls = 0;
        $terminal = map(static function (int $value) use (&$calls): int {
            $calls++;

            return $value + 1;
        })
            |> values()->apply();

        self::assertSame(0, $calls);

        self::assertSame(['value' => 2], $terminal(['value' => 1]));
        self::assertSame(1, $calls);
    }

    public function testDerivedTerminalCanBeReusedWithoutSharingExecutionState(): void
    {
        $terminal = map(static fn(int $value): int => $value + 1) |> values()->apply();

        self::assertSame(['first' => 2], $terminal(['first' => 1]));
        self::assertSame(['second' => 3], $terminal(['second' => 2]));
    }

    public function testOriginalTerminalAndDerivedTerminalRemainIndependentDefinitions(): void
    {
        $original = values();
        $derived = map(static fn(int $value): int => $value + 1) |> $original->apply();

        self::assertSame(['value' => 1], $original(['value' => 1]));
        self::assertSame(['value' => 2], $derived(['value' => 1]));
        self::assertSame(['value' => 1], $original(['value' => 1]));
    }

    public function testTerminalInvocationAcceptsOnlyIterableExecutionInput(): void
    {
        $terminal = values();
        $transform = map(static fn(int $value): int => $value + 1);

        $this->expectException(TypeError::class);

        /** @phpstan-ignore argument.type (The runtime contract intentionally rejects transforms as terminal input.) */
        $terminal($transform);
    }

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

    public function testTransformCallbackExceptionsPropagate(): void
    {
        $failure = new RuntimeException('transform failure');
        $terminal = map(static function (int $value) use ($failure): int {
            throw $failure;
        })
            |> values()->apply();

        try {
            $terminal([1]);
            self::fail('The transform exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    public function testMappingIsLazyAndPreservesKeysAndOrder(): void
    {
        $calls = [];
        $mapped = mapping(static function (int $value) use (&$calls): int {
            $calls[] = $value;

            return $value * 2;
        })(['first' => 1, 'second' => 2]);

        self::assertSame([], $calls);

        $entries = [];
        foreach ($mapped as $key => $value) {
            $entries[] = [$key, $value];
        }

        self::assertSame([['first', 2], ['second', 4]], $entries);
        self::assertSame([1, 2], $calls);
    }

    public function testMappingOnlyProcessesValuesRequestedByDownstreamTerminal(): void
    {
        $calls = [];
        $mapped = mapping(static function (int $value) use (&$calls): int {
            $calls[] = $value;

            return $value * 2;
        });
        $source = static function (): iterable {
            yield 'first' => 1;
            throw new RuntimeException('second value was requested');
        };
        $mapped = $mapped($source());

        self::assertSame(2, first()($mapped));
        self::assertSame([1], $calls);
    }

    public function testMappingPropagatesSourceIteratorExceptions(): void
    {
        $failure = new RuntimeException('iterator failure');
        $mapped = mapping(static fn(int $value): int => $value);
        $source = static function () use ($failure): iterable {
            yield 1;
            throw $failure;
        };
        $mapped = $mapped($source());

        try {
            values()($mapped);
            self::fail('The iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
