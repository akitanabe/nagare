<?php

declare(strict_types=1);

namespace Nagare\Tests\PHPStan\Fixtures;

use Nagare\Terminal;
use Nagare\Tests\PHPStan\ObjectKeyExecution;
use Nagare\Transform;

use function Nagare\Aggregation\average;
use function Nagare\Aggregation\count;
use function Nagare\Aggregation\fold;
use function Nagare\Aggregation\join;
use function Nagare\Aggregation\max;
use function Nagare\Aggregation\maxBy;
use function Nagare\Aggregation\min;
use function Nagare\Aggregation\minBy;
use function Nagare\Aggregation\pivot;
use function Nagare\Aggregation\sum;
use function Nagare\Materialization\values;
use function Nagare\Pipeline\dropping;
use function Nagare\Pipeline\droppingWhile;
use function Nagare\Pipeline\filtering;
use function Nagare\Pipeline\flatMapping;
use function Nagare\Pipeline\mapping;
use function Nagare\Pipeline\taking;
use function Nagare\Pipeline\takingWhile;
use function Nagare\Query\all;
use function Nagare\Query\any;
use function Nagare\Query\find;
use function Nagare\Query\first;
use function Nagare\Query\last;
use function Nagare\Query\none;
use function Nagare\Transformation\defaults;
use function Nagare\Transformation\defaultsOr;
use function Nagare\Transformation\map;
use function Nagare\Transformation\then;
use function PHPStan\Testing\assertType;

function format_number(int $value): string
{
    return (string) $value;
}

function text_length(string $value): int
{
    return strlen($value);
}

function nullable_length(string $value): ?int
{
    return $value === '' ? null : strlen($value);
}

function created_text(): string
{
    return 'created';
}

/**
 * @param array{total: int, count: int} $state
 * @return array{total: int, count: int}
 */
function summarize(array $state, int $value): array
{
    return ['total' => $state['total'] + $value, 'count' => $state['count'] + 1];
}

/**
 * @param list<int> $numbers
 * @param list<string> $strings
 * @param list<positive-int> $positiveNumbers
 * @param iterable<object, int> $objectKeys
 * @param array<string, int> $stringKeys
 */
