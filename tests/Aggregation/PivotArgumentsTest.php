<?php

declare(strict_types=1);

namespace Nagare\Tests\Aggregation;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function Nagare\Aggregation\pivot;
use function Nagare\Materialization\values;
use function Nagare\Selection\first;

final class PivotArgumentsTest extends TestCase
{
    public function testPivotReturnsPositionalResultsInDeclarationOrder(): void
    {
        self::assertSame([1, [1, 2]], pivot(first(), values())([1, 2]));
    }

    public function testPivotRejectsMixedPositionalAndNamedArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        pivot(first(), values: values());
    }

    public function testPivotRejectsMixedKeysInUnpackedArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        pivot(...[first(), 'values' => values()]);
    }

    public function testPivotRejectsUnpackedPositionalArgumentsWithNamedArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        pivot(...[first(), values()], last: first());
    }
}
