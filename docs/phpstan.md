# PHPStan types

The project enables `extension.neon` through `phpstan.neon.dist`. Applications
using Nagare should include the extension in their PHPStan configuration:

```neon
includes:
    - vendor/akitanabe/nagare/extension.neon
```

The type implementation is verified with PHPStan 2.2.13. PHPStan is a development
dependency; terminal execution does not load or invoke the extension.

`TerminalAdapter<TInput, TOutput>` describes terminal-local input and output,
and `Adapter\Definition<TInput, TOutput>` is Nagare's built-in implementation.
`Terminal<TKey, TValue, TResult>` and `TerminalExecution<TKey, TValue, TResult>`
describe accepted keys, accepted values, and the result. Inputs are
contravariant and outputs/results are covariant. Iterable keys are unrestricted;
an object key is valid unless a custom terminal explicitly requires another key
type.

The extension preserves the following relationships:

| Definition | Result for input elements of type `T` |
| --- | --- |
| `first()` | `T\|null` |
| `values()` | `list<T>` |
| `fold($initial, $reducer)` | The accumulator type |
| `pivot(first: first(), values: values())` | `array{first: T\|null, values: list<T>}` |

The same saved `first()` or `values()` definition can be invoked with different
element types. Each invocation infers its own result. `Terminal::apply()` carries
any adapter output into the terminal result, while `mapping()` preserves the
source key type and changes its value type.

## TerminalAdapter relationships

Attaching `TerminalAdapter<TSource, TOutput>` to a terminal that accepts
`TOutput` produces a terminal that accepts `TSource`. Keys and terminal results
remain opaque to the adapter. When a terminal result depends on its input value,
the extension substitutes the adapter output. For example:

```php
<?php

declare(strict_types=1);

namespace App;

use function Nagare\Adapter\filterMap;
use function Nagare\Adapter\map;
use function Nagare\Aggregation\pivot;
use function Nagare\Materialization\values;
use function Nagare\Query\first;

$format = map(static fn(int $value): string => (string) $value);
$formatted = $format |> values()->apply();
$summary = $format |> pivot(first: first(), values: values())->apply();
$lengths = filterMap(static fn(string $value): ?int => $value === '' ? null : strlen($value))
    |> values()->apply();

// $formatted is Terminal<mixed, int, list<string>>
// $summary is Terminal<mixed, int, array{first: string|null, values: list<string>}>
// $lengths is Terminal<mixed, string, list<int<1, max>>>
```

The eight built-in factories preserve these relationships:

| Factory | Inferred definition |
| --- | --- |
| `map(callable(TInput): TOutput)` | `Definition<TInput, TOutput>` |
| `then(callable(TValue): mixed)` | `Definition<TValue, TValue\|null>` |
| `fallback(TFallback)` | `Definition<TValue\|null, TValue\|TFallback>` at each input connection |
| `fallbackWith(callable(): TFallback)` | `Definition<TValue\|null, TValue\|TFallback>` at each input connection |
| `filter(callable(TValue): bool)` | `Definition<TValue, TValue>` |
| `some()` | `Definition<TValue\|null, TValue>` at each input connection |
| `none()` | `Definition<TValue\|null, null>` at each input connection |
| `filterMap(callable(TInput): TOutput\|null)` | `Definition<TInput, TOutput>` with `null` removed from the output |

The input-dependent factories can be saved and reused. For example, one
`fallback('missing')` definition produces `list<int|string>` from
`iterable<int|null>` and `list<string>` from `iterable<string|null>`.
Composition through `Definition::__invoke()` carries each output into the next
input from left to right. PHPStan rejects an incompatible source, an adapter
whose output cannot satisfy the terminal, an incompatible definition chain, a
non-boolean `filter()` predicate, and a `filterMap()` source that cannot satisfy
its callback input.

`pivot()` accepts either all positional or all named arguments. Mixing them,
including through unpacking, throws `InvalidArgumentException` when `pivot()`
is called. It preserves keys, nested results, and optional entries in unpacked
array shapes. Arrays with dynamic keys retain their key and result types without
inventing known names. Its input must satisfy every child
terminal's key and value constraints. When those constraints are incompatible,
only an empty source can be supplied. The same requirement applies when attaching
a transformation to a terminal selected from multiple possible definitions.

For a custom execution, declare `@implements TerminalExecution<TKey, TValue,
TResult>` on the implementation. A factory returning that implementation carries
its contract into `Terminal::factory($factory)`. Reducers must accept the initial
accumulator and every subsequent result they return.

For a third-party adapter, declare `@implements TerminalAdapter<TInput,
TOutput>`. Its `apply()` method keeps key and result types generic:

```php
/**
 * @template TKey
 * @template TResult
 * @param TerminalExecution<TKey, TOutput, TResult> $execution
 * @return TerminalExecution<TKey, TInput, TResult>
 */
public function apply(TerminalExecution $execution): TerminalExecution;
```

This is sufficient for a custom adapter to connect to custom terminals with
opaque key and result types. The extension reads the public
`TerminalAdapter<TInput, TOutput>` relationship; it does not require knowledge
of the concrete adapter or execution class.

Explicitly widening a definition to `Terminal<mixed, mixed, mixed>` discards its
result relationship. Likewise, a callback accepting or returning `mixed` cannot
provide a more specific contract just from a later pipeline connection. Declare
callback input types when their accepted values matter.

`composer test` runs the public result assertions through PHPStan's
`TypeInferenceTestCase`, alongside the runtime tests. `composer analyse` also
checks invalid public API calls in `tests/PHPStan/fixtures/invalid.php`. Each
intentional error has a line-specific identifier and reason; unmatched ignores
fail analysis, so accepting an invalid connection causes a regression.

Run `composer check` for the full test, analysis, lint, and formatting checks.

For a complete application extension example, see [Custom components](custom-components.md).
