<?php

declare(strict_types=1);

namespace Nagare\Materialization;

use Nagare\TerminalExecution;

/** @internal */
final class ValuesExecution implements TerminalExecution
{
    /** @var list<mixed> */
    private array $values = [];

    public function accept(mixed $value, mixed $key): void
    {
        $this->values[] = $value;
    }

    public function isComplete(): bool
    {
        return false;
    }

    /** @return list<mixed> */
    public function finish(): mixed
    {
        return $this->values;
    }
}
