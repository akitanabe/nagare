<?php

declare(strict_types=1);

namespace Nagare\Tests\PHPStan\Fixtures;

use Nagare\Terminal;
use Nagare\Tests\PHPStan\ObjectKeyExecution;
use Nagare\Tests\TerminalAdapter\StringLengthAdapter;

use function Nagare\Adapter\fallback as adapterFallback;
use function Nagare\Adapter\fallbackWith as adapterFallbackWith;
use function Nagare\Adapter\filter as adapterFilter;
use function Nagare\Adapter\filterMap as adapterFilterMap;
use function Nagare\Adapter\map;
use function Nagare\Adapter\map as adapterMap;
use function Nagare\Adapter\none as adapterNone;
use function Nagare\Adapter\some as adapterSome;
use function Nagare\Adapter\then as adapterThen;
use function Nagare\Aggregation\average;
use function Nagare\Aggregation\count;
use function Nagare\Aggregation\countBy;
use function Nagare\Aggregation\fold;
use function Nagare\Aggregation\groupBy;
use function Nagare\Aggregation\join;
use function Nagare\Aggregation\max;
use function Nagare\Aggregation\maxBy;
use function Nagare\Aggregation\min;
use function Nagare\Aggregation\minBy;
use function Nagare\Aggregation\pivot;
use function Nagare\Aggregation\sum;
use function Nagare\Aggregation\unique;
use function Nagare\Aggregation\uniqueBy;
use function Nagare\Materialization\associate;
use function Nagare\Materialization\entries;
use function Nagare\Materialization\keys;
use function Nagare\Materialization\values;
use function Nagare\Pipeline\chunking;
use function Nagare\Pipeline\distinct;
use function Nagare\Pipeline\dropping;
use function Nagare\Pipeline\droppingWhile;
use function Nagare\Pipeline\each;
use function Nagare\Pipeline\filtering;
use function Nagare\Pipeline\flatMapping;
use function Nagare\Pipeline\mapping;
use function Nagare\Pipeline\taking;
use function Nagare\Pipeline\takingWhile;
use function Nagare\Query\all;
use function Nagare\Query\any;
use function Nagare\Query\contains;
use function Nagare\Query\find;
use function Nagare\Query\first;
use function Nagare\Query\isEmpty;
use function Nagare\Query\last;
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

