<?php

declare(strict_types=1);

namespace Nagare\Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Pipeline\distinct;
use function Nagare\Query\first;

final class DistinctTest extends TestCase
{
    public function testDistinctYieldsFirstStrictlyDistinctValuesWithTheirKeysAndOrder(): void
    {
        $firstObject = new \stdClass();
        $secondObject = new \stdClass();
        $source = [
            'integer' => 1,
            'string' => '1',
            'boolean' => true,
            'integer-again' => 1,
            'first-object' => $firstObject,
            'same-object' => $firstObject,
            'second-object' => $secondObject,
        ];

        $entries = [];
        foreach (distinct()($source) as $key => $value) {
            $entries[] = [$key, $value];
        }

        self::assertSame(
            [
                ['integer',       1],
                ['string',        '1'],
                ['boolean',       true],
                ['first-object',  $firstObject],
                ['second-object', $secondObject],
            ],
            $entries,
        );
    }

    public function testDistinctDefersSourceConsumptionAndConsumesOnlyValuesNeededByDownstream(): void
    {
        $sourceLog = [];
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'first' => 1;
            $sourceLog[] = 'second';
            yield 'second' => 1;
            $sourceLog[] = 'third';
            yield 'third' => 2;
            $sourceLog[] = 'fourth';
            throw new RuntimeException('The fourth value was requested.');
        };

        $distinct = distinct()($source());

        self::assertSame([], $sourceLog);
        self::assertSame(1, first()($distinct));
        self::assertSame(['first'], $sourceLog);
    }

    public function testDistinctStopsAfterYieldingTheSecondDistinctValueWithoutRequestingTheFourthInput(): void
    {
        $sourceLog = [];
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'first' => 1;
            $sourceLog[] = 'second';
            yield 'second' => 1;
            $sourceLog[] = 'third';
            yield 'third' => 2;
            $sourceLog[] = 'fourth';
            throw new RuntimeException('The fourth value was requested.');
        };

        $entries = [];
        foreach (distinct()($source()) as $key => $value) {
            $entries[] = [$key, $value];
            if (count($entries) === 2) {
                break;
            }
        }

        self::assertSame([['first', 1], ['third', 2]], $entries);
        self::assertSame(['first', 'second', 'third'], $sourceLog);
    }

    public function testDistinctPropagatesSourceIteratorExceptionsWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('Source iterator failure.');
        $source = static function () use ($failure): iterable {
            yield 'first' => 1;
            throw $failure;
        };

        try {
            iterator_to_array(distinct()($source()));
            self::fail('The source iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
