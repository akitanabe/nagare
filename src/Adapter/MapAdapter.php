<?php

declare(strict_types=1);

namespace Nagare\Adapter;

use Closure;
use Nagare\TerminalAdapter;
use Nagare\TerminalExecution;

/**
 * @internal
 * @template-contravariant TInput
 * @template-covariant TOutput
 * @implements TerminalAdapter<TInput, TOutput>
 */
final readonly class MapAdapter implements TerminalAdapter
{
    /** @var Closure(TInput): TOutput */
    private Closure $mapper;

    /** @param callable(TInput): TOutput $mapper */
    public function __construct(callable $mapper)
    {
        $this->mapper = $mapper(...);
    }

    public function apply(TerminalExecution $execution): TerminalExecution
    {
        return new MapExecution($execution, $this->mapper);
    }
}
