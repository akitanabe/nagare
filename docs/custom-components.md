# Custom components

Nagare exposes reusable definitions at two extension points.
`Terminal::factory()` creates a terminal from a `TerminalExecution` factory,
and `TerminalAdapter` lets an application adapt values immediately before a
terminal consumes them. `Nagare\Adapter` provides the built-in adapter
definitions. The examples below use a small pricing domain.

## Adapt terminal values

The functions in `Nagare\Adapter` all return the same immutable
`Definition<TInput, TOutput>` type. Definitions compose from left to right with
the pipe operator and run only after the resulting terminal receives input.

| Factory | Terminal-local behavior |
| --- | --- |
| `map(callable(TInput): TOutput)` | Map and forward every input. |
| `then(callable(TValue): mixed)` | Forward the original value when the result is PHP-truthy; otherwise forward `null`. It never rejects an input. |
| `fallback(TFallback)` | Replace only `null` with the fixed fallback. |
| `fallbackWith(callable(): TFallback)` | Call the factory once for each `null`; do not call it for present values. |
| `filter(callable(TValue): bool)` | Forward the original value only when the predicate is PHP-truthy. Its static predicate contract is `bool`. |
| `some()` | Reject only `null`; forward `false`, `0`, `0.0`, and `''`. |
| `none()` | Forward only `null`; reject present values, including `false`, `0`, `0.0`, and `''`. |
| `filterMap(callable(TInput): TOutput\|null)` | Call the mapper once per input, reject `null`, and forward every non-null output. |

Use `then()` when a rejected predicate result must remain visible to the
terminal as `null`. Use `filter()` when that value must not reach the terminal.
Use `map() |> some()` for an explicit nullable mapping, or `filterMap()` when
the mapping and null rejection are one operation.

Every namespace import is explicit in this complete composition example:

```php
<?php

declare(strict_types=1);

namespace App\Pricing;

use function Nagare\Adapter\filter;
use function Nagare\Adapter\map;
use function Nagare\Materialization\values;

$discountedPrices = filter(static fn(int $cents): bool => $cents >= 100)
    |> map(static fn(int $cents): string => sprintf('$%.2f', $cents / 100))
    |> values()->apply();

$result = $discountedPrices(['coffee' => 250, 'sample' => 50]);

// $result === ['$2.50']
```

The source keys are preserved while an adapter runs. Whether keys appear in the
final result remains the terminal's decision; `values()` intentionally returns
a list.

## Define a custom terminal

Implement `TerminalExecution<TKey, TValue, TResult>` for the state and result
of one terminal evaluation. `accept()` receives values in input order,
`isComplete()` reports whether more input is needed, and `finish()` returns the
observable result. The execution must not traverse the input itself; `Terminal`
owns that traversal.

Append the following to `src/PricingComponents.php`:

```php
use Nagare\Terminal;
use Nagare\TerminalExecution;

/** @implements TerminalExecution<mixed, int, int> */
final class TotalCentsExecution implements TerminalExecution
{
    private int $total = 0;

    /**
     * @param int $value
     * @param mixed $key
     */
    public function accept(mixed $value, mixed $key): void
    {
        $this->total += $value;
    }

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): int
    {
        return $this->total;
    }
}

/**
 * Create a terminal that totals prices expressed in cents.
 *
 * @return Terminal<mixed, int, int>
 */
function totalCents(): Terminal
{
    return Terminal::factory(static fn(): TotalCentsExecution => new TotalCentsExecution());
}
```

The factory is called for every invocation of the terminal definition. Therefore
`totalCents()` can be reused safely: state from one input does not affect the
next input. Keep mutable state in the execution object, not in the factory's
definition or in a shared object captured by the factory.

## Define a custom TerminalAdapter

Implement `TerminalAdapter<TInput, TOutput>` when terminal-local adaptation is
application-specific. `apply()` receives the downstream execution and must
return a fresh wrapper for one terminal invocation. The wrapper accepts
`TInput`, forwards `TOutput` with the same key, and preserves the downstream
completion state and result.

The following adapter parses decimal cent strings before an integer terminal:

```php
<?php

declare(strict_types=1);

namespace App\Pricing;

use Nagare\TerminalAdapter;
use Nagare\TerminalExecution;

/**
 * @template TKey
 * @template TResult
 * @implements TerminalExecution<TKey, string, TResult>
 */
final class ParseCentsExecution implements TerminalExecution
{
    /** @param TerminalExecution<TKey, int, TResult> $downstream */
    public function __construct(
        private TerminalExecution $downstream,
    ) {}

    /**
     * @param string $value
     * @param TKey $key
     */
    public function accept(mixed $value, mixed $key): void
    {
        $this->downstream->accept((int) $value, $key);
    }

    public function isComplete(): bool
    {
        return $this->downstream->isComplete();
    }

    /** @return TResult */
    public function finish(): mixed
    {
        return $this->downstream->finish();
    }
}

/** @implements TerminalAdapter<string, int> */
final class ParseCentsAdapter implements TerminalAdapter
{
    /**
     * @template TKey
     * @template TResult
     * @param TerminalExecution<TKey, int, TResult> $execution
     * @return TerminalExecution<TKey, string, TResult>
     */
    public function apply(TerminalExecution $execution): TerminalExecution
    {
        return new ParseCentsExecution($execution);
    }
}
```

The adapter definition must not retain invocation state. A filtering adapter
may omit a downstream `accept()` call for a rejected value, but it must still
delegate `isComplete()` and `finish()`. It must not change keys or traverse the
source; `Terminal` owns traversal and short-circuiting.

## Compose custom and built-in components

The public `Terminal::apply()` connection accepts any `TerminalAdapter`. It
returns a terminal that accepts the adapter input and passes its output to the
original terminal. A custom adapter has the same connection syntax as a
built-in `Nagare\Adapter\Definition`:

```php
<?php

declare(strict_types=1);

namespace App\Pricing;

use function Nagare\Materialization\values;

$parsedPrices = new ParseCentsAdapter() |> values()->apply();

$result = $parsedPrices(['coffee' => '250', 'tea' => '400']);

// $result === [250, 400]
```

## Test components independently

Test the custom terminal directly with an iterable. A separate test can cover
a custom adapter's composition with a built-in terminal.

Save this as `tests/PricingComponentsTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Tests;

use PHPUnit\Framework\TestCase;

use function App\Pricing\totalCents;
use function Nagare\Materialization\values;

require_once __DIR__ . '/../src/PricingComponents.php';

final class PricingComponentsTest extends TestCase
{
    public function testTotalCentsReturnsTheTotalAndStartsFreshForEachInput(): void
    {
        $total = totalCents();

        self::assertSame(650, $total([250, 400]));
        self::assertSame(50, $total(['only' => 50]));
    }

    public function testCustomAdapterComposesWithBuiltInValues(): void
    {
        $parsedPrices = new ParseCentsAdapter() |> values()->apply();

        self::assertSame([250, 400], $parsedPrices(['250', '400']));
    }
}
```

The first test isolates the custom terminal's observable contract. The second
test verifies the custom adapter's connection to a built-in Nagare terminal
without inspecting callbacks, constructors, or execution internals.

For the generic contracts used by custom executions and factories, see
[PHPStan types](phpstan.md).
