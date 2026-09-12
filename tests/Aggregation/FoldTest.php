<?php

declare(strict_types=1);

namespace Nagare\Tests\Aggregation;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Aggregation\fold;

final class FoldTest extends TestCase
{
    public function testFoldReturnsItsInitialValueForEmptyInput(): void
    {
        self::assertSame(10, fold(10, static fn(int $carry, int $value): int => $carry + $value)([]));
    }

    public function testFoldReceivesValuesInInputOrder(): void
    {
        $fold = fold('', static fn(string $carry, int $value): string => $carry . $value);

        self::assertSame('123', $fold(['a' => 1, 'b' => 2, 'c' => 3]));
    }

    public function testCallbackExceptionsPropagateWithoutBeingSwallowed(): void
    {
        $failure = new RuntimeException('callback failure');
        $terminal = fold(0, static function (int $carry, int $value) use ($failure): int {
            if ($value === 2) {
                throw $failure;
            }

            return $carry + $value;
        });

        try {
            $terminal([1, 2, 3]);
            self::fail('The callback exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
