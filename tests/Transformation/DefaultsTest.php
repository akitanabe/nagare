<?php

declare(strict_types=1);

namespace Nagare\Tests\Transformation;

use Nagare\Transform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Nagare\Transformation\defaults;
use function Nagare\Transformation\defaultsOr;
use function Nagare\Transformation\map;
use function Nagare\Transformation\then;

final class DefaultsTest extends TestCase
{
    public function testThenKeepsTheSameValueWhenThePredicateIsTruthy(): void
    {
        $value = new \stdClass();

        self::assertSame($value, then(static fn(object $input): int => $input === $value ? 1 : 0)->transform($value));
    }

    #[DataProvider('falsyValues')]
    public function testThenReturnsNullForPhpFalsyPredicateResults(mixed $result): void
    {
        self::assertNull(then(static fn(mixed $value): mixed => $result)->transform('value'));
    }

    /** @return iterable<string, array{mixed}> */
    public static function falsyValues(): iterable
    {
        yield 'empty string' => [''];
        yield 'zero string' => ['0'];
        yield 'zero integer' => [0];
        yield 'zero float' => [0.0];
        yield 'empty array' => [[]];
        yield 'false' => [false];
        yield 'null' => [null];
    }

    public function testThenIsLazyAndPropagatesPredicateExceptions(): void
    {
        $failure = new RuntimeException('predicate failure');
        $calls = 0;
        $transform = then(static function () use (&$calls, $failure): never {
            $calls++;
            throw $failure;
        });

        self::assertSame(0, $calls);

        try {
            $transform->transform('value');
            self::fail('The predicate exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
            self::assertSame(1, $calls);
        }
    }

    public function testDefaultsReplacesOnlyNullWithTheFixedFallback(): void
    {
        $fallback = new \stdClass();
        $transform = defaults($fallback);

        self::assertSame($fallback, $transform->transform(null));
        self::assertFalse($transform->transform(false));
        self::assertSame(0, $transform->transform(0));
        self::assertSame('', $transform->transform(''));
        $value = new \stdClass();
        self::assertSame($value, $transform->transform($value));
    }

    public function testDefaultsOrCallsTheFactoryOnceForEachNullAndNeverForPresentValues(): void
    {
        $calls = 0;
        $transform = defaultsOr(static function () use (&$calls): string {
            $calls++;

            return "created {$calls}";
        });

        self::assertSame(0, $calls);
        self::assertFalse($transform->transform(false));
        self::assertSame(0, $transform->transform(0));
        self::assertSame('', $transform->transform(''));
        self::assertSame(0, $calls);
        self::assertSame('created 1', $transform->transform(null));
        self::assertSame(1, $calls);
        self::assertSame('created 2', $transform->transform(null));
        self::assertSame(2, $calls);
    }

    public function testDefaultsOrPropagatesFactoryExceptions(): void
    {
        $failure = new RuntimeException('factory failure');
        $transform = defaultsOr(static function () use ($failure): never {
            throw $failure;
        });

        $this->expectExceptionObject($failure);

        $transform->transform(null);
    }

    public function testDefinitionsReturnTransformsAndComposeFromLeftToRight(): void
    {
        $transform = map(static fn(int $value): string => (string) $value)
            |> then(static fn(string $value): bool => $value !== '0')
            |> defaults('fallback')
            |> defaultsOr(static fn(): string => 'created');

        self::assertInstanceOf(Transform::class, then(static fn(mixed $value): bool => true));
        self::assertInstanceOf(Transform::class, defaults('fallback'));
        self::assertInstanceOf(Transform::class, defaultsOr(static fn(): string => 'created'));
        self::assertSame('12', $transform->transform(12));
        self::assertSame('fallback', $transform->transform(0));
    }
}
