<?php

declare(strict_types=1);

namespace Nagare\Tests\Transformation;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

use function Nagare\Materialization\values;
use function Nagare\Transformation\map;

final class MapTest extends TestCase
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

        self::assertSame([4, 6, 8], $terminal(['a' => 1, 'b' => 2, 'c' => 3]));
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
        self::assertSame([2], $terminal(['value' => 1]));
        self::assertSame(1, $calls);
    }

    public function testDerivedTerminalCanBeReusedWithoutSharingExecutionState(): void
    {
        $terminal = map(static fn(int $value): int => $value + 1) |> values()->apply();

        self::assertSame([2], $terminal(['first' => 1]));
        self::assertSame([3], $terminal(['second' => 2]));
    }

    public function testOriginalTerminalAndDerivedTerminalRemainIndependentDefinitions(): void
    {
        $original = values();
        $derived = map(static fn(int $value): int => $value + 1) |> $original->apply();

        self::assertSame([1], $original(['value' => 1]));
        self::assertSame([2], $derived(['value' => 1]));
        self::assertSame([1], $original(['value' => 1]));
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

    public function testTransformInvocationRejectsValueExecutionInput(): void
    {
        $transform = map(static fn(int $value): int => $value + 1);

        $this->expectException(TypeError::class);

        /** @phpstan-ignore argument.type (Transform values are executed through transform(), not __invoke().) */
        $transform(1);
    }
}
