<?php

declare(strict_types=1);

namespace Nagare\Tests\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Terminal\Query\isEmpty;

final class IsEmptyQueryTest extends TestCase
{
    #[DataProvider('falsyValues')]
    public function testIsEmptyReturnsFalseAfterObservingTheFirstValue(mixed $value): void
    {
        $source = static function () use ($value): iterable {
            yield 'first' => $value;
            throw new RuntimeException('second element was requested');
        };

        self::assertFalse(isEmpty()($source()));
    }

    /** @return iterable<string, array{mixed}> */
    public static function falsyValues(): iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'zero' => [0];
        yield 'empty string' => [''];
    }

    public function testIsEmptyReturnsTrueForEmptyInput(): void
    {
        self::assertTrue(isEmpty()([]));
    }

    public function testIsEmptyDefinitionsCanBeReusedForIndependentInputs(): void
    {
        $terminal = isEmpty();

        self::assertTrue($terminal([]));
        self::assertFalse($terminal([null]));
        self::assertTrue($terminal([]));
    }
}
