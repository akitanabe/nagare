<?php

declare(strict_types=1);

namespace Nagare\Tests\Aggregation;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Terminal\Aggregation\max;
use function Nagare\Terminal\Aggregation\maxBy;
use function Nagare\Terminal\Aggregation\min;
use function Nagare\Terminal\Aggregation\minBy;

final class ExtremeTest extends TestCase
{
    public function testMinAndMaxUsePhpValueComparisonAndKeepTheFirstValueForTies(): void
    {
        $min = min();
        $max = max();

        self::assertSame(1, $min([3, 1, 1.0, 2]));
        self::assertSame(3, $max([1, 3, 3.0, 2]));
        self::assertSame(2, $min([10, 2]));
        self::assertSame(10, $max([10, 2]));
        // @phpstan-ignore staticMethod.alreadyNarrowedType (The empty-input result must also be verified at runtime.)
        self::assertNull($min([]));
        // @phpstan-ignore staticMethod.alreadyNarrowedType (The empty-input result must also be verified at runtime.)
        self::assertNull($max([]));
    }

    public function testMinByAndMaxByReturnTheOriginalValueAndKeepTheFirstValueForTies(): void
    {
        $minBy = minBy(self::score(...));
        $maxBy = maxBy(self::score(...));
        $firstMinimum = new ScoredItem('first minimum', 1);
        $firstMaximum = new ScoredItem('first maximum', 3);
        $items = [
            $firstMinimum,
            $firstMaximum,
            new ScoredItem('second minimum', 1),
            new ScoredItem('second maximum', 3),
        ];

        self::assertSame($firstMinimum, $minBy($items));
        self::assertSame($firstMaximum, $maxBy($items));
        // @phpstan-ignore staticMethod.alreadyNarrowedType (The empty-input result must also be verified at runtime.)
        self::assertNull($minBy([]));
        // @phpstan-ignore staticMethod.alreadyNarrowedType (The empty-input result must also be verified at runtime.)
        self::assertNull($maxBy([]));
    }

    public function testExtremeTerminalsCanBeReusedForIndependentInputs(): void
    {
        $min = min();
        $max = max();
        $minBy = minBy(static fn(int $value): int => $value);
        $maxBy = maxBy(static fn(int $value): int => $value);

        self::assertSame(1, $min([2, 1]));
        self::assertSame(3, $min([3]));
        self::assertSame(2, $max([1, 2]));
        self::assertSame(4, $max([4]));
        self::assertSame(1, $minBy([2, 1]));
        self::assertSame(3, $minBy([4, 3]));
        self::assertSame(2, $maxBy([1, 2]));
        self::assertSame(4, $maxBy([4]));
    }

    public function testExtremeSelectorExceptionsPropagateWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('selector failure');
        $minBy = minBy(static function (int $value) use ($failure): int {
            if ($value === 2) {
                throw $failure;
            }

            return $value;
        });

        try {
            $minBy([1, 2]);
            self::fail('The selector exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    private static function score(ScoredItem $item): int
    {
        return $item->score;
    }
}
