<?php

declare(strict_types=1);

namespace Nagare\Adapter;

use Nagare\TerminalAdapter;
use Nagare\TerminalExecution;

/**
 * An immutable, reusable definition of terminal-local value adaptation.
 *
 * @template-contravariant TInput
 * @template-covariant TOutput
 * @implements TerminalAdapter<TInput, TOutput>
 */
final readonly class Definition implements TerminalAdapter
{
    /** @param TerminalAdapter<TInput, TOutput> $adapter */
    private function __construct(
        private TerminalAdapter $adapter,
    ) {}

    /**
     * @internal
     * @template TFactoryInput
     * @template TFactoryOutput
     * @param callable(TFactoryInput): TFactoryOutput $mapper
     * @return self<TFactoryInput, TFactoryOutput>
     */
    public static function mapping(callable $mapper): self
    {
        return new self(new MapAdapter($mapper));
    }

    /**
     * @internal
     * @template TValue
     * @param callable(TValue): bool $predicate
     * @return self<TValue, TValue>
     */
    public static function filtering(callable $predicate): self
    {
        return new self(new FilterAdapter($predicate));
    }

    /**
     * @internal
     * @template TFactoryInput
     * @template TFactoryOutput
     * @param callable(TFactoryInput): (TFactoryOutput|null) $mapper
     * @return self<TFactoryInput, TFactoryOutput>
     */
    public static function filteringMap(callable $mapper): self
    {
        return new self(new FilterMapAdapter($mapper));
    }

    /**
     * Compose definitions in left-to-right data-flow order.
     *
     * @template TPrevious
     * @param self<TPrevious, TInput> $previous
     * @return self<TPrevious, TOutput>
     */
    public function __invoke(self $previous): self
    {
        return new self(new ComposedAdapter($previous->adapter, $this->adapter));
    }

    public function apply(TerminalExecution $execution): TerminalExecution
    {
        return $this->adapter->apply($execution);
    }
}
