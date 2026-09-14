<?php

declare(strict_types=1);

namespace Nagare\Tests\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Terminal\Query\find;
use function Nagare\Terminal\Query\first;
use function Nagare\Terminal\Query\last;

final class QueryTest extends TestCase
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

    public function testLastReturnsTheLastInputValueAndNullForEmptyInput(): void
    {
        $last = last();

        self::assertSame('last', $last(['first', null, 'last']));
        self::assertNull($last(['first', null]));
        // @phpstan-ignore staticMethod.alreadyNarrowedType (The empty-input result must also be verified at runtime.)
        self::assertNull($last([]));
    }

    public function testFindReturnsTheFirstMatchingInputValueWithoutRequestingAnotherValue(): void
    {
        $source = static function (): iterable {
            yield 'first' => 1;
            yield 'match' => 2;
            throw new RuntimeException('third element was requested');
        };

        self::assertSame(2, find(static fn(int $value): bool => $value === 2)($source()));
    }

    public function testFindReturnsNullWhenNoValueMatchesOrInputIsEmpty(): void
    {
        $find = find(static fn(int $value): bool => $value > 10);

        self::assertNull($find([1, 2, 3]));
        // @phpstan-ignore staticMethod.alreadyNarrowedType (The empty-input result must also be verified at runtime.)
        self::assertNull($find([]));
    }

    public function testQueryTerminalsCanBeReusedForIndependentInputs(): void
    {
        $last = last();
        $find = find(static fn(int $value): bool => $value > 0);

        self::assertSame(2, $last([1, 2]));
        self::assertSame(4, $last([3, 4]));
        self::assertSame(1, $find([1, -1]));
        self::assertNull($find([-1, -2]));
    }

    public function testQueryCallbackExceptionsPropagateWithTheirOriginalIdentity(): void
    {
        $predicateFailure = new RuntimeException('predicate failure');
        $find = find(static function (int $value) use ($predicateFailure): bool {
            if ($value === 2) {
                throw $predicateFailure;
            }

            return false;
        });

        try {
            $find([1, 2]);
            self::fail('The predicate exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($predicateFailure, $thrown);
        }
    }
}