function inferred_results(
    array $numbers,
    array $strings,
    array $positiveNumbers,
    iterable $objectKeys,
    array $stringKeys,
): void {
    $format = map(format_number(...));
    $length = map(text_length(...));
    $factoryTransform = Transform::factory(format_number(...));
    assertType('string', $format->transform(1));
    assertType('string', $factoryTransform->transform(1));
    assertType('int', ($format |> $length)->transform(1));

    $first = first();
    assertType('int|null', $numbers |> $first);
    assertType('string|null', $strings |> $first);
    assertType('null', [] |> $first);

    $last = last();
    $minimum = min();
    $maximum = max();
    assertType('int|null', $numbers |> $last);
    assertType('string|null', $strings |> $last);
    assertType('int|null', $numbers |> $minimum);
    assertType('string|null', $strings |> $maximum);
    assertType('int<1, max>|null', $positiveNumbers |> $last);
    assertType('int<1, max>|null', $positiveNumbers |> $minimum);
    assertType('int<1, max>|null', $positiveNumbers |> $maximum);
    assertType('null', [] |> $last);
    assertType('null', [] |> $minimum);
    assertType('null', [] |> $maximum);

    $found = find(static fn(int $value): bool => $value > 0);
    assertType('int|null', $numbers |> $found);
    assertType('int<1, max>|null', $positiveNumbers |> $found);
    assertType('null', [] |> $found);

    $minimumBy = minBy(static fn(int $value): int => $value);
    $maximumBy = maxBy(static fn(int $value): int => $value);
    assertType('int|null', $numbers |> $minimumBy);
    assertType('int|null', $numbers |> $maximumBy);
    assertType('int<1, max>|null', $positiveNumbers |> $minimumBy);
    assertType('int<1, max>|null', $positiveNumbers |> $maximumBy);
    assertType('null', [] |> $minimumBy);
    assertType('null', [] |> $maximumBy);

    $foundText = find(static fn(string $value): bool => $value !== '');
    $minimumTextByLength = minBy(static fn(string $value): int => strlen($value));
    $formattedFound = $format |> $foundText->apply();
    $formattedMinimumByLength = $format |> $minimumTextByLength->apply();
    $formattedMaximumByLength = $format |> maxBy(static fn(string $value): int => strlen($value))->apply();
    assertType('string|null', $numbers |> $formattedFound);
    assertType('string|null', $numbers |> $formattedMinimumByLength);
    assertType('string|null', $numbers |> $formattedMaximumByLength);

    $values = values();
    assertType('list<int>', $numbers |> $values);
    assertType('list<string>', $strings |> $values);
    assertType('list<int>', $objectKeys |> $values);

    $sum = fold(0, static fn(int $sum, int $value): int => $sum + $value);
    assertType('int', $numbers |> $sum);
    assertType('int', [] |> $sum);

    $count = count();
    $integerSum = sum();
    $average = average();
    $joined = join(',');
    assertType('int', $numbers |> $count);
    assertType('int', $strings |> $count);
    assertType('int', [] |> $count);
    assertType('int', $numbers |> $integerSum);
    assertType('int', [] |> $integerSum);
    assertType('float|null', $numbers |> $average);
    assertType('float|null', [] |> $average);
    assertType('string', $strings |> $joined);
    assertType('string', [] |> $joined);

    $hasPositive = any(static fn(int $value): bool => $value > 0);
    $allPositive = all(static fn(int $value): bool => $value > 0);
    $hasNoNegative = none(static fn(int $value): bool => $value < 0);
    $allNonEmpty = all(static fn(string $value): bool => $value !== '');
    assertType('bool', $numbers |> $hasPositive);
    assertType('bool', $numbers |> $allPositive);
    assertType('bool', $strings |> $allNonEmpty);
    assertType('bool', [] |> $hasNoNegative);

    $applyFirst = $first->apply();
    $formattedFirst = $format |> $applyFirst;
    $lengthFirst = $length |> $applyFirst;
    $formattedValues = $format |> $values->apply();
    $lengthSum = $length |> $sum->apply();
    assertType('string|null', $numbers |> $formattedFirst);
    assertType('int|null', $strings |> $lengthFirst);
    assertType('list<string>', $numbers |> $formattedValues);
    assertType('int', $strings |> $lengthSum);

    $pivoted = pivot(first: $first, values: $values, total: $sum, formatted: $formattedFirst);
    assertType('array{first: int|null, values: list<int>, total: int, formatted: string|null}', $numbers |> $pivoted);
    $reusable = pivot(first: $first, values: $values);
    $lengthPivoted = $length |> $reusable->apply();
    assertType('array{first: int|null, values: list<int>}', $numbers |> $reusable);
    assertType('array{first: string|null, values: list<string>}', $strings |> $reusable);
    assertType('array{first: int|null, values: list<int>}', $strings |> $lengthPivoted);
    assertType('array{nested: array{first: int|null, values: list<int>}}', $numbers |> pivot(nested: $reusable));
    assertType('array{int|null, list<int>}', $numbers |> pivot($first, $values));
    assertType('array{}', $numbers |> pivot());

    $mapped = mapping(format_number(...));
    assertType('iterable<object, string>', $objectKeys |> $mapped);
    assertType('iterable<string, string>', $stringKeys |> $mapped);
    assertType('string|null', $objectKeys |> $mapped |> $first);

    $flatMapped = flatMapping(static fn(int $value): iterable => [$value => format_number($value)]);
    assertType('iterable<int, string>', $objectKeys |> $flatMapped);
    assertType('iterable<int, string>', $stringKeys |> $flatMapped);
    assertType('iterable<int, int>', $objectKeys |> $flatMapped |> mapping(text_length(...)));

    $filtered = filtering(static fn(int $value): bool => $value > 0);
    assertType('iterable<object, int>', $objectKeys |> $filtered);
    assertType('iterable<string, int>', $stringKeys |> $filtered);
    assertType('iterable<int<0, max>, int>', $numbers |> $filtered);

    $dropped = dropping(2);
    assertType('iterable<object, int>', $objectKeys |> $dropped);
    assertType('iterable<string, int>', $stringKeys |> $dropped);
    assertType('iterable<int<0, max>, int>', $numbers |> $dropped);

    $taken = taking(2);
    assertType('iterable<object, int>', $objectKeys |> $taken);
    assertType('iterable<string, int>', $stringKeys |> $taken);
    assertType('iterable<int<0, max>, int>', $numbers |> $taken);

    $takenWhile = takingWhile(static fn(int $value): bool => $value > 0);
    assertType('iterable<object, int>', $objectKeys |> $takenWhile);
    assertType('iterable<string, int>', $stringKeys |> $takenWhile);
    assertType('iterable<int<0, max>, int>', $numbers |> $takenWhile);

    $droppedWhile = droppingWhile(static fn(int $value): bool => $value > 0);
    assertType('iterable<object, int>', $objectKeys |> $droppedWhile);
    assertType('iterable<string, int>', $stringKeys |> $droppedWhile);
    assertType('iterable<int<0, max>, int>', $numbers |> $droppedWhile);

    assertType('int|null', $first->__invoke($numbers));
    assertType('list<int>', $values->__invoke(...)($numbers));
    assertType('iterable<string, string>', $stringKeys |> mapping($format->transform(...)));
    assertType('array{total: int, count: int}', $numbers |> fold(['total' => 0, 'count' => 0], summarize(...)));

    $custom = Terminal::factory(static fn(): ObjectKeyExecution => new ObjectKeyExecution());
    $incrementedCustom = map(static fn(int $n): int => $n + 1) |> $custom->apply();
    assertType('string', $objectKeys |> $custom);
    assertType('string', $objectKeys |> $incrementedCustom);
    assertType('array{first: int|null, custom: string}', $objectKeys |> pivot(first: $first, custom: $custom));
    assertType('string', $custom->execution()->finish());
}

