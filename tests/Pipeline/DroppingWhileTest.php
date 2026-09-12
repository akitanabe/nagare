<?php

declare(strict_types=1);

namespace Nagare\Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Materialization\values;
use function Nagare\Pipeline\droppingWhile;
use function Nagare\Selection\first;

final class DroppingWhileTest extends TestCase
{
    public function testDroppingWhileYieldsTheFirstFalseyValueAndTheRemainingValuesWithTheirKeys(): void
    {
        $firstKey = new \stdClass();
        $source = static function () use ($firstKey): iterable {
            yield $firstKey => 1;
            yield 'second' => 2;
            yield 'third' => 3;
            yield 'fourth' => 4;
        };

        $entries = [];
        foreach (droppingWhile(static fn(int $value): bool => $value < 3)($source()) as $key => $value) {
            $entries[] = [$key, $value];
        }

        self::assertSame(
            [
                ['third', 3],
                ['fourth', 4],
            ],
            $entries,
        );
    }

    public function testDroppingWhileDoesNotInvokeThePredicateAfterTheFirstFalseyResult(): void
    {
        $predicateLog = [];
        $dropped = droppingWhile(static function (int $value) use (&$predicateLog): bool {
            $predicateLog[] = $value;

            return $value < 3;
        });

        self::assertSame([3, 4], values()($dropped([1, 2, 3, 4])));
        self::assertSame([1, 2, 3], $predicateLog);
    }

    public function testDroppingWhileDoesNotInvokeThePredicateForAnEmptyInput(): void
    {
        $predicateCalls = 0;
        $dropped = droppingWhile(static function (mixed $value) use (&$predicateCalls): bool {
            $predicateCalls++;

            return true;
        });

        self::assertSame([], values()($dropped([])));
        self::assertSame(0, $predicateCalls);
    }

    public function testDroppingWhileUsesPhpTruthinessForPredicateResults(): void
    {
        $predicateLog = [];
        // @phpstan-ignore argument.type (Runtime truthiness is intentionally exercised.)
        $dropped = droppingWhile(static function (int $value) use (&$predicateLog): mixed {
            $predicateLog[] = $value;

            return match ($value) {
                1 => 'yes',
                2 => '0',
                default => true,
            };
        });

        self::assertSame([2, 3, 4], values()($dropped([1, 2, 3, 4])));
        self::assertSame([1, 2], $predicateLog);
    }

    public function testDroppingWhileDefersSourceAndPredicateUntilDownstreamRequestsAValue(): void
    {
        $sourceLog = [];
        $predicateLog = [];
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'first' => 1;
            $sourceLog[] = 'second';
            throw new RuntimeException('The second value was requested.');
        };
        $dropped = droppingWhile(static function (int $value) use (&$predicateLog): bool {
            $predicateLog[] = $value;

            return false;
        });

        $droppedValues = $dropped($source());

        self::assertSame([], $sourceLog);
        self::assertSame([], $predicateLog);
        self::assertSame(1, first()($droppedValues));
        self::assertSame(['first'], $sourceLog);
        self::assertSame([1], $predicateLog);
    }

    public function testDroppingWhileConsumesOnlyValuesNeededByDownstreamTerminal(): void
    {
        $predicateLog = [];
        $dropped = droppingWhile(static function (int $value) use (&$predicateLog): bool {
            $predicateLog[] = $value;

            return $value < 3;
        });
        $source = static function (): iterable {
            yield 'first' => 1;
            yield 'second' => 2;
            yield 'third' => 3;
            throw new RuntimeException('The fourth value was requested.');
        };

        self::assertSame(3, first()($dropped($source())));
        self::assertSame([1, 2, 3], $predicateLog);
    }

    public function testDroppingWhilePropagatesSourceIteratorExceptionsWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('Source iterator failure.');
        $source = static function () use ($failure): iterable {
            yield 'first' => 1;
            throw $failure;
        };

        try {
            values()(droppingWhile(static fn(int $value): bool => true)($source()));
            self::fail('The source iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    public function testDroppingWhilePropagatesPredicateExceptionsWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('Predicate failure.');
        $dropped = droppingWhile(static function (int $value) use ($failure): bool {
            throw $failure;
        });

        try {
            values()($dropped([1]));
            self::fail('The predicate exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
