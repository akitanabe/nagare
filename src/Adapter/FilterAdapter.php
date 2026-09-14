<?php

declare(strict_types=1);

namespace Nagare\Adapter;

use Closure;
use Nagare\TerminalAdapter;
use Nagare\TerminalExecution;

/**
 * @internal
 * @template TValue
 * @implements TerminalAdapter<TValue, TValue>
 */
final readonly class FilterAdapter implements TerminalAdapter
{
    /** @var Closure(TValue): bool */
    private Closure $predicate;

    /** @param callable(TValue): bool $predicate */
    public function __construct(callable $predicate)
    {
        $this->predicate = $predicate(...);
    }

    public function apply(TerminalExecution $execution): TerminalExecution
    {
        return new FilterExecution($execution, $this->predicate);
    }
}
