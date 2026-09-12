<?php

declare(strict_types=1);

namespace Nagare\Materialization;

use Nagare\Terminal;
use Nagare\TerminalExecution;

/** Create a terminal that collects input values into a list. */
function values(): Terminal
{
    return new Terminal(static fn(): TerminalExecution => new ValuesExecution());
}
