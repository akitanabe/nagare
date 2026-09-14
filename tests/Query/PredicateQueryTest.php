<?php

declare(strict_types=1);

namespace Nagare\Tests\Query;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Query\all;
use function Nagare\Query\any;

final class PredicateQueryTest extends TestCase
{
    public function testAnyReturnsTrueAtTheFirstMatchingValueAndStopsReadingInput(): void
    {
        $source = static function (): iterable {
            yield 'first' => 1;
            throw new RuntimeException('second element was requested');
        };

        self::assertTrue(any(static fn(int $value): bool => $value === 1)($source()));
    }

    public function testAllReturnsFalseAtTheFirstNonMatchingValueAndStopsReadingInput(): void
    {
        $source = static function (): iterable {
            yield 'first' => 1;
            throw new RuntimeException('second element was requested');
        };

        self::assertFalse(all(static fn(int $value): bool => $value < 0)($source()));
    }

    public function testAllEvaluatesValuesInInputOrderUntilTheFirstNonMatch(): void
    {
        $received = [];
        $predicate = static function (int $value) use (&$received): bool {
            $received[] = $value;

            return $value < 3;
        };

        self::assertFalse(all($predicate)([1, 2, 3, 4]));
        self::assertSame([1, 2, 3], $received);
    }

    public function testPredicatesReadAllValuesWhenNoShortCircuitConclusionOccurs(): void
    {
        $input = [1, 2, 3];
        $anyReceived = [];
        $allReceived = [];
        $anyPredicate = static function (int $value) use (&$anyReceived): bool {
            $anyReceived[] = $value;

            return false;
        };
        $allPredicate = static function (int $value) use (&$allReceived): bool {
            $allReceived[] = $value;

            return true;
        };

        self::assertFalse(any($anyPredicate)($input));
        self::assertTrue(all($allPredicate)($input));
        self::assertSame($input, $anyReceived);
        self::assertSame($input, $allReceived);
    }

    public function testAnyReturnsFalseAndAllReturnsTrueForEmptyInput(): void
    {
        self::assertFalse(any(static fn(int $value): bool => $value > 0)([]));
        self::assertTrue(all(static fn(int $value): bool => $value > 0)([]));
    }

    public function testPredicateTerminalsCanBeReusedForIndependentInputs(): void
    {
        $any = any(static fn(int $value): bool => $value > 0);
        $all = all(static fn(int $value): bool => $value > 0);

        self::assertTrue($any([1]));
        self::assertFalse($any([]));
        self::assertFalse($all([0]));
        self::assertTrue($all([]));
    }

    public function testPredicateExceptionsPropagateWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('predicate failure');
        $terminal = any(static function (int $value) use ($failure): bool {
            if ($value === 2) {
                throw $failure;
            }

            return false;
        });

        try {
            $terminal([1, 2, 3]);
            self::fail('The predicate exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
