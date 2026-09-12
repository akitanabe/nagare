<?php

declare(strict_types=1);

namespace Nagare\Tests\Aggregation;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Aggregation\combine;
use function Nagare\Aggregation\fold;
use function Nagare\Materialization\values;

final class CombineReuseTest extends TestCase
{
    public function testSameTerminalDefinitionHasIndependentResultsForEachName(): void
    {
        $terminal = values();
        $combined = combine(left: $terminal, right: $terminal);

        self::assertSame(['left' => [1, 2], 'right' => [1, 2]], $combined([1, 2]));
    }

    public function testFailedInvocationDoesNotLeakPartialResultsIntoTheNextInput(): void
    {
        $failure = new RuntimeException('callback failure');
        $combined = combine(values: values(), total: fold(0, static function (int $carry, int $value) use (
            $failure,
        ): int {
            if ($value === 2) {
                throw $failure;
            }

            return $carry + $value;
        }));

        try {
            $combined([1, 2]);
            self::fail('The callback exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }

        self::assertSame(['values' => [3], 'total' => 3], $combined([3]));
    }
}
