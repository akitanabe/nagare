<?php

declare(strict_types=1);

namespace Nagare\Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Pipeline\mapping;
use function Nagare\Terminal\Materialization\values;
use function Nagare\Terminal\Query\first;

final class MappingTest extends TestCase
{
    public function testMappingEmptyInputDoesNotInvokeTheMapper(): void
    {
        $seen = [];
        $mapped = mapping(static function (mixed $value) use (&$seen): mixed {
            $seen[] = $value;

            return $value;
        });

        self::assertSame([], values()($mapped([])));
        self::assertSame([], $seen);
    }

    public function testMappingIsLazyAndPreservesKeysAndOrder(): void
    {
        $calls = [];
        $firstKey = new \stdClass();
        $source = static function () use ($firstKey): iterable {
            yield $firstKey => 1;
            yield 'second' => 2;
        };
        $mapped = mapping(static function (int $value) use (&$calls): int {
            $calls[] = $value;

            return $value * 2;
        })($source());

        self::assertSame([], $calls);

        $entries = [];
        foreach ($mapped as $key => $value) {
            $entries[] = [$key, $value];
        }

        self::assertSame([[$firstKey, 2], ['second', 4]], $entries);
        self::assertSame([1, 2], $calls);
    }

    public function testMappingOnlyProcessesValuesRequestedByDownstreamTerminal(): void
    {
        $calls = [];
        $mapped = mapping(static function (int $value) use (&$calls): int {
            $calls[] = $value;

            return $value * 2;
        });
        $source = static function (): iterable {
            yield 'first' => 1;
            throw new RuntimeException('second value was requested');
        };

        self::assertSame(2, first()($mapped($source())));
        self::assertSame([1], $calls);
    }

    public function testMappingPropagatesSourceIteratorExceptions(): void
    {
        $failure = new RuntimeException('iterator failure');
        $mapped = mapping(static fn(int $value): int => $value);
        $source = static function () use ($failure): iterable {
            yield 1;
            throw $failure;
        };

        try {
            values()($mapped($source()));
            self::fail('The iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    public function testMappingDefersSourceAndMapperUntilDownstreamRequestsFirstValue(): void
    {
        $sourceLog = [];
        $mapperLog = [];
        $mapped = mapping(static function (int $value) use (&$mapperLog): int {
            $mapperLog[] = $value;

            return $value * 2;
        });
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'first' => 1;
            $sourceLog[] = 'second';
            throw new RuntimeException('second value was requested');
        };

        $mappedValues = $mapped($source());

        self::assertSame([], $sourceLog);
        self::assertSame([], $mapperLog);
        self::assertSame(2, first()($mappedValues));
        self::assertSame(['first'], $sourceLog);
        self::assertSame([1], $mapperLog);
    }

    public function testMappingCallbackExceptionsPropagateWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('mapping callback failure');
        $mapped = mapping(static function (int $value) use ($failure): int {
            throw $failure;
        });

        try {
            values()($mapped([1]));
            self::fail('The mapping callback exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
