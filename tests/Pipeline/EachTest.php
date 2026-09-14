<?php

declare(strict_types=1);

namespace Nagare\Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Pipeline\each;
use function Nagare\Terminal\Materialization\values;
use function Nagare\Terminal\Query\first;

final class EachTest extends TestCase
{
    public function testEachEmptyInputDoesNotInvokeTheCallback(): void
    {
        $seen = [];
        $observed = each(static function (mixed $value, mixed $key) use (&$seen): void {
            $seen[] = [$key, $value];
        });

        self::assertSame([], values()($observed([])));
        self::assertSame([], $seen);
    }

    public function testEachPassesValuesAndKeysThroughInInputOrder(): void
    {
        $firstKey = new \stdClass();
        $firstValue = new \stdClass();
        $source = static function () use ($firstKey, $firstValue): iterable {
            yield $firstKey => $firstValue;
            yield 'second' => 2;
        };
        $seen = [];
        $observed = each(static function (mixed $value, mixed $key) use (&$seen): void {
            $seen[] = [$key, $value];
        });

        $entries = [];
        foreach ($observed($source()) as $key => $value) {
            $entries[] = [$key, $value];
        }

        self::assertSame(
            [
                [$firstKey, $firstValue],
                ['second',  2],
            ],
            $entries,
        );
        self::assertSame(
            [
                [$firstKey, $firstValue],
                ['second',  2],
            ],
            $seen,
        );
    }

    public function testEachOnlyProcessesValuesRequestedByDownstreamTerminal(): void
    {
        $sourceLog = [];
        $callbackLog = [];
        $observed = each(static function (int $value, string $key) use (&$callbackLog): void {
            $callbackLog[] = [$key, $value];
        });
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'first' => 1;
            $sourceLog[] = 'second';
            throw new RuntimeException('second value was requested');
        };

        self::assertSame(1, first()($observed($source())));
        self::assertSame(['first'], $sourceLog);
        self::assertSame([['first', 1]], $callbackLog);
    }

    public function testEachDefersSourceAndCallbackUntilDownstreamRequestsAValue(): void
    {
        $sourceLog = [];
        $callbackLog = [];
        $observed = each(static function (int $value, string $key) use (&$callbackLog): void {
            $callbackLog[] = [$key, $value];
        });
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'first' => 1;
        };

        $result = $observed($source());

        self::assertSame([], $sourceLog);
        self::assertSame([], $callbackLog);
        self::assertSame(1, first()($result));
        self::assertSame(['first'], $sourceLog);
        self::assertSame([['first', 1]], $callbackLog);
    }

    public function testEachPropagatesSourceIteratorExceptionsWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('source iterator failure');
        $observed = each(static function (int $value, int $key): void {});
        $source = static function () use ($failure): iterable {
            yield 1;
            throw $failure;
        };

        try {
            values()($observed($source()));
            self::fail('The iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    public function testEachPropagatesCallbackExceptionsWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('each callback failure');
        $observed = each(static function (int $value, int $key) use ($failure): void {
            throw $failure;
        });

        try {
            values()($observed([1]));
            self::fail('The callback exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
