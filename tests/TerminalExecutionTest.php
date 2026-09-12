<?php

declare(strict_types=1);

namespace Nagare\Tests;

use Nagare\TerminalExecution;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\first;
use function Nagare\fold;
use function Nagare\values;

final class TerminalExecutionTest extends TestCase
{
    public function testTerminalDefinitionCanBeReusedForIndependentInputs(): void
    {
        $terminal = values();

        self::assertSame([1], $terminal(['first' => 1]));
        self::assertSame([2], $terminal(['second' => 2]));
    }

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

    public function testEmptyInputsReturnEachTerminalDefault(): void
    {
        self::assertNull(first()([]));
        self::assertSame([], values()([]));
        self::assertSame(10, fold(10, static fn(int $carry, int $value): int => $carry + $value)([]));
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

    public function testIteratorExceptionsPropagateWithoutBeingSwallowed(): void
    {
        $failure = new RuntimeException('iterator failure');
        $source = static function () use ($failure): iterable {
            yield 'first' => 'first value';
            throw $failure;
        };

        $thrown = null;
        try {
            values()($source());
            self::fail('The iterator exception was not thrown.');
        } catch (RuntimeException $caught) {
            $thrown = $caught;
        }

        self::assertSame($failure, $thrown);
    }

    public function testTerminalPassesKeysOfAnyTypeAndValuesInInputOrder(): void
    {
        $log = new RecordingLog();
        $terminal = new \Nagare\Terminal(static fn(): TerminalExecution => new RecordingExecution($log));
        $key = new \stdClass();
        $source = static function () use ($key): iterable {
            yield $key => 1;
            yield 'two' => 2;
        };

        self::assertSame('finished', $terminal($source()));
        self::assertSame([[$key, 1], ['two', 2]], $log->seen);
    }
}
