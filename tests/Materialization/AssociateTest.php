<?php

declare(strict_types=1);

namespace Nagare\Tests\Materialization;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Terminal\Materialization\associate;

final class AssociateTest extends TestCase
{
    public function testAssociatePreservesArrayKeysWithoutASelector(): void
    {
        self::assertSame(['first' => 1, 2 => 'second'], associate()(['first' => 1, 2 => 'second']));
    }

    public function testAssociateReturnsAnEmptyArrayForEmptyInput(): void
    {
        self::assertSame([], associate()([]));
    }

    public function testAssociateKeepsTheLastValueForRepeatedInputKeys(): void
    {
        $source = static function (): iterable {
            yield 'repeated' => 'first';
            yield 'repeated' => 'last';
        };

        self::assertSame(['repeated' => 'last'], associate()($source()));
    }

    public function testAssociateSelectorReceivesValueAndKeyAndUsesTheLastValueForDuplicateKeys(): void
    {
        $seen = [];
        $source = static function (): iterable {
            yield 'first' => 1;
            yield 'second' => 2;
            yield 'third' => 3;
        };
        $terminal = associate(static function (int $value, string $key) use (&$seen): string {
            $seen[] = [$value, $key];

            return ($value % 2) === 0 ? 'duplicate' : 'unique';
        });

        self::assertSame(['unique' => 3, 'duplicate' => 2], $terminal($source()));
        self::assertSame([[1, 'first'], [2, 'second'], [3, 'third']], $seen);
    }

    public function testAssociateSelectorExceptionsPropagate(): void
    {
        $failure = new RuntimeException('selector failure');
        $terminal = associate(static function (int $value) use ($failure): string {
            if ($value === 2) {
                throw $failure;
            }

            return (string) $value;
        });

        try {
            $terminal([1, 2]);
            self::fail('The selector exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    public function testAssociateTerminalCanBeReused(): void
    {
        $terminal = associate();

        self::assertSame(['first' => 1], $terminal(['first' => 1]));
        self::assertSame(['second' => 2], $terminal(['second' => 2]));
    }

    public function testAssociateAllowsSelectorsThatIgnoreBothValueAndKey(): void
    {
        $terminal = associate(static fn(): string => 'same');

        self::assertSame(['same' => 2], $terminal([1, 2]));
        self::assertSame(['same' => 'last'], $terminal(['first', 'last']));
    }

    public function testAssociateConvertsSelectedNumericStringsToIntegerArrayKeys(): void
    {
        $terminal = associate(static fn(int $value): string => '123');

        self::assertSame([123 => 2], $terminal([1, 2]));
    }

    public function testAssociatePreservesOrReselectsKeysWithAnOptionalSelector(): void
    {
        $results = [];
        foreach ([null, static fn(int $value): string => "item_{$value}"] as $selector) {
            $results[] = associate($selector)([2, 3]);
        }

        self::assertSame([[2, 3], ['item_2' => 2, 'item_3' => 3]], $results);
    }

    public function testAssociatePropagatesNativeTypeErrorForObjectSelectorKeys(): void
    {
        // @phpstan-ignore argument.type (An object selector key intentionally exercises native array assignment failure.)
        $terminal = associate(static fn(int $value, string $key): object => (object) ['value' => $value]);

        self::expectException(\TypeError::class);
        $terminal(['key' => 1]);
    }
}
