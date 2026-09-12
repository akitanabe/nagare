<?php

declare(strict_types=1);

namespace Nagare\Tests\Selection;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Selection\find;
use function Nagare\Selection\last;
use function Nagare\Selection\max;
use function Nagare\Selection\maxBy;
use function Nagare\Selection\min;
use function Nagare\Selection\minBy;

final class SelectorTest extends TestCase
{
    public function testLastReturnsTheLastInputValueAndNullForEmptyInput(): void
    {
        $last = last();

        self::assertSame('last', $last(['first', null, 'last']));
        self::assertNull($last(['first', null]));
        // @phpstan-ignore staticMethod.alreadyNarrowedType (The empty-input result must also be verified at runtime.)
        self::assertNull($last([]));
    }

    public function testFindReturnsTheFirstMatchingInputValueWithoutRequestingAnotherValue(): void
    {
        $source = static function (): iterable {
            yield 'first' => 1;
            yield 'match' => 2;
            throw new RuntimeException('third element was requested');
        };

        self::assertSame(2, find(static fn(int $value): bool => $value === 2)($source()));
    }

    public function testFindReturnsNullWhenNoValueMatchesOrInputIsEmpty(): void
    {
        $find = find(static fn(int $value): bool => $value > 10);

        self::assertNull($find([1, 2, 3]));
        // @phpstan-ignore staticMethod.alreadyNarrowedType (The empty-input result must also be verified at runtime.)
        self::assertNull($find([]));
    }

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

    public function testSelectorTerminalsCanBeReusedForIndependentInputs(): void
    {
        $last = last();
        $find = find(static fn(int $value): bool => $value > 0);
        $min = min();
        $max = max();
        $minBy = minBy(static fn(int $value): int => $value);
        $maxBy = maxBy(static fn(int $value): int => $value);

        self::assertSame(2, $last([1, 2]));
        self::assertSame(4, $last([3, 4]));
        self::assertSame(1, $find([1, -1]));
        self::assertNull($find([-1, -2]));
        self::assertSame(1, $min([2, 1]));
        self::assertSame(3, $min([3]));
        self::assertSame(2, $max([1, 2]));
        self::assertSame(4, $max([4]));
        self::assertSame(1, $minBy([2, 1]));
        self::assertSame(3, $minBy([4, 3]));
        self::assertSame(2, $maxBy([1, 2]));
        self::assertSame(4, $maxBy([4]));
    }

    public function testSelectorCallbackExceptionsPropagateWithTheirOriginalIdentity(): void
    {
        $predicateFailure = new RuntimeException('predicate failure');
        $find = find(static function (int $value) use ($predicateFailure): bool {
            if ($value === 2) {
                throw $predicateFailure;
            }

            return false;
        });

        try {
            $find([1, 2]);
            self::fail('The predicate exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($predicateFailure, $thrown);
        }

        $selectorFailure = new RuntimeException('selector failure');
        $minBy = minBy(static function (int $value) use ($selectorFailure): int {
            if ($value === 2) {
                throw $selectorFailure;
            }

            return $value;
        });

        try {
            $minBy([1, 2]);
            self::fail('The selector exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($selectorFailure, $thrown);
        }
    }

    private static function score(ScoredItem $item): int
    {
        return $item->score;
    }
}
