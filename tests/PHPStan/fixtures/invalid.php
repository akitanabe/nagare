<?php

declare(strict_types=1);

namespace Nagare\Tests\PHPStan\Fixtures;

use Nagare\Terminal;
use Nagare\Tests\PHPStan\ObjectKeyExecution;
use Nagare\Tests\TerminalAdapter\StringLengthAdapter;

use function Nagare\Adapter\filter as adapterFilter;
use function Nagare\Adapter\filterMap as adapterFilterMap;
use function Nagare\Adapter\map;
use function Nagare\Adapter\map as adapterMap;
use function Nagare\Adapter\then;
use function Nagare\Pipeline\chunking;
use function Nagare\Pipeline\each;
use function Nagare\Pipeline\mapping;
use function Nagare\Terminal\Aggregation\average;
use function Nagare\Terminal\Aggregation\fold;
use function Nagare\Terminal\Aggregation\join;
use function Nagare\Terminal\Aggregation\maxBy;
use function Nagare\Terminal\Aggregation\minBy;
use function Nagare\Terminal\Aggregation\pivot;
use function Nagare\Terminal\Aggregation\sum;
use function Nagare\Terminal\Materialization\associate;
use function Nagare\Terminal\Materialization\values;
use function Nagare\Terminal\Query\all;
use function Nagare\Terminal\Query\any;
use function Nagare\Terminal\Query\find;
use function Nagare\Terminal\Query\first;

/**
 * @param list<int> $numbers
 * @param list<string> $strings
 * @param iterable<object, int> $objectKeys
 */
function incompatible_inputs(array $numbers, array $strings, iterable $objectKeys): void
{
    $format = map(format_number(...));
    $length = map(text_length(...));
    $formattedValues = $format |> values()->apply();
    // @phpstan-ignore argument.type (A string cannot be given to an integer adapter.)
    ['invalid'] |> $formattedValues;
    // @phpstan-ignore argument.type (The next adapter cannot accept the previous output.)
    $format |> $format;

    $positive = then(static fn(int $value): bool => $value > 0);
    $positiveValues = $positive |> values()->apply();
    // @phpstan-ignore argument.type (A string cannot be given to an integer predicate adapter.)
    ['invalid'] |> $positiveValues;

    $terminal = $length |> first()->apply();
    // @phpstan-ignore argument.type (The terminal requires string input after applying this adapter.)
    $numbers |> $terminal;
    // @phpstan-ignore argument.type (Lazy mapping must reject incompatible source elements.)
    $strings |> mapping(format_number(...));

    $findInteger = find(static fn(int $value): bool => $value > 0);
    // @phpstan-ignore argument.type, argument.templateType (The find predicate accepts integers only.)
    $strings |> $findInteger;
    $minimumIntegerByValue = minBy(static fn(int $value): int => $value);
    // @phpstan-ignore argument.type, argument.templateType (The minBy selector accepts integers only.)
    $strings |> $minimumIntegerByValue;
    $maximumIntegerByValue = maxBy(static fn(int $value): int => $value);
    // @phpstan-ignore argument.type, argument.templateType (The maxBy selector accepts integers only.)
    $strings |> $maximumIntegerByValue;
    // @phpstan-ignore argument.type (The find predicate must return bool.)
    find(static fn(int $value): string => (string) $value);

    $sum = fold(0, static fn(int $sum, int $value): int => $sum + $value);
    // @phpstan-ignore argument.type (The reducer accepts integers only.)
    $strings |> $sum;
    // @phpstan-ignore argument.type (The adapted output must satisfy the reducer input.)
    $format |> $sum->apply();

    $integerSum = sum();
    // @phpstan-ignore argument.type (The integer sum terminal accepts integers only.)
    $strings |> $integerSum;
    $average = average();
    // @phpstan-ignore argument.type (The average terminal accepts integers only.)
    $strings |> $average;
    $joined = join(',');
    // @phpstan-ignore argument.type (The join terminal accepts strings only.)
    $numbers |> $joined;

    $hasPositive = any(static fn(int $value): bool => $value > 0);
    // @phpstan-ignore argument.type (The any terminal predicate accepts integers only.)
    $strings |> $hasPositive;
    $allPositive = all(static fn(int $value): bool => $value > 0);
    // @phpstan-ignore argument.type (The all terminal predicate accepts integers only.)
    $strings |> $allPositive;
    // @phpstan-ignore argument.type (The any predicate must return bool.)
    any(static fn(int $value): string => (string) $value);
    // @phpstan-ignore argument.type (The all predicate must return bool.)
    all(static fn(int $value): string => (string) $value);

    $pivoted = pivot(values: values(), total: $sum);
    // @phpstan-ignore argument.type, argument.templateType (Every child terminal must accept the shared source.)
    $strings |> $pivoted;

    $incompatible = pivot(length: $terminal, sum: $sum);
    // @phpstan-ignore argument.type (No integer value satisfies both string and integer input constraints.)
    $numbers |> $incompatible;

    $custom = Terminal::factory(static fn(): ObjectKeyExecution => new ObjectKeyExecution());
    // @phpstan-ignore argument.type (This custom terminal requires object keys.)
    $numbers |> $custom;
    // @phpstan-ignore argument.type (Pivoting terminals must preserve a child's key constraint.)
    $numbers |> pivot(first: first(), custom: $custom);

    // @phpstan-ignore argument.type (The initial accumulator must be accepted by the reducer.)
    $invalidInitial = fold(0, static fn(string $state, int $value): string => $state . $value);
    // @phpstan-ignore argument.type (Each reducer result becomes the next accumulator input.)
    $invalidResult = fold(0, static fn(int $state, int $value): string => (string) ($state + $value));

    // @phpstan-ignore argument.type (An object selector key intentionally violates associate's array-key contract.)
    associate(static fn(int $value, string $key): object => (object) ['value' => $value]);
    // @phpstan-ignore argument.type (Associate requires PHP array-compatible input keys.)
    $objectKeys |> associate();
    // @phpstan-ignore argument.type (Selecting a new key does not relax associate's input-key contract.)
    $objectKeys |> associate(static fn(int $value, mixed $key): string => (string) $value);

    $integerAssociation = associate(static fn(int $value): int => $value);
    // @phpstan-ignore argument.type, argument.templateType (The key selector accepts integer values only.)
    $strings |> $integerAssociation;

    // @phpstan-ignore argument.unresolvableType, callable.unresolvableReturnType (Preserving chunk keys requires PHP array-compatible input keys.)
    $objectKeys |> chunking(2, preserveKeys: true);
}

