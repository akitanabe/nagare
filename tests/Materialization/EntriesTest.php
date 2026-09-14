<?php

declare(strict_types=1);

namespace Nagare\Tests\Materialization;

use PHPUnit\Framework\TestCase;

use function Nagare\Terminal\Materialization\entries;

final class EntriesTest extends TestCase
{
    public function testEntriesReturnsAnEmptyListForEmptyInput(): void
    {
        self::assertSame([], entries()([]));
    }

    public function testEntriesPreservesKeysAndValuesInOrderIncludingArbitraryKeysAndRepeats(): void
    {
        $key = new \stdClass();
        $source = static function () use ($key): iterable {
            yield $key => 'first';
            yield 'repeated' => 'second';
            yield 'repeated' => 'last';
        };

        self::assertSame(
            [
                [$key,       'first'],
                ['repeated', 'second'],
                ['repeated', 'last'],
            ],
            entries()($source()),
        );
    }

    public function testEntriesTerminalCanBeReused(): void
    {
        $terminal = entries();

        self::assertSame([['first', 1]], $terminal(['first' => 1]));
        self::assertSame([['second', 2]], $terminal(['second' => 2]));
    }
}
