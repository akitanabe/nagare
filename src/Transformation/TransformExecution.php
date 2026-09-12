<?php

declare(strict_types=1);

namespace Nagare\Transformation;

use Nagare\TerminalExecution;
use Nagare\Transform;

/**
 * @internal
 * @template TKey
 * @template TInput
 * @template TOutput
 * @template TResult
 * @implements TerminalExecution<TKey, TInput, TResult>
 */
final class TransformExecution implements TerminalExecution
{
    /**
     * @param TerminalExecution<TKey, TOutput, TResult> $execution
     * @param Transform<TInput, TOutput> $transform
     */
    public function __construct(
        private TerminalExecution $execution,
        private Transform $transform,
    ) {}

    public function accept(mixed $value, mixed $key): void
    {
        $this->execution->accept($this->transform->transform($value), $key);
    }

    public function isComplete(): bool
    {
        return $this->execution->isComplete();
    }

    public function finish(): mixed
    {
        return $this->execution->finish();
    }
}
