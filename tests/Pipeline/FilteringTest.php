<?php

declare(strict_types=1);

namespace Nagare\Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Materialization\values;
use function Nagare\Pipeline\filtering;
use function Nagare\Selection\first;

final class FilteringTest extends TestCase
{
    public function testFilteringEmptyInputDoesNotInvokeThePredicate(): void
    {
        $seen = [];
        $filtered = filtering(static function (mixed $value) use (&$seen): bool {
            $seen[] = $value;

            return true;
        });

        self::assertSame([], values()($filtered([])));
        self::assertSame([], $seen);
    }

    public function testFilteringKeepsPredicateAcceptedValuesInOrderWithTheirKeys(): void
    {
        $firstKey = new \stdClass();
        $source = static function () use ($firstKey): iterable {
            yield $firstKey => 1;
            yield 'false' => 0;
            yield 'true' => 2;
        };

        $entries = [];
        foreach (filtering(static fn(int $value): bool => $value > 0)($source()) as $key => $value) {
            $entries[] = [$key, $value];
        }

        self::assertSame([[$firstKey, 1], ['true', 2]], $entries);
    }

    public function testFilteringUsesPhpTruthinessForPredicateResults(): void
    {
        $values = [1, 2, 3, 4];
        // @phpstan-ignore argument.type (Runtime truthiness is intentionally exercised.)
        $filtered = filtering(static fn(int $value): mixed => match ($value) {
            1 => 'yes',
            2 => '0',
            3 => 1,
            default => null,
        });

        self::assertSame([1, 3], values()($filtered($values)));
    }

    public function testFilteringDefersSourceAndPredicateUntilDownstreamRequestsFirstValue(): void
    {
        $sourceLog = [];
        $predicateLog = [];
        $filtered = filtering(static function (int $value) use (&$predicateLog): bool {
            $predicateLog[] = $value;

            return true;
        });
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'first' => 1;
            $sourceLog[] = 'second';
            throw new RuntimeException('second value was requested');
        };

        $filteredValues = $filtered($source());

        self::assertSame([], $sourceLog);
        self::assertSame([], $predicateLog);
        self::assertSame(1, first()($filteredValues));
        self::assertSame(['first'], $sourceLog);
        self::assertSame([1], $predicateLog);
    }

    public function testFilteringOnlyProcessesValuesRequestedByDownstreamTerminal(): void
    {
        $predicateLog = [];
        $filtered = filtering(static function (int $value) use (&$predicateLog): bool {
            $predicateLog[] = $value;

            return $value > 1;
        });
        $source = static function (): iterable {
            yield 'first' => 1;
            yield 'second' => 2;
            throw new RuntimeException('third value was requested');
        };

        self::assertSame(2, first()($filtered($source())));
        self::assertSame([1, 2], $predicateLog);
    }

    public function testFilteringPropagatesSourceIteratorExceptions(): void
    {
        $failure = new RuntimeException('iterator failure');
        $filtered = filtering(static fn(int $value): bool => $value > 0);
        $source = static function () use ($failure): iterable {
            yield 1;
            throw $failure;
        };

        try {
            values()($filtered($source()));
            self::fail('The iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    public function testFilteringPredicateExceptionsPropagateWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('filtering predicate failure');
        $filtered = filtering(static function (int $value) use ($failure): bool {
            throw $failure;
        });

        try {
            values()($filtered([1]));
            self::fail('The predicate exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
