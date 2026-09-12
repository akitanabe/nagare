<?php

declare(strict_types=1);

namespace Nagare\Tests\PHPStan\Fixtures;

use Nagare\Terminal;
use Nagare\Tests\PHPStan\ObjectKeyExecution;

use function Nagare\Aggregation\combine;
use function Nagare\Aggregation\fold;
use function Nagare\Materialization\values;
use function Nagare\Pipeline\mapping;
use function Nagare\Selection\first;
use function Nagare\Transformation\map;

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

    $combined = combine(values: values(), total: $sum);
    // @phpstan-ignore argument.type, argument.templateType (Every child terminal must accept the shared source.)
    $strings |> $combined;

    $incompatible = combine(length: $terminal, sum: $sum);
    // @phpstan-ignore argument.type (No integer value satisfies both string and integer input constraints.)
    $numbers |> $incompatible;

    $custom = new Terminal(static fn(): ObjectKeyExecution => new ObjectKeyExecution());
    // @phpstan-ignore argument.type (This custom terminal requires object keys.)
    $numbers |> $custom;
    // @phpstan-ignore argument.type (Combining terminals must preserve a child's key constraint.)
    $numbers |> combine(first: first(), custom: $custom);

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
