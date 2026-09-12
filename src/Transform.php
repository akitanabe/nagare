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
    public function __construct(callable $transform)
    {
        $this->transform = $transform(...);
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
        return new self(fn(mixed $value): mixed => $next->transform($this->transform($value)));
    }
}
