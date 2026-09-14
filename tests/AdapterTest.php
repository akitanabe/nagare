<?php

declare(strict_types=1);

namespace Nagare\Tests;

use Nagare\Adapter\Definition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Nagare\Adapter\defaults;
use function Nagare\Adapter\defaultsOr;
use function Nagare\Adapter\filter;
use function Nagare\Adapter\filterMap;
use function Nagare\Adapter\map;
use function Nagare\Adapter\some;
use function Nagare\Adapter\then;
use function Nagare\Materialization\entries;
use function Nagare\Materialization\keys;
use function Nagare\Materialization\values;

final class AdapterTest extends TestCase
{
    public function testEveryFactoryReturnsTheSameReusableDefinitionType(): void
    {
        self::assertEmptyResult(map(static fn(int $value): int => $value + 1));
        self::assertEmptyResult(then(static fn(int $value): bool => $value > 0));
        self::assertEmptyResult(defaults(0));
        self::assertEmptyResult(defaultsOr(static fn(): int => 0));
        self::assertEmptyResult(filter(static fn(int $value): bool => $value > 0));
        self::assertEmptyResult(some());
        self::assertEmptyResult(filterMap(static fn(int $value): ?int => $value > 0 ? $value : null));
    }

    public function testMappingAdaptersComposeLeftToRightWithoutRunningCallbacksUntilInputArrives(): void
    {
        $log = [];
        $adapter = map(static function (int $value) use (&$log): int {
            $log[] = ['map', $value];

            return $value + 1;
        })
            |> then(static function (int $value) use (&$log): bool {
                $log[] = ['then', $value];

                return ($value % 2) === 0;
            })
            |> defaultsOr(static function () use (&$log): int {
                $log[] = ['default'];

                return 10;
            })
            |> map(static function (int $value) use (&$log): int {
                $log[] = ['last', $value];

                return $value * 2;
            });

        self::assertSame([], $log);
        $terminal = $adapter |> values()->apply();
        self::assertSame([], $log);
        self::assertSame([], $terminal([]));
        self::assertSame([], $log);
        self::assertSame([4, 20], $terminal([1, 2]));
        self::assertSame(
            [
                ['map', 1],
                ['then', 2],
                ['last', 2],
                ['map', 2],
                ['then', 3],
                ['default'],
                ['last', 10],
            ],
            $log,
        );
    }

    #[DataProvider('presentValues')]
    public function testDefaultsReplaceOnlyNullAndDefaultsOrIsLazy(mixed $present): void
    {
        $calls = 0;
        $fixed = defaults('fallback') |> values()->apply();
        $factory = defaultsOr(static function () use (&$calls): string {
            $calls++;

            return 'generated';
        })
            |> values()->apply();

        self::assertSame([$present, 'fallback'], $fixed([$present, null]));
        self::assertSame([$present, 'generated'], $factory([$present, null]));
        self::assertSame(1, $calls);
    }

    public function testDefaultsOrCallsItsFactoryOnceForEachNullInOneInvocation(): void
    {
        $calls = 0;
        $terminal = defaultsOr(static function () use (&$calls): string {
            $calls++;

            return "fallback-{$calls}";
        })
            |> values()->apply();

        self::assertSame(['fallback-1', false, 'fallback-2', 0, 'fallback-3'], $terminal([null, false, null, 0, null]));
        self::assertSame(3, $calls);
    }

    /** @return iterable<string, array{mixed}> */
    public static function presentValues(): iterable
    {
        yield 'false' => [false];
        yield 'zero' => [0];
        yield 'zero float' => [0.0];
        yield 'empty string' => [''];
    }

    public function testThenReturnsNullWhileFilterRejectsFalsyPredicateResults(): void
    {
        $truthiness = static fn(int $value): mixed => match ($value) {
            1 => 'yes',
            2 => 0,
            default => null,
        };

        self::assertSame([1, null, null], (then($truthiness) |> values()->apply())([1, 2, 3]));
        // @phpstan-ignore argument.type (Non-boolean results intentionally exercise runtime truthiness.)
        self::assertSame([1], (filter($truthiness) |> values()->apply())([1, 2, 3]));
    }

    public function testSomeRejectsOnlyNullAndPreservesKeysAndOrder(): void
    {
        $source = static function (): iterable {
            yield 'null' => null;
            yield 'false' => false;
            yield 'zero' => 0;
            yield 'empty' => '';
        };
        $adapter = some();

        self::assertSame([false, 0, ''], ($adapter |> values()->apply())($source()));
        self::assertSame(['false', 'zero', 'empty'], ($adapter |> keys()->apply())($source()));
        self::assertSame([['false', false], ['zero', 0], ['empty', '']], ($adapter |> entries()->apply())($source()));
    }

    public function testFilterMapTraversesOnceMapsEachItemOnceAndAcceptsEachNonNullResultOnce(): void
    {
        $traversals = 0;
        $reads = 0;
        $calls = [];
        $accepted = [];
        $adapter = filterMap(static function (int $value) use (&$calls): ?string {
            $calls[] = $value;

            return ($value % 2) === 0 ? "v{$value}" : null;
        });
        $terminal = $adapter
            |> values()->hook(static function (mixed $value, mixed $key) use (&$accepted): void {
                $accepted[] = [$key, $value];
            })->apply();
        $source = static function () use (&$traversals, &$reads): iterable {
            $traversals++;

            foreach (['first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4] as $key => $value) {
                $reads++;
                yield $key => $value;
            }
        };

        self::assertSame(['v2', 'v4'], $terminal($source()));
        self::assertSame(1, $traversals);
        self::assertSame(4, $reads);
        self::assertSame([1, 2, 3, 4], $calls);
        self::assertSame([['second', 'v2'], ['fourth', 'v4']], $accepted);
    }

    public function testFilterMapRejectsOnlyNullOutputsAndPreservesFalsyOutputsWithTheirKeys(): void
    {
        $terminal = filterMap(static fn(string $value): mixed => match ($value) {
            'false' => false,
            'zero' => 0,
            'zero-float' => 0.0,
            'empty-string' => '',
            default => null,
        })
            |> entries()->apply();

        self::assertSame(
            [
                ['false-key',        false],
                ['zero-key',         0],
                ['zero-float-key',   0.0],
                ['empty-string-key', ''],
            ],
            $terminal([
                'false-key' => 'false',
                'zero-key' => 'zero',
                'zero-float-key' => 'zero-float',
                'empty-string-key' => 'empty-string',
                'null-key' => 'null',
            ]),
        );
    }

    /**
     * @template TInput
     * @template TOutput
     * @param Definition<TInput, TOutput> $definition
     */
    private static function assertEmptyResult(Definition $definition): void
    {
        self::assertInstanceOf(Definition::class, $definition);
        self::assertSame([], ($definition |> values()->apply())([]));
    }
}
