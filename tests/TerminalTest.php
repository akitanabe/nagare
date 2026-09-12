<?php

declare(strict_types=1);

namespace Nagare\Tests;

use Nagare\Terminal;
use Nagare\TerminalExecution;
use PHPUnit\Framework\TestCase;
use TypeError;

use function Nagare\Materialization\values;
use function Nagare\Transformation\map;

final class TerminalTest extends TestCase
{
    public function testTerminalDefinitionCanBeReusedForIndependentInputs(): void
    {
        $terminal = values();

        self::assertSame([1], $terminal(['first' => 1]));
        self::assertSame([2], $terminal(['second' => 2]));
    }

    public function testTerminalInvocationAcceptsOnlyIterableExecutionInput(): void
    {
        $terminal = values();
        $transform = map(static fn(int $value): int => $value + 1);

        $this->expectException(TypeError::class);

        /** @phpstan-ignore argument.type (The runtime contract intentionally rejects transforms as terminal input.) */
        $terminal($transform);
    }

    public function testTerminalPassesKeysOfAnyTypeAndValuesInInputOrder(): void
    {
        $log = new RecordingLog();
        $terminal = new Terminal(static fn(): TerminalExecution => new RecordingExecution($log));
        $key = new \stdClass();
        $source = static function () use ($key): iterable {
            yield $key => 1;
            yield 'two' => 2;
        };

        self::assertSame('finished', $terminal($source()));
        self::assertSame([[$key, 1], ['two', 2]], $log->seen);
    }
}
