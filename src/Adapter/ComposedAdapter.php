<?php

declare(strict_types=1);

namespace Nagare\Adapter;

use Nagare\TerminalAdapter;
use Nagare\TerminalExecution;

/**
 * @internal
 * @template-contravariant TInput
 * @template TIntermediate
 * @template-covariant TOutput
 * @implements TerminalAdapter<TInput, TOutput>
 */
final readonly class ComposedAdapter implements TerminalAdapter
{
    /**
     * @param TerminalAdapter<TInput, TIntermediate> $previous
     * @param TerminalAdapter<TIntermediate, TOutput> $next
     */
    public function __construct(
        private TerminalAdapter $previous,
        private TerminalAdapter $next,
    ) {}

    public function apply(TerminalExecution $execution): TerminalExecution
    {
        return $this->previous->apply($this->next->apply($execution));
    }
}
