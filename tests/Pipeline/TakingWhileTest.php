<?php

declare(strict_types=1);

namespace Nagare\Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Pipeline\takingWhile;
use function Nagare\Query\first;

final class TakingWhileTest extends TestCase
{
    public function testTakingWhileYieldsAcceptedValuesInOrderWithTheirKeys(): void
    {
        $firstKey = new \stdClass();
        $sourceLog = [];
        $predicateLog = [];
        $source = static function () use ($firstKey, &$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield $firstKey => 1;
            $sourceLog[] = 'second';
            yield 'second' => 2;
            $sourceLog[] = 'third';
            yield 'third' => 3;
            $sourceLog[] = 'after-failure';
            throw new RuntimeException('A value after the first falsey result was requested.');
        };

        $taken = takingWhile(static function (int $value) use (&$predicateLog): bool {
            $predicateLog[] = $value;

            return $value < 3;
        });

        $entries = [];
        foreach ($taken($source()) as $key => $value) {
            $entries[] = [$key, $value];
        }

        self::assertSame([[$firstKey, 1], ['second', 2]], $entries);
        self::assertSame(['first', 'second', 'third'], $sourceLog);
        self::assertSame([1, 2, 3], $predicateLog);
    }

    public function testTakingWhileDoesNotInvokeThePredicateForAnEmptyInput(): void
    {
        $predicateCalls = 0;
        $taken = takingWhile(static function (mixed $value) use (&$predicateCalls): bool {
            $predicateCalls++;

            return true;
        });

        self::assertSame([], iterator_to_array($taken([])));
        self::assertSame(0, $predicateCalls);
    }

    public function testTakingWhileUsesPhpTruthinessAndStopsAtTheFirstFalseyResult(): void
    {
        $predicateLog = [];
        // @phpstan-ignore argument.type (Runtime truthiness is intentionally exercised.)
        $taken = takingWhile(static function (int $value) use (&$predicateLog): mixed {
            $predicateLog[] = $value;

            return match ($value) {
                1 => 'yes',
                2 => '0',
                default => true,
            };
        });

        self::assertSame([1], iterator_to_array($taken([1, 2, 3])));
        self::assertSame([1, 2], $predicateLog);
    }

    public function testTakingWhileDefersSourceAndPredicateUntilDownstreamRequestsAValue(): void
    {
        $sourceLog = [];
        $predicateLog = [];
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'first' => 1;
            $sourceLog[] = 'second';
            throw new RuntimeException('The second value was requested.');
        };
        $taken = takingWhile(static function (int $value) use (&$predicateLog): bool {
            $predicateLog[] = $value;

            return true;
        });

        $takenValues = $taken($source());

        self::assertSame([], $sourceLog);
        self::assertSame([], $predicateLog);
        self::assertSame(1, first()($takenValues));
        self::assertSame(['first'], $sourceLog);
        self::assertSame([1], $predicateLog);
    }

    public function testTakingWhilePropagatesSourceIteratorExceptionsWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('Source iterator failure.');
        $source = static function () use ($failure): iterable {
            yield 'first' => 1;
            throw $failure;
        };

        try {
            iterator_to_array(takingWhile(static fn(int $value): bool => true)($source()));
            self::fail('The source iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    public function testTakingWhilePropagatesPredicateExceptionsWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('Predicate failure.');
        $taken = takingWhile(static function (int $value) use ($failure): bool {
            throw $failure;
        });

        try {
            iterator_to_array($taken([1]));
            self::fail('The predicate exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
