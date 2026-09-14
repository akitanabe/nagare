<?php

declare(strict_types=1);

namespace Nagare\Tests\Aggregation;

use Nagare\Terminal;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use TypeError;

use function Nagare\Aggregation\countBy;
use function Nagare\Aggregation\groupBy;
use function Nagare\Aggregation\pivot;
use function Nagare\Aggregation\unique;
use function Nagare\Aggregation\uniqueBy;

final class CollectionAggregationTest extends TestCase
{
    public function testUniqueKeepsTheFirstStrictlyEqualValueInInputOrder(): void
    {
        $firstObject = new stdClass();
        $equalObject = new stdClass();

        self::assertSame(
            [1, '1', 1.0, false, null, [1], $firstObject, $equalObject],
            unique()([1, '1', 1.0, false, null, [1], [1], $firstObject, $firstObject, $equalObject]),
        );
        self::assertSame([], unique()([]));
    }

    public function testUniqueByKeepsTheFirstValueForEachStrictlyEqualSelection(): void
    {
        $selected = [];
        $terminal = uniqueBy(static function (array $value) use (&$selected): mixed {
            $selected[] = $value['id'];

            return $value['id'];
        });
        $input = [
            'first' => ['id' => 1, 'name' => 'first integer'],
            10 => ['id' => 1, 'name' => 'duplicate of first'],
            20 => ['id' => '1', 'name' => 'first string'],
            'middle' => ['id' => 2, 'name' => 'middle integer'],
            40 => ['id' => 2, 'name' => 'duplicate of middle'],
        ];

        self::assertSame([$input['first'], $input[20], $input['middle']], $terminal($input));
        self::assertSame([1, 1, '1', 2, 2], $selected);

        $emptySelections = [];
        $empty = uniqueBy(static function (int $value) use (&$emptySelections): int {
            $emptySelections[] = $value;

            return $value;
        });
        self::assertSame([], $empty([]));
        self::assertSame([], $emptySelections);
    }

    public function testCountByUsesNativeArrayKeysAndKeepsGroupOrder(): void
    {
        $count = countBy(self::group(...));

        self::assertSame(
            ['b' => 2, 'a' => 1, 1 => 2],
            $count([
                ['group' => 'b'],
                ['group' => 'a'],
                ['group' => 'b'],
                ['group' => '1'],
                ['group' => 1],
            ]),
        );
        self::assertSame([], $count([]));
    }

    public function testGroupByUsesNativeArrayKeysAndKeepsGroupAndValueOrder(): void
    {
        $input = [
            ['group' => 'b', 'value' => 1],
            ['group' => 'a', 'value' => 2],
            ['group' => 'b', 'value' => 3],
            ['group' => '1', 'value' => 4],
            ['group' => 1, 'value' => 5],
        ];
        $group = groupBy(self::group(...));

        self::assertSame(
            [
                'b' => [$input[0], $input[2]],
                'a' => [$input[1]],
                1 => [$input[3], $input[4]],
            ],
            $group($input),
        );
        self::assertSame([], $group([]));
    }

    public function testSelectorExceptionsPropagateWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('selector failure');
        $selector = static function (int $value) use ($failure): int {
            if ($value === 2) {
                throw $failure;
            }

            return $value;
        };

        self::assertSelectorFailure($failure, uniqueBy($selector));
        self::assertSelectorFailure($failure, countBy($selector));
        self::assertSelectorFailure($failure, groupBy($selector));
    }

    public function testCountByPreservesNativeInvalidArrayKeyFailure(): void
    {
        // @phpstan-ignore argument.type, argument.templateType (An object selector result intentionally exercises PHP's native invalid array-key failure.)
        $terminal = countBy(static fn(int $value): object => (object) ['value' => $value]);

        $this->expectException(TypeError::class);
        $terminal([1]);
    }

    public function testGroupByPreservesNativeInvalidArrayKeyFailure(): void
    {
        // @phpstan-ignore argument.type, argument.templateType (An object selector result intentionally exercises PHP's native invalid array-key failure.)
        $terminal = groupBy(static fn(int $value): object => (object) ['value' => $value]);

        $this->expectException(TypeError::class);
        $terminal([1]);
    }

    public function testDefinitionsAreReusableAndPivotReadsTheSourceOnce(): void
    {
        $unique = unique();
        $uniqueBy = uniqueBy(static fn(int $value): int => $value % 2);
        $countBy = countBy(static fn(int $value): int => $value % 2);
        $groupBy = groupBy(static fn(int $value): int => $value % 2);

        self::assertSame([1, 2], $unique([1, 1, 2]));
        self::assertSame([3], $unique([3, 3]));
        self::assertSame([1, 2], $uniqueBy([1, 3, 2]));
        self::assertSame([4, 5], $uniqueBy([4, 6, 5]));
        self::assertSame([1 => 2, 0 => 1], $countBy([1, 3, 2]));
        self::assertSame([0 => 2, 1 => 1], $countBy([4, 6, 5]));
        self::assertSame([1 => [1, 3], 0 => [2]], $groupBy([1, 3, 2]));
        self::assertSame([0 => [4, 6], 1 => [5]], $groupBy([4, 6, 5]));

        $reads = [];
        $source = static function () use (&$reads): iterable {
            foreach ([1, 2, 1] as $value) {
                $reads[] = $value;
                yield $value;
            }
        };

        self::assertSame(
            [
                'unique' => [1, 2],
                'uniqueBy' => [1, 2],
                'countBy' => [1 => 2, 0 => 1],
                'groupBy' => [1 => [1, 1], 0 => [2]],
            ],
            pivot(unique: $unique, uniqueBy: $uniqueBy, countBy: $countBy, groupBy: $groupBy)($source()),
        );
        self::assertSame([1, 2, 1], $reads);
    }

    /** @param array{group: int|string} $value */
    private static function group(array $value): int|string
    {
        return $value['group'];
    }

    /** @param Terminal<mixed, int, mixed> $terminal */
    private static function assertSelectorFailure(RuntimeException $failure, Terminal $terminal): void
    {
        try {
            $terminal([1, 2]);
            self::fail('The selector exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
