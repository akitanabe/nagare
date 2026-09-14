<?php

declare(strict_types=1);

namespace Nagare\Tests\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use function Nagare\Terminal\Aggregation\pivot;
use function Nagare\Terminal\Query\contains;
use function Nagare\Terminal\Query\isEmpty;

final class ContainsQueryTest extends TestCase
{
    #[DataProvider('containedValues')]
    public function testContainsMatchesFalseyAndNullValues(mixed $value): void
    {
        self::assertTrue(contains($value)([$value]));
    }

    /** @return iterable<string, array{mixed}> */
    public static function containedValues(): iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'zero' => [0];
        yield 'empty string' => [''];
    }

    public function testContainsUsesStrictComparison(): void
    {
        self::assertFalse(contains('1')([1]));
        self::assertFalse(contains(1)(['1']));
        self::assertFalse(contains(false)([0]));
    }

    public function testContainsDistinguishesObjectIdentity(): void
    {
        $target = new stdClass();
        $other = new stdClass();

        self::assertTrue(contains($target)([$target]));
        self::assertFalse(contains($target)([$other]));
    }

    public function testContainsDefinitionsCanBeReusedForIndependentInputs(): void
    {
        $terminal = contains('target');

        self::assertTrue($terminal(['target']));
        self::assertFalse($terminal(['other']));
        self::assertFalse($terminal([]));
        self::assertTrue($terminal(['target']));
    }

    public function testContainsReturnsFalseForAnEmptyInput(): void
    {
        self::assertFalse(contains('target')([]));
    }

    public function testContainsStopsReadingInputAtTheFirstMatch(): void
    {
        $source = static function (): iterable {
            yield 'first' => 0;
            yield 'match' => 'target';
            throw new RuntimeException('third element was requested');
        };

        self::assertTrue(contains('target')($source()));
    }

    public function testQueryTerminalsCanBePivotedOverOneInputPass(): void
    {
        $source = static function (): iterable {
            yield 'first' => 1;
            yield 'match' => 2;
            throw new RuntimeException('third element was requested');
        };

        self::assertSame([false, true], pivot(isEmpty(), contains(2))($source()));
    }
}
