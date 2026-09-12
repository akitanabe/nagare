<?php

declare(strict_types=1);

namespace Nagare\Tests\Materialization;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Materialization\values;

final class ValuesTest extends TestCase
{
    public function testValuesReturnsAnEmptyListForEmptyInput(): void
    {
        self::assertSame([], values()([]));
    }

    public function testValuesIgnoresKeysFromAnyIterable(): void
    {
        $key = new \stdClass();
        $source = static function () use ($key): iterable {
            yield $key => 'first';
            yield 'ignored' => 'second';
        };

        self::assertSame(['first', 'second'], values()($source()));
    }

    public function testIteratorExceptionsPropagateWithoutBeingSwallowed(): void
    {
        $failure = new RuntimeException('iterator failure');
        $source = static function () use ($failure): iterable {
            yield 'first' => 'first value';
            throw $failure;
        };

        try {
            values()($source());
            self::fail('The iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
