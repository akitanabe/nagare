<?php

declare(strict_types=1);

namespace Nagare\Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Materialization\values;
use function Nagare\Pipeline\dropping;
use function Nagare\Query\first;

final class DroppingTest extends TestCase
{
    public function testDroppingSkipsTheRequestedValuesAndPreservesTheRemainingKeysAndOrder(): void
    {
        $remainingKey = new \stdClass();
        $source = static function () use ($remainingKey): iterable {
            yield 'first' => 'first';
            yield 'second' => 'second';
            yield $remainingKey => 'third';
            yield 'fourth' => 'fourth';
        };

        $entries = [];
        foreach (dropping(2)($source()) as $key => $value) {
            $entries[] = [$key, $value];
        }

        self::assertSame(
            [
                [$remainingKey, 'third'],
                ['fourth',      'fourth'],
            ],
            $entries,
        );
    }

    public function testDroppingDoesNotSkipValuesForNonPositiveCounts(): void
    {
        $zero = [];
        foreach (dropping(0)(['first' => 1, 'second' => 2]) as $key => $value) {
            $zero[$key] = $value;
        }

        $negative = [];
        foreach (dropping(-1)(['first' => 1, 'second' => 2]) as $key => $value) {
            $negative[$key] = $value;
        }

        self::assertSame(['first' => 1, 'second' => 2], $zero);
        self::assertSame(['first' => 1, 'second' => 2], $negative);
    }

    public function testDroppingDefersSourceConsumptionUntilDownstreamRequestsAValue(): void
    {
        $sourceLog = [];
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'first' => 1;
            $sourceLog[] = 'second';
            yield 'second' => 2;
            $sourceLog[] = 'third';
            yield 'third' => 3;
        };

        $dropped = dropping(2)($source());

        self::assertSame([], $sourceLog);
        self::assertSame(3, first()($dropped));
        self::assertSame(['first', 'second', 'third'], $sourceLog);
    }

    public function testDroppingConsumesOnlyValuesNeededByDownstream(): void
    {
        $source = static function (): iterable {
            yield 'first' => 1;
            yield 'second' => 2;
            yield 'third' => 3;
            throw new RuntimeException('fourth value was requested');
        };

        self::assertSame(3, first()(dropping(2)($source())));
    }

    public function testDroppingPropagatesSourceIteratorExceptionsWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('iterator failure');
        $source = static function () use ($failure): iterable {
            yield 'first' => 1;
            throw $failure;
        };

        try {
            values()(dropping(2)($source()));
            self::fail('The iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    public function testDroppingReturnsAnEmptyIterableWhenTheSourceHasNoRemainingValues(): void
    {
        self::assertSame([], values()(dropping(2)(['first' => 1])));
    }
}
