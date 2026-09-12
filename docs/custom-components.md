# Custom components

Nagare exposes two factories for application-specific components:
`Transform::factory()` creates a reusable single-value transformation, and
`Terminal::factory()` creates a reusable terminal definition from a
`TerminalExecution` factory. The examples below use a small pricing domain.

## Define a reusable Transform

`Transform` is a definition, not a value in the input sequence. Give its
callable an input and output type that describe the application contract. The
definition is stateless and can be reused for multiple values and executions.

Save this as `src/PricingComponents.php` in an application:

```php
<?php

declare(strict_types=1);

namespace App\Pricing;

use Nagare\Transform;

/**
 * Add the application's fixed handling fee to one price.
 *
 * @return Transform<int, int>
 */
function addHandlingFee(): Transform
{
    return Transform::factory(static fn(int $cents): int => $cents + 50);
}
```

Use `Transform::factory()` again when the application needs another reusable
single-value operation. Transformations compose in the order in which they are
connected, and composing definitions does not execute their callbacks.

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

## Compose custom and built-in components

The public `apply()` connection accepts a `Transform` and returns a terminal
that transforms each value before passing it to the original terminal. The
built-in `values()` terminal can therefore be combined with the custom
transformation as follows:

```php
<?php

declare(strict_types=1);

namespace App\Pricing;

use function Nagare\Materialization\values;

$pricesWithFee = addHandlingFee() |> values()->apply();

$result = $pricesWithFee(['coffee' => 250, 'tea' => 400]);

// $result === [300, 450]
```

The same custom Transform can be attached to the custom terminal:

```php
<?php

declare(strict_types=1);

namespace App\Pricing;

$totalWithFee = addHandlingFee() |> totalCents()->apply();

$result = $totalWithFee([250, 400]);

// $result === 750
```

Both definitions remain reusable. Calling either terminal with another
iterable creates a fresh execution for that call.

## Test components independently

Test the public result of a custom Transform directly, and test the custom
terminal directly with an iterable. These tests do not depend on a composed
full pipeline. A separate test can cover composition with a built-in terminal.

Save this as `tests/PricingComponentsTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Tests;

use PHPUnit\Framework\TestCase;

use function App\Pricing\addHandlingFee;
use function App\Pricing\totalCents;
use function Nagare\Materialization\values;

require_once __DIR__ . '/../src/PricingComponents.php';

final class PricingComponentsTest extends TestCase
{
    public function testHandlingFeeTransformsOnePrice(): void
    {
        self::assertSame(300, addHandlingFee()->transform(250));
    }

    public function testTotalCentsReturnsTheTotalAndStartsFreshForEachInput(): void
    {
        $total = totalCents();

        self::assertSame(650, $total([250, 400]));
        self::assertSame(50, $total(['only' => 50]));
    }

    public function testCustomTransformComposesWithBuiltInValues(): void
    {
        $pricesWithFee = addHandlingFee() |> values()->apply();

        self::assertSame([300, 450], $pricesWithFee([250, 400]));
    }
}
```

The first two tests isolate the custom components and their observable
contracts. The third test verifies the connection to a built-in Nagare
terminal without inspecting callbacks, constructors, or execution internals.

For the generic contracts used by custom executions and factories, see
[PHPStan types](phpstan.md).
