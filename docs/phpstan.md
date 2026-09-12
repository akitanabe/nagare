# PHPStan types

The project enables `extension.neon` through `phpstan.neon.dist`. Applications
using Nagare should include the extension in their PHPStan configuration:

```neon
includes:
    - vendor/akitanabe/nagare/extension.neon
```

The type implementation is verified with PHPStan 2.2.13. PHPStan is a development
dependency; terminal execution does not load or invoke the extension.

`Transform<TInput, TOutput>` describes a single-value transformation.
`Terminal<TKey, TValue, TResult>` and `TerminalExecution<TKey, TValue, TResult>`
describe accepted keys, accepted values, and the result. Inputs are contravariant
and results are covariant. Iterable keys are unrestricted; an object key is valid
unless a custom terminal explicitly requires another key type.

The extension preserves the following relationships:

| Definition | Result for input elements of type `T` |
| --- | --- |
| `first()` | `T\|null` |
| `values()` | `list<T>` |
| `fold($initial, $reducer)` | The accumulator type |
| `pivot(first: first(), values: values())` | `array{first: T\|null, values: list<T>}` |

The same saved `first()` or `values()` definition can be invoked with different
element types. Each invocation infers its own result. `apply()` carries the
transformation output into the terminal result, while `mapping()` preserves the
source key type and changes its value type.

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
its contract into `new Terminal($factory)`. Reducers must accept the initial
accumulator and every subsequent result they return.

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
