<?php

declare(strict_types=1);

namespace Nagare\Tests\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Query\first;

final class FirstTest extends TestCase
{
    #[DataProvider('falsyValues')]
    public function testFirstReturnsFalsyValuesWithoutRequestingASecondValue(mixed $value): void
    {
        $source = static function () use ($value): iterable {
            yield 'first' => $value;
            throw new RuntimeException('second element was requested');
        };

        self::assertSame($value, first()($source()));
    }

    /** @return iterable<string, array{mixed}> */
    public static function falsyValues(): iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'zero' => [0];
        yield 'empty string' => [''];
    }

    public function testFirstDoesNotRequestASecondSourceElement(): void
    {
        $source = static function (): iterable {
            yield 'first' => 'first value';
            throw new RuntimeException('second element was requested');
        };

        self::assertSame('first value', first()($source()));
    }

    public function testFirstReturnsNullForEmptyInput(): void
    {
        // @phpstan-ignore staticMethod.alreadyNarrowedType (The empty-input result must also be verified at runtime.)
        self::assertNull(first()([]));
    }
}
