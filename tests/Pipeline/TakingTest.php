<?php

declare(strict_types=1);

namespace Nagare\Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Pipeline\taking;
use function Nagare\Terminal\Materialization\values;
use function Nagare\Terminal\Query\first;

final class TakingTest extends TestCase
{
    public function testTakingReturnsAtMostTheRequestedValuesInOrderWithTheirKeys(): void
    {
        $firstKey = new \stdClass();
        $source = static function () use ($firstKey): iterable {
            yield $firstKey => 'first';
            yield 'second' => 'second';
            yield 'third' => 'third';
        };

        $entries = [];
        foreach (taking(2)($source()) as $key => $value) {
            $entries[] = [$key, $value];
        }

        self::assertSame([[$firstKey, 'first'], ['second', 'second']], $entries);
    }

    public function testTakingDoesNotConsumeTheSourceForNonPositiveCounts(): void
    {
        $sourceLog = [];
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'started';
            yield 'key' => 'value';
        };

        self::assertSame([], values()(taking(0)($source())));
        self::assertSame([], $sourceLog);

        self::assertSame([], values()(taking(-1)($source())));
        self::assertSame([], $sourceLog);
    }

    public function testTakingDoesNotRequestAnotherSourceValueAfterReachingTheCount(): void
    {
        $source = static function (): iterable {
            yield 'first' => 1;
            yield 'second' => 2;
            throw new RuntimeException('third value was requested');
        };

        self::assertSame([1, 2], values()(taking(2)($source())));
    }

    public function testTakingDefersSourceConsumptionUntilDownstreamRequestsAValue(): void
    {
        $sourceLog = [];
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'first' => 1;
            $sourceLog[] = 'second';
            yield 'second' => 2;
        };

        $taken = taking(2)($source());

        self::assertSame([], $sourceLog);
        self::assertSame(1, first()($taken));
        self::assertSame(['first'], $sourceLog);
    }

    public function testTakingPropagatesSourceIteratorExceptionsBeforeTheCount(): void
    {
        $failure = new RuntimeException('iterator failure');
        $source = static function () use ($failure): iterable {
            yield 'first' => 1;
            throw $failure;
        };

        try {
            values()(taking(2)($source()));
            self::fail('The iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    public function testTakingReturnsAllValuesWhenTheSourceHasFewerValuesThanTheCount(): void
    {
        self::assertSame([1], values()(taking(2)(['first' => 1])));
    }

    public function testTakingHandlesAnEmptySource(): void
    {
        $sourceLog = [];
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'started';
            yield from [];
        };

        self::assertSame([], values()(taking(2)($source())));
        self::assertSame(['started'], $sourceLog);
    }
}
