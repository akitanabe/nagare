<?php

declare(strict_types=1);

namespace Nagare;

/**
 * Adapts values accepted by a terminal execution while preserving its keys and result.
 *
 * Adapter definitions must not retain state from an invocation. Each call to
 * apply() creates the execution wrapper used for one terminal invocation.
 *
 * @template-contravariant TInput
 * @template-covariant TOutput
 */
interface TerminalAdapter
{
    /**
     * Wrap a terminal execution with this adapter.
     *
     * @template TKey
     * @template TResult
     * @param TerminalExecution<TKey, TOutput, TResult> $execution
     * @return TerminalExecution<TKey, TInput, TResult>
     */
    public function apply(TerminalExecution $execution): TerminalExecution;
}