/**
 * @param list<string> $strings
 * @param iterable<object, int> $objectKeys
 */
function incompatible_terminal_adapters(array $strings, iterable $objectKeys): void
{
    $custom = Terminal::factory(static fn(): ObjectKeyExecution => new ObjectKeyExecution());
    $thirdParty = new StringLengthAdapter() |> $custom->apply();
    // @phpstan-ignore argument.type (The third-party adapter accepts strings, not this integer-valued source.)
    $objectKeys |> $thirdParty;

    // @phpstan-ignore argument.type (The adapter's integer output cannot satisfy a string terminal input.)
    new StringLengthAdapter() |> join(',')->apply();

    // @phpstan-ignore argument.type (Adapter filter predicates have a strict boolean contract.)
    adapterFilter(static fn(int $value): string => (string) $value);

    $integerFilterMap = adapterFilterMap(static fn(int $value): ?string => $value > 0 ? (string) $value : null);
    $integerFilterMapValues = $integerFilterMap |> values()->apply();
    // @phpstan-ignore argument.type (The filterMap callback accepts integers, not this string-valued source.)
    $strings |> $integerFilterMapValues;

    // @phpstan-ignore argument.type (The next definition cannot accept the preceding definition's string output.)
    adapterMap(format_number(...)) |> adapterMap(static fn(float $value): bool => $value > 0);
}

function incompatible_hook_callback_types(): void
{
    $sum = fold(0, static fn(int $state, int $value): int => $state + $value);
    // @phpstan-ignore argument.type (A hook callback must accept the terminal's integer values.)
    $sum->hook(static function (string $value, mixed $key): void {});

    $custom = Terminal::factory(static fn(): ObjectKeyExecution => new ObjectKeyExecution());
    // @phpstan-ignore argument.type (A hook callback must accept the terminal's object keys.)
    $custom->hook(static function (int $value, string $key): void {});
}

/** @param iterable<object, int> $objectKeys */
function incompatible_dynamic_chunking_input(iterable $objectKeys, bool $preserveKeys): void
{
    // @phpstan-ignore argument.unresolvableType (A dynamic flag may preserve keys, so object keys cannot safely be accepted.)
    $objectKeys |> chunking(2, preserveKeys: $preserveKeys);
}

/** @param iterable<object, int> $objectKeys */
function incompatible_each_input(iterable $objectKeys): void
{
    $observed = each(static function (int $value, string $key): void {});
    // @phpstan-ignore argument.type, argument.templateType (Each callback key type does not accept object keys.)
    $objectKeys |> $observed;
}

/** @param iterable<string, string> $stringValues */
function incompatible_each_value(iterable $stringValues): void
{
    $observed = each(static function (int $value, string $key): void {});
    // @phpstan-ignore argument.type, argument.templateType (Each callback value type does not accept strings.)
    $stringValues |> $observed;
}

/** @mago-expect lint:no-boolean-flag-parameter Boolean branches intentionally exercise incompatible union alternatives. */
function incompatible_definition_alternatives(bool $chooseStrings): void
{
    $strings = fold('', static fn(string $state, string $value): string => $state . $value);
    $numbers = fold(0, static fn(int $state, int $value): int => $state + $value);
    $terminal = $chooseStrings ? $strings : $numbers;
    // @phpstan-ignore argument.type (The adapted value must be accepted by every possible terminal definition.)
    map(format_number(...)) |> $terminal->apply();
}
