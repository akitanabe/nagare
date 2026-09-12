<?php

declare(strict_types=1);

namespace Nagare\Tests\Transformation;

use Nagare\Transform;
use PHPUnit\Framework\TestCase;

final class TransformFactoryTest extends TestCase
{
    public function testTransformConstructorIsPrivate(): void
    {
        self::assertTrue(new \ReflectionMethod(Transform::class, '__construct')->isPrivate());
    }

    public function testFactoryCreatesReusableTransformDefinitions(): void
    {
        $transform = Transform::factory(static fn(int $value): int => $value + 1);

        self::assertSame(2, $transform->transform(1));
        self::assertSame(3, $transform->transform(2));
    }
}
