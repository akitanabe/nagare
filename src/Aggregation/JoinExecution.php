<?php

declare(strict_types=1);

namespace Nagare\Aggregation;

use Nagare\TerminalExecution;

/**
 * @internal
 * @implements TerminalExecution<mixed, string, string>
 */
final class JoinExecution implements TerminalExecution
{
    private string $joined = '';

    private bool $hasValue = false;

    public function __construct(
        private readonly string $separator,
    ) {}

    public function accept(mixed $value, mixed $key): void
    {
        $this->append($value);
    }

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): string
    {
        return $this->joined;
    }

    private function append(string $value): void
    {
        if ($this->hasValue) {
            $this->joined .= $this->separator;
        }

        $this->joined .= $value;
        $this->hasValue = true;
    }
}
