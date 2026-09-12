<?php

declare(strict_types=1);

namespace Nagare\Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Materialization\values;
use function Nagare\Pipeline\flatMapping;
use function Nagare\Selection\first;

final class FlatMappingTest extends TestCase
{
    public function testFlatMappingEmptyInputDoesNotInvokeTheMapper(): void
    {
        $seen = [];
        $flattened = flatMapping(static function (mixed $value) use (&$seen): iterable {
            $seen[] = $value;

            return [];
        });

        self::assertSame([], values()($flattened([])));
        self::assertSame([], $seen);
    }

    public function testFlatMappingYieldsInnerValuesInInputAndInnerOrderWithTheirKeys(): void
    {
        $source = static function (): iterable {
            yield 'outer-first' => 1;
            yield 'outer-second' => 2;
        };
        $flattened = flatMapping(static fn(int $value): iterable => match ($value) {
            1 => ['same' => 'first', 0 => 'second'],
            2 => ['same' => 'third'],
            default => [],
        });

        $entries = [];
        foreach ($flattened($source()) as $key => $value) {
            $entries[] = [$key, $value];
        }

        self::assertSame(
            [
                ['same', 'first'],
                [0, 'second'],
                ['same', 'third'],
            ],
            $entries,
        );
    }

    public function testFlatMappingSkipsEmptyMappedIterablesAndContinuesWithLaterInputs(): void
    {
        $flattened = flatMapping(static fn(int $value): iterable => match ($value) {
            1 => [],
            2 => ['second' => 'value'],
            default => ['third' => 'value'],
        });

        $entries = [];
        foreach ($flattened([1, 2, 3]) as $key => $value) {
            $entries[] = [$key, $value];
        }

        self::assertSame(
            [
                ['second', 'value'],
                ['third', 'value'],
            ],
            $entries,
        );
    }

    public function testFlatMappingDefersSourceMapperAndInnerIteratorUntilDownstreamRequestsAValue(): void
    {
        $sourceLog = [];
        $mapperLog = [];
        $innerLog = [];
        $flattened = flatMapping(static function (string $value) use (&$mapperLog, &$innerLog): iterable {
            $mapperLog[] = $value;

            return (static function () use (&$innerLog): iterable {
                $innerLog[] = 'first';
                yield 'inner' => 'result';
                $innerLog[] = 'second';
                throw new RuntimeException('second inner value was requested');
            })();
        });
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'outer' => 'input';
            $sourceLog[] = 'second';
            throw new RuntimeException('second outer value was requested');
        };

        $flattenedValues = $flattened($source());

        self::assertSame([], $sourceLog);
        self::assertSame([], $mapperLog);
        self::assertSame([], $innerLog);
        self::assertSame('result', first()($flattenedValues));
        self::assertSame(['first'], $sourceLog);
        self::assertSame(['input'], $mapperLog);
        self::assertSame(['first'], $innerLog);
    }

    public function testFlatMappingPropagatesSourceIteratorExceptions(): void
    {
        $failure = new RuntimeException('source iterator failure');
        $flattened = flatMapping(static fn(int $value): iterable => [$value]);
        $source = static function () use ($failure): iterable {
            yield 1;
            throw $failure;
        };

        try {
            values()($flattened($source()));
            self::fail('The source iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    public function testFlatMappingPropagatesMapperExceptions(): void
    {
        $failure = new RuntimeException('mapper failure');
        $flattened = flatMapping(static function (int $value) use ($failure): iterable {
            if ($value === 1) {
                throw $failure;
            }

            return [$value => 'unreachable'];
        });

        try {
            values()($flattened([1]));
            self::fail('The mapper exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    public function testFlatMappingPropagatesInnerIteratorExceptions(): void
    {
        $failure = new RuntimeException('inner iterator failure');
        $flattened = flatMapping(static function (int $value) use ($failure): iterable {
            return (static function () use ($failure, $value): iterable {
                yield $value;
                throw $failure;
            })();
        });

        try {
            values()($flattened([1]));
            self::fail('The inner iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