function select_string_key(int $value, string $key): string
{
    return (string) $value;
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
 */
function adapter_and_query_results(array $numbers, array $strings, array $positiveNumbers): void
{
    $format = map(format_number(...));

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

    $empty = isEmpty();
    $containsOne = contains(1);
    assertType('bool', $numbers |> $empty);
    assertType('bool', $strings |> $empty);
    assertType('bool', [] |> $empty);
    assertType('bool', $numbers |> $containsOne);
    assertType('bool', $strings |> $containsOne);
    assertType('bool', [] |> $containsOne);

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

    $hookedFound = $formattedFound->hook(static function (int $value, mixed $key): void {});
    assertType('Nagare\\Terminal<mixed, int, string|null>', $hookedFound);
    assertType('string|null', $numbers |> $hookedFound);
}

/**
 * @param list<int> $numbers
 * @param list<string> $strings
 * @param iterable<object, int> $objectKeys
 * @param array<string, int> $stringKeys
 */
function materialization_and_aggregation_results(
    array $numbers,
    array $strings,
    iterable $objectKeys,
    array $stringKeys,
): void {
    $values = values();
    assertType('list<int>', $numbers |> $values);
    assertType('list<string>', $strings |> $values);
    assertType('list<int>', $objectKeys |> $values);

    $keys = keys();
    assertType('list<int<0, max>>', $numbers |> $keys);
    assertType('list<string>', $stringKeys |> $keys);
    assertType('list<object>', $objectKeys |> $keys);

    $associated = associate();
    assertType('array<int<0, max>, int>', $numbers |> $associated);
    assertType('array<string, int>', $stringKeys |> $associated);
    assertType('array<string, int>', $stringKeys |> associate(null));

    $selectedAssociation = associate(select_string_key(...));
    assertType('array<string, int>', $stringKeys |> $selectedAssociation);

    $entries = entries();
    assertType('list<array{int<0, max>, int}>', $numbers |> $entries);
    assertType('list<array{string, int}>', $stringKeys |> $entries);
    assertType('list<array{object, int}>', $objectKeys |> $entries);

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
    $allNonEmpty = all(static fn(string $value): bool => $value !== '');
    assertType('bool', $numbers |> $hasPositive);
    assertType('bool', $numbers |> $allPositive);
    assertType('bool', $strings |> $allNonEmpty);

    $empty = isEmpty();
    $containsOne = contains(1);
    assertType('bool', $objectKeys |> $empty);
    assertType('bool', $objectKeys |> $containsOne);
}

/**
 * @param list<int> $numbers
 * @param list<string> $strings
 */
function collection_aggregation_results(array $numbers, array $strings): void
{
    $unique = unique();
    $uniqueByParity = uniqueBy(static fn(int $value): int => $value % 2);
    $countByParity = countBy(static fn(int $value): int => $value % 2);
    $groupByParity = groupBy(static fn(int $value): int => $value % 2);
    assertType('list<int>', $numbers |> $unique);
    assertType('list<string>', $strings |> $unique);
    assertType('list<int>', $numbers |> $uniqueByParity);
    assertType('array<int, int>', $numbers |> $countByParity);
    assertType('array<int, list<int>>', $numbers |> $groupByParity);
    assertType(
        'array{unique: list<int>, uniqueBy: list<int>, counts: array<int, int>, groups: array<int, list<int>>}',
        $numbers |> pivot(unique: $unique, uniqueBy: $uniqueByParity, counts: $countByParity, groups: $groupByParity),
    );

    $uniqueStringsByLength = uniqueBy(text_length(...));
    $countStringsByLength = countBy(text_length(...));
    $groupStringsByLength = groupBy(text_length(...));
    assertType('list<string>', $strings |> $uniqueStringsByLength);
    assertType('array<int, int>', $strings |> $countStringsByLength);
    assertType('array<int, list<string>>', $strings |> $groupStringsByLength);
    assertType(
        'array{uniqueBy: list<string>, counts: array<int, int>, groups: array<int, list<string>>}',
        $strings
            |> pivot(uniqueBy: $uniqueStringsByLength, counts: $countStringsByLength, groups: $groupStringsByLength),
    );
}

/**
 * @param list<int> $numbers
 * @param list<string> $strings
 * @param iterable<object, int> $objectKeys
 */
function terminal_composition_results(array $numbers, array $strings, iterable $objectKeys): void
{
    $format = map(format_number(...));
    $length = map(text_length(...));
    $first = first();
    $values = values();
    $sum = fold(0, static fn(int $sum, int $value): int => $sum + $value);
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

    assertType('int|null', $first->__invoke($numbers));
    assertType('list<int>', $values->__invoke(...)($numbers));
    assertType('array{total: int, count: int}', $numbers |> fold(['total' => 0, 'count' => 0], summarize(...)));

    $custom = Terminal::factory(static fn(): ObjectKeyExecution => new ObjectKeyExecution());
    $incrementedCustom = map(static fn(int $n): int => $n + 1) |> $custom->apply();
    $hookedCustom = $custom->hook(static function (int $value, object $key): void {});
    assertType('string', $objectKeys |> $custom);
    assertType('string', $objectKeys |> $incrementedCustom);
    assertType('Nagare\\Terminal<object, int, string>', $hookedCustom);
    assertType('array{first: int|null, custom: string}', $objectKeys |> pivot(first: $first, custom: $custom));
    assertType('string', $custom->execution()->finish());
}

/**
 * @param list<int> $numbers
 * @param iterable<object, int> $objectKeys
 * @param array<string, int> $stringKeys
 */
function pipeline_results(array $numbers, iterable $objectKeys, array $stringKeys, bool $preserveKeys): void
{
    $format = map(format_number(...));
    $first = first();
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

    $chunks = chunking(2);
    assertType('iterable<int, array<string, int>>', $stringKeys |> $chunks);
    assertType('iterable<int, array<int<0, max>, int>>', $numbers |> $chunks);

    $preservedChunks = chunking(2, preserveKeys: true);
    assertType('iterable<int, array<string, int>>', $stringKeys |> $preservedChunks);
    assertType('iterable<int, array<int<0, max>, int>>', $numbers |> $preservedChunks);

    $valueChunks = chunking(2, preserveKeys: false);
    assertType('iterable<int, list<int>>', $stringKeys |> $valueChunks);
    assertType('iterable<int, list<int>>', $objectKeys |> $valueChunks);

    $dynamicChunks = chunking(2, preserveKeys: $preserveKeys);
    assertType('iterable<int, array<int<0, max>|string, int>>', $stringKeys |> $dynamicChunks);
    assertType('iterable<int, array<int<0, max>, int>>', $numbers |> $dynamicChunks);

    $distinct = distinct();
    assertType('iterable<object, int>', $objectKeys |> $distinct);
    assertType('iterable<string, int>', $stringKeys |> $distinct);
    assertType('iterable<int<0, max>, int>', $numbers |> $distinct);

    assertType('iterable<string, string>', $stringKeys |> mapping(format_number(...)));

    $observed = each(static function (int $value, string $key): void {});
    assertType('iterable<string, int>', $stringKeys |> $observed);

    $observedObjectKeys = each(static function (int $value, object $key): void {});
    assertType('iterable<object, int>', $objectKeys |> $observedObjectKeys);

    $observesAnyInput = each(static function (mixed $value, mixed $key): void {});
    assertType('iterable<string, int>', $stringKeys |> $observesAnyInput);
    assertType('iterable<int<0, max>, int>', $numbers |> $observesAnyInput);
}

/**
 * @param list<int> $numbers
 * @param list<string> $strings
 * @param list<int|null> $nullableNumbers
 * @param list<string|null> $nullableStrings
 * @param iterable<object, string> $objectKeyedStrings
 */
function terminal_adapter_results(
    array $numbers,
    array $strings,
    array $nullableNumbers,
    array $nullableStrings,
    iterable $objectKeyedStrings,
): void {
    $format = adapterMap(format_number(...));
    $positiveOrNull = adapterThen(static fn(int $value): bool => $value > 0);
    $positive = adapterFilter(static fn(int $value): bool => $value > 0);
    $parsed = adapterFilterMap(static fn(string $value): ?int => $value === '' ? null : strlen($value));
    assertType('Nagare\\Adapter\\Definition<int, string>', $format);
    assertType('Nagare\\Adapter\\Definition<int, int|null>', $positiveOrNull);
    assertType('Nagare\\Adapter\\Definition<int, int>', $positive);
    assertType('Nagare\\Adapter\\Definition<string, int<1, max>>', $parsed);

    $formattedValues = $format |> values()->apply();
    $positiveOrNullValues = $positiveOrNull |> values()->apply();
    $positiveValues = $positive |> values()->apply();
    $presentValues = adapterSome() |> values()->apply();
    $absentValues = adapterNone() |> values()->apply();
    $parsedValues = $parsed |> values()->apply();
    assertType('list<string>', $numbers |> $formattedValues);
    assertType('list<int|null>', $numbers |> $positiveOrNullValues);
    assertType('list<int>', $numbers |> $positiveValues);
    assertType('list<string>', $nullableStrings |> $presentValues);
    assertType('list<null>', $nullableStrings |> $absentValues);
    assertType('list<int<1, max>>', $strings |> $parsedValues);

    $fixedDefault = adapterFallback('missing');
    $factoryDefault = adapterFallbackWith(created_text(...));
    $fixedDefaultValues = $fixedDefault |> values()->apply();
    $factoryDefaultValues = $factoryDefault |> values()->apply();
    assertType("list<'missing'|int>", $nullableNumbers |> $fixedDefaultValues);
    assertType('list<string>', $nullableStrings |> $fixedDefaultValues);
    assertType('list<int|string>', $nullableNumbers |> $factoryDefaultValues);
    assertType('list<string>', $nullableStrings |> $factoryDefaultValues);

    $normalizedLength = adapterMap(nullable_length(...)) |> adapterSome() |> adapterMap(format_number(...));
    $defaultedLength = adapterMap(nullable_length(...)) |> adapterFallback(0);
    $factoryDefaultedLength = adapterMap(nullable_length(...)) |> adapterFallbackWith(static fn(): int => 0);
    assertType('Nagare\\Adapter\\Definition<string, string>', $normalizedLength);
    assertType('Nagare\\Adapter\\Definition<string, int>', $defaultedLength);
    assertType('Nagare\\Adapter\\Definition<string, int>', $factoryDefaultedLength);

    $allFactories = adapterMap(format_number(...))
        |> adapterFilter(static fn(string $value): bool => $value !== '')
        |> adapterThen(static fn(string $value): bool => $value !== '0')
        |> adapterFallback('missing')
        |> adapterFilterMap(nullable_length(...))
        |> adapterSome()
        |> adapterFallbackWith(static fn(): int => 0);
    assertType('Nagare\\Adapter\\Definition<int, int>', $allFactories);

    $formattedFirst = $format |> first()->apply();
    $formattedMinimum = $format |> min()->apply();
    $formattedUnique = $format |> unique()->apply();
    $formattedEntries = $format |> entries()->apply();
    $formattedAssociation = $format |> associate()->apply();
    $formattedPivot = $format |> pivot(first: first(), values: values(), minimum: min())->apply();
    assertType('string|null', $numbers |> $formattedFirst);
    assertType('string|null', $numbers |> $formattedMinimum);
    assertType('list<string>', $numbers |> $formattedUnique);
    assertType('list<array{int<0, max>, string}>', $numbers |> $formattedEntries);
    assertType('array<int<0, max>, string>', $numbers |> $formattedAssociation);
    assertType('array{first: string|null, values: list<string>, minimum: string|null}', $numbers |> $formattedPivot);

    $opaqueTerminal = Terminal::factory(static fn(): ObjectKeyExecution => new ObjectKeyExecution());
    $thirdPartyTerminal = new StringLengthAdapter() |> $opaqueTerminal->apply();
    assertType('Nagare\\Terminal<object, string, string>', $thirdPartyTerminal);
    assertType('string', $objectKeyedStrings |> $thirdPartyTerminal);
}

/**
 * @param list<int> $numbers
 * @param list<string> $strings
 * @mago-expect lint:no-boolean-flag-parameter Boolean branches intentionally exercise optional and union inference.
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

/**
 * @param list<int> $numbers
 * @param list<string> $strings
 * @param (callable(int): string)|null $selector
 */
function associate_selector_variants(array $numbers, array $strings, ?callable $selector, string $key): void
{
    $constantKey = associate(static fn(): string => $key);
    assertType('array<string, int>', $numbers |> $constantKey);
    assertType('array<string, string>', $strings |> $constantKey);
    assertType('array<int<0, max>|string, int>', $numbers |> associate($selector));
    assertType('array<123, int>', $numbers |> associate(static fn(int $value): string => '123'));
}

/**
 * @param array<string, int> $stringKeys
 * @param iterable<object, int> $objectKeys
 */
function materialization_composition(array $stringKeys, iterable $objectKeys): void
{
    $format = map(format_number(...));
    $formattedKeys = $format |> keys()->apply();
    $formattedEntries = $format |> entries()->apply();
    $formattedAssociation = $format |> associate()->apply();
    assertType('list<object>', $objectKeys |> $formattedKeys);
    assertType('list<array{object, string}>', $objectKeys |> $formattedEntries);
    assertType('array<string, string>', $stringKeys |> $formattedAssociation);
    assertType(
        'array{keys: list<string>, entries: list<array{string, int}>, associated: array<string, int>}',
        $stringKeys |> pivot(keys: keys(), entries: entries(), associated: associate()),
    );
}
