<?php

declare(strict_types=1);

namespace Nagare\Tests\Aggregation;

use PHPUnit\Framework\TestCase;

use function Nagare\Aggregation\average;
use function Nagare\Aggregation\count;
use function Nagare\Aggregation\join;
use function Nagare\Aggregation\sum;

final class ScalarAggregationTest extends TestCase
{
    public function testCountReturnsTheNumberOfInputValuesAndZeroForEmptyInput(): void
    {
        $count = count();

        self::assertSame(3, $count(['first' => 'a', 'second' => 2, 'third' => null]));
        self::assertSame(0, $count([]));
    }

    public function testSumReturnsTheIntegerTotalAndZeroForEmptyInput(): void
    {
        $sum = sum();

        self::assertSame(6, $sum(['first' => 1, 'second' => 2, 'third' => 3]));
        self::assertSame(0, $sum([]));
    }

    public function testAverageReturnsTheArithmeticMeanAsFloatAndNullForEmptyInput(): void
    {
        $average = average();

        self::assertSame(2.0, $average(['first' => 1, 'second' => 2, 'third' => 3]));
        self::assertSame(2.5, $average([2, 3]));
        self::assertNull($average([]));
    }

    public function testJoinConcatenatesStringsInInputOrderAndReturnsEmptyStringForEmptyInput(): void
    {
        $join = join(', ');

        self::assertSame('first, second, third', $join(['a' => 'first', 'b' => 'second', 'c' => 'third']));
        self::assertSame(', second', $join(['a' => '', 'b' => 'second']));
        self::assertSame('', $join([]));
    }

    public function testTerminalDefinitionsCanBeReusedForIndependentInputs(): void
    {
        $count = count();
        self::assertSame(3, $count([1, 2, 3]));
        self::assertSame(1, $count([4]));

        $sum = sum();
        self::assertSame(6, $sum([1, 2, 3]));
        self::assertSame(4, $sum([4]));

        $average = average();
        self::assertSame(2.0, $average([1, 2, 3]));
        self::assertSame(4.0, $average([4]));

        $join = join('|');
        self::assertSame('1|2|3', $join(['1', '2', '3']));
        self::assertSame('4', $join(['4']));
    }
}
