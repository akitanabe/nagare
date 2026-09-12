<?php

declare(strict_types=1);

namespace Nagare\Selection;

use Nagare\Terminal;
use Nagare\TerminalExecution;

/**
 * Create a terminal that returns the first input value, or null when empty.
 *
 * @return Terminal<mixed, mixed, mixed>
 */
function first(): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new FirstExecution());
}
