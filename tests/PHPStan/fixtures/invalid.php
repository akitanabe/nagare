<?php

declare(strict_types=1);

namespace Nagare\Tests\PHPStan\Fixtures;

use Nagare\Terminal;
use Nagare\Tests\PHPStan\ObjectKeyExecution;

use function Nagare\Aggregation\all;
use function Nagare\Aggregation\any;
use function Nagare\Aggregation\average;
use function Nagare\Aggregation\fold;
use function Nagare\Aggregation\join;
use function Nagare\Aggregation\none;
use function Nagare\Aggregation\pivot;
use function Nagare\Aggregation\sum;
use function Nagare\Materialization\values;
use function Nagare\Pipeline\mapping;
use function Nagare\Selection\first;
use function Nagare\Transformation\map;
use function Nagare\Transformation\then;

/**
 * @param list<int> $numbers
 * @param list<string> $strings
 */
function incompatible_inputs(array $numbers, array $strings): void
{
    $format = map(format_number(...));
    $length = map(text_length(...));
    // @phpstan-ignore argument.type (A string cannot be given to an integer transformation.)
    $format->transform('invalid');
    // @phpstan-ignore argument.type (The next transformation cannot accept the previous output.)
    $format |> $format;

    $positive = then(static fn(int $value): bool => $value > 0);
    // @phpstan-ignore argument.type (A string cannot be given to an integer predicate transformation.)
    $positive->transform('invalid');

    $terminal = $length |> first()->apply();
    // @phpstan-ignore argument.type (The terminal requires string input after applying this transformation.)
    $numbers |> $terminal;
    // @phpstan-ignore argument.type (Lazy mapping must reject incompatible source elements.)
    $strings |> mapping(format_number(...));

    $sum = fold(0, static fn(int $sum, int $value): int => $sum + $value);
    // @phpstan-ignore argument.type (The reducer accepts integers only.)
    $strings |> $sum;
    // @phpstan-ignore argument.type (The transformed output must satisfy the reducer input.)
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
    $hasNoNegative = none(static fn(int $value): bool => $value < 0);
    // @phpstan-ignore argument.type (The none terminal predicate accepts integers only.)
    $strings |> $hasNoNegative;
    // @phpstan-ignore argument.type (The any predicate must return bool.)
    any(static fn(int $value): string => (string) $value);
    // @phpstan-ignore argument.type (The all predicate must return bool.)
    all(static fn(int $value): string => (string) $value);
    // @phpstan-ignore argument.type (The none predicate must return bool.)
    none(static fn(int $value): string => (string) $value);

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
}

function incompatible_definition_alternatives(bool $chooseStrings): void
{
    $strings = fold('', static fn(string $state, string $value): string => $state . $value);
    $numbers = fold(0, static fn(int $state, int $value): int => $state + $value);
    $terminal = $chooseStrings ? $strings : $numbers;
    // @phpstan-ignore argument.type (The transformed value must be accepted by every possible terminal definition.)
    map(format_number(...)) |> $terminal->apply();
}
