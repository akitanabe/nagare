<?php

declare(strict_types=1);

namespace Nagare\Tests\Aggregation;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Aggregation\fold;
use function Nagare\Aggregation\pivot;
use function Nagare\Materialization\values;

final class PivotReuseTest extends TestCase
{
    public function testSameTerminalDefinitionHasIndependentResultsForEachName(): void
    {
        $terminal = values();
        $pivoted = pivot(left: $terminal, right: $terminal);

        self::assertSame(['left' => [1, 2], 'right' => [1, 2]], $pivoted([1, 2]));
    }

    public function testFailedInvocationDoesNotLeakPartialResultsIntoTheNextInput(): void
    {
        $failure = new RuntimeException('callback failure');
        $pivoted = pivot(values: values(), total: fold(0, static function (int $carry, int $value) use ($failure): int {
            if ($value === 2) {
                throw $failure;
            }

            return $carry + $value;
        }));

        try {
            $pivoted([1, 2]);
            self::fail('The callback exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }

        self::assertSame(['values' => [3], 'total' => 3], $pivoted([3]));
    }
}
