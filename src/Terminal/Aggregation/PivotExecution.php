<?php

declare(strict_types=1);

namespace Nagare\Terminal\Aggregation;

use Nagare\Terminal;
use Nagare\TerminalExecution;

/**
 * @internal
 * @template TKey
 * @template TValue
 * @template TResult
 * @implements TerminalExecution<TKey, TValue, array<int|string, TResult>>
 */
final class PivotExecution implements TerminalExecution
{
    /** @var array<int|string, TerminalExecution<TKey, TValue, TResult>> */
    private array $executions = [];

    /**
     * @param array<int|string, Terminal<TKey, TValue, TResult>> $terminals
     */
    public function __construct(array $terminals)
    {
        foreach ($terminals as $name => $terminal) {
            $this->executions[$name] = $terminal->execution();
        }
    }

    public function accept(mixed $value, mixed $key): void
    {
        foreach ($this->executions as $execution) {
            if ($execution->isComplete()) {
                continue;
            }

            $execution->accept($value, $key);
        }
    }

    public function isComplete(): bool
    {
        foreach ($this->executions as $execution) {
            if (!$execution->isComplete()) {
                return false;
            }
        }

        return true;
    }

    public function finish(): mixed
    {
        $results = [];

        foreach ($this->executions as $name => $execution) {
            $results[$name] = $execution->finish();
        }

        return $results;
    }
}
