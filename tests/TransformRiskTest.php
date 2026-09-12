<?php

declare(strict_types=1);

namespace Nagare\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

use function Nagare\first;
use function Nagare\map;
use function Nagare\mapping;
use function Nagare\values;

final class TransformRiskTest extends TestCase
{
    public function testTransformInvocationRejectsValueExecutionInput(): void
    {
        $transform = map(static fn(int $value): int => $value + 1);

        $this->expectException(TypeError::class);

        /** @phpstan-ignore argument.type (Transform values are executed through transform(), not __invoke().) */
        $transform(1);
    }

    public function testMappingDefersSourceAndMapperUntilDownstreamRequestsFirstValue(): void
    {
        $sourceLog = [];
        $mapperLog = [];
        $mapped = mapping(static function (int $value) use (&$mapperLog): int {
            $mapperLog[] = $value;

            return $value * 2;
        });
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'first' => 1;
            $sourceLog[] = 'second';
            throw new RuntimeException('second value was requested');
        };

        $mappedValues = $mapped($source());

        self::assertSame([], $sourceLog);
        self::assertSame([], $mapperLog);

        self::assertSame(2, first()($mappedValues));
        self::assertSame(['first'], $sourceLog);
        self::assertSame([1], $mapperLog);
    }

    public function testMappingCallbackExceptionsPropagateWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('mapping callback failure');
        $mapped = mapping(static function (int $value) use ($failure): int {
            throw $failure;
        });

        try {
            values()($mapped([1]));
            self::fail('The mapping callback exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
