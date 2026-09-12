<?php

declare(strict_types=1);

namespace Nagare\Selection;

use Nagare\Terminal;
use Nagare\TerminalExecution;

/** Create a terminal that returns the first input value, or null when empty. */
function first(): Terminal
{
    return new Terminal(static fn(): TerminalExecution => new FirstExecution());
}
