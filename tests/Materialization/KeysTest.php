<?php

declare(strict_types=1);

namespace Nagare\Tests\Materialization;

use PHPUnit\Framework\TestCase;

use function Nagare\Terminal\Materialization\keys;

final class KeysTest extends TestCase
{
    public function testKeysReturnsAnEmptyListForEmptyInput(): void
    {
        self::assertSame([], keys()([]));
    }

    public function testKeysPreservesInputKeysInOrderIncludingArbitraryKeysAndRepeats(): void
    {
        $first = new \stdClass();
        $second = new \stdClass();
        $source = static function () use ($first, $second): iterable {
            yield $first => 'first';
            yield 'repeated' => 'second';
            yield $second => 'third';
            yield 'repeated' => 'last';
        };

        self::assertSame([$first, 'repeated', $second, 'repeated'], keys()($source()));
    }

    public function testKeysTerminalCanBeReused(): void
    {
        $terminal = keys();

        self::assertSame(['first'], $terminal(['first' => 1]));
        self::assertSame(['second'], $terminal(['second' => 2]));
    }
}
