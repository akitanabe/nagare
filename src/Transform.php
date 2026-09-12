<?php

declare(strict_types=1);

namespace Nagare;

use Closure;

/**
 * A reusable single-value transformation.
 *
 * Transformations are definitions only. They do not retain a value or any
 * state from an execution, so a composed transformation can be reused.
 *
 * @template-contravariant TInput
 * @template-covariant TOutput
 */
final class Transform
{
    /** @var Closure(TInput): TOutput */
    private readonly Closure $transform;

    /** @param callable(TInput): TOutput $transform */
    private function __construct(callable $transform)
    {
        $this->transform = $transform(...);
    }

    /**
     * Create a reusable single-value transformation definition.
     *
     * @template TFactoryInput
     * @template TFactoryOutput
     * @param callable(TFactoryInput): TFactoryOutput $transform
     * @return self<TFactoryInput, TFactoryOutput>
     */
    public static function factory(callable $transform): self
    {
        return new self($transform);
    }

    /**
     * @template TPrevious
     * @param self<TPrevious, TInput> $previous
     * @return self<TPrevious, TOutput>
     */
    public function __invoke(self $previous): self
    {
        return $previous->then($this);
    }

    /**
     * @param TInput $value
     * @return TOutput
     */
    public function transform(mixed $value): mixed
    {
        return ($this->transform)($value);
    }

    /**
     * @template TNext
     * @param self<TOutput, TNext> $next
     * @return self<TInput, TNext>
     */
    private function then(self $next): self
    {
        return self::factory(fn(mixed $value): mixed => $next->transform($this->transform($value)));
    }
}