function transformation_defaults(int $integerFallback, string $textFallback, ?int $nullableInteger): void
{
    $positive = then(static fn(int $value): bool => $value > 0);
    assertType('int|null', $positive->transform(1));
    assertType('int', defaults($integerFallback)->transform(null));
    assertType('string', defaultsOr(created_text(...))->transform(null));

    $fixedDefault = defaults($textFallback);
    $factoryDefault = defaultsOr(created_text(...));
    assertType('int|string', $fixedDefault->transform($nullableInteger));
    assertType('int|string', $factoryDefault->transform($nullableInteger));
    assertType('string', ($fixedDefault |> $factoryDefault)->transform(null));
    $nestedDefault = defaults($fixedDefault);
    assertType('int|string', $nestedDefault->transform(null)->transform($nullableInteger));

    $nullableNumber = map(nullable_length(...));
    assertType('int|string', ($nullableNumber |> $fixedDefault)->transform('value'));
    assertType('int|string', ($nullableNumber |> $factoryDefault)->transform('value'));
    assertType('int', ($positive |> defaults(0))->transform(1));
    assertType('int', ($positive |> defaultsOr(static fn(): int => 0))->transform(1));

    $format = map(format_number(...));
    assertType('string|null', ($format |> then(static fn(string $value): bool => $value !== ''))->transform(1));
    assertType(
        'string',
        ($format |> then(static fn(string $value): bool => $value !== '') |> defaults('missing'))->transform(1),
    );

    $nullableDefaultValues = $nullableNumber |> $fixedDefault |> values()->apply();
    $nullableFactoryValues = $nullableNumber |> $factoryDefault |> values()->apply();
    $selectedValues = $positive |> values()->apply();
    $selectedDefaultValues = $positive |> defaults(0) |> values()->apply();
    assertType('list<int|string>', ['one', ''] |> $nullableDefaultValues);
    assertType('list<int|string>', ['one', ''] |> $nullableFactoryValues);
    assertType('list<int|null>', [1, 0] |> $selectedValues);
    assertType('list<int>', [1, 0] |> $selectedDefaultValues);
}

/**
 * @template TInput
 * @param TInput $value
 * @param Transform<mixed, TInput> $unrelated
 */
function unrelated_default_template(mixed $value, Transform $unrelated): void
{
    assertType(
        'TInput (function Nagare\\Tests\\PHPStan\\Fixtures\\unrelated_default_template(), argument)',
        $unrelated->transform('input'),
    );
}

/**
 * @param list<int> $numbers
 * @param list<string> $strings
 */
function pivoted_definitions(array $numbers, array $strings, bool $includeValues, string $name): void
{
    $terminals = ['first' => first(), 'values' => values()];
    $pivoted = pivot(...$terminals);
    assertType('array{first: int|null, values: list<int>}', $numbers |> $pivoted);
    assertType('array{first: string|null, values: list<string>}', $strings |> $pivoted);

    $optional = ['first' => first()];
    if ($includeValues) {
        $optional['values'] = values();
    }
    assertType('array{first: int|null, values?: list<int>}', $numbers |> pivot(...$optional));
    assertType('non-empty-array<string, int|null>', $numbers |> pivot(...[$name => first()]));

    $terminal = $includeValues ? first() : values();
    assertType('int|list<int>|null', $numbers |> $terminal);
    assertType('array{result: int|list<int>|null}', $numbers |> pivot(result: $terminal));
    assertType(
        'array{first: int|null, values: list<int>, last: int|null}',
        $numbers |> pivot(...['first' => first(), 'values' => values()], last: first()),
    );
    assertType('array{first: int|null, terminals: list<int>}', $numbers |> pivot(first: first(), terminals: values()));
    assertType(
        'array{result: list<string>|string|null}',
        $numbers |> pivot(result: $terminal->apply()(map(format_number(...)))),
    );
}

/**
 * @param list<int> $numbers
 * @param array<string, Terminal<mixed, int, string>> $terminals
 */
function dynamic_pivot(array $numbers, array $terminals): void
{
    assertType('array<string, string>', $numbers |> pivot(...$terminals));
}
