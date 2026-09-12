<?php

declare(strict_types=1);

namespace Nagare\Tests\Aggregation;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function Nagare\Aggregation\combine;
use function Nagare\Materialization\values;
use function Nagare\Selection\first;

final class CombineArgumentsTest extends TestCase
{
    public function testCombineReturnsPositionalResultsInDeclarationOrder(): void
    {
        self::assertSame([1, [1, 2]], combine(first(), values())([1, 2]));
    }

    public function testCombineRejectsMixedPositionalAndNamedArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        combine(first(), values: values());
    }

    public function testCombineRejectsMixedKeysInUnpackedArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        combine(...[first(), 'values' => values()]);
    }

    public function testCombineRejectsUnpackedPositionalArgumentsWithNamedArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        combine(...[first(), values()], last: first());
    }
}
