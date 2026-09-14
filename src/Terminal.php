<?php

declare(strict_types=1);

namespace Nagare;

use Closure;
use Nagare\Terminal\HookExecution;
use Nagare\Transformation\TransformExecution;

/**
 * A reusable terminal definition.
 *
 * The factory is evaluated for every invocation, so mutable execution state
 * belongs to one invocation and cannot leak into another one.
 *
 * @template-contravariant TKey
 * @template-contravariant TValue
 * @template-covariant TResult
 */
final class Terminal
{
    /** @var Closure(): TerminalExecution<TKey, TValue, TResult> */
    private readonly Closure $createExecution;

    /** @param callable(): TerminalExecution<TKey, TValue, TResult> $createExecution */
    private function __construct(callable $createExecution)
    {
        $this->createExecution = $createExecution(...);
    }

    /**
     * Create a reusable terminal definition.
     *
     * @template TFactoryKey
     * @template TFactoryValue
     * @template TFactoryResult
     * @param callable(): TerminalExecution<TFactoryKey, TFactoryValue, TFactoryResult> $createExecution
     * @return self<TFactoryKey, TFactoryValue, TFactoryResult>
     */
    public static function factory(callable $createExecution): self
    {
        return new self($createExecution);
    }

    /**
     * Evaluate this terminal against one iterable input.
     *
     * The PHPStan extension uses TInputValue to specialize input-dependent results.
     *
     * @template TInputValue of TValue
     * @param iterable<TKey, TValue&TInputValue> $input
     * @return TResult
     */
    public function __invoke(iterable $input): mixed
    {
        $execution = $this->execution();

        if (!$execution->isComplete()) {
            foreach ($input as $key => $value) {
                $execution->accept($value, $key);

                if ($execution->isComplete()) {
                    break;
                }
            }
        }

        return $execution->finish();
    }

    /**
     * Attach a single-value transformation to this terminal definition.
     *
     * @return Closure<TSource>(Transform<TSource, TValue>): Terminal<TKey, TSource, TResult>
     */
    public function apply(): Closure
    {
        return $this->applyTransform(...);
    }

    /**
     * @template TSource
     * @param Transform<TSource, TValue> $transform
     * @return Terminal<TKey, TSource, TResult>
     */
    private function applyTransform(Transform $transform): self
    {
        return self::factory(fn(): TerminalExecution => new TransformExecution($this->execution(), $transform));
    }

    /**
     * Observe each value and key received by this terminal before forwarding them to the underlying execution.
     *
     * @param-later-invoked-callable $callback
     * @param callable(TValue, TKey): void $callback
     * @return self<TKey, TValue, TResult>
     */
    public function hook(callable $callback): self // @phpstan-ignore generics.variance, generics.variance (The hook callback consumes the terminal's contravariant input types.)
    {
        return self::factory(fn(): TerminalExecution => new HookExecution($this->execution(), $callback));
    }

    /**
     * @internal
     * @return TerminalExecution<TKey, TValue, TResult>
     */
    public function execution(): TerminalExecution
    {
        return ($this->createExecution)();
    }
}
