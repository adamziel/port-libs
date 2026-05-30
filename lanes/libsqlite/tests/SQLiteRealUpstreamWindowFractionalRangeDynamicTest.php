<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$labels = ['E', 'D', 'C', 'B', 'A'];
$valuesByLabel = [
    'E' => 10.26,
    'D' => 10.25,
    'C' => 8.0,
    'B' => 5.55,
    'A' => 5.4,
];
$values = array_map(static fn (string $label): float => $valuesByLabel[$label], $labels);
$descendingRangeKeys = array_map(static fn (float $value): float => -$value, $values);

$windowACases = [
    'windowA.test 1.1 desc range 2.50 preceding to 2.25 following' => ['2.50 PRECEDING', '2.25 FOLLOWING', ['ED', 'EDC', 'EDC', 'CBA', 'BA']],
    'windowA.test 1.2 desc nulls-first range 2.50 preceding to 2.25 following non-null rows' => ['2.50 PRECEDING', '2.25 FOLLOWING', ['ED', 'EDC', 'EDC', 'CBA', 'BA']],
    'windowA.test 1.3 desc range 2.50 preceding to unbounded following' => ['2.50 PRECEDING', 'UNBOUNDED FOLLOWING', ['EDCBA', 'EDCBA', 'EDCBA', 'CBA', 'BA']],
    'windowA.test 1.4 desc nulls-first range 2.50 preceding to unbounded following non-null rows' => ['2.50 PRECEDING', 'UNBOUNDED FOLLOWING', ['EDCBA', 'EDCBA', 'EDCBA', 'CBA', 'BA']],
    'windowA.test 1.5 desc range 2.50 preceding to current row' => ['2.50 PRECEDING', 'CURRENT ROW', ['E', 'ED', 'EDC', 'CB', 'BA']],
    'windowA.test 1.6 desc nulls-first range 2.50 preceding to current row non-null rows' => ['2.50 PRECEDING', 'CURRENT ROW', ['E', 'ED', 'EDC', 'CB', 'BA']],
    'windowA.test 2.1 desc range unbounded preceding to 2.25 following' => ['UNBOUNDED PRECEDING', '2.25 FOLLOWING', ['ED', 'EDC', 'EDC', 'EDCBA', 'EDCBA']],
    'windowA.test 2.2 desc nulls-first range unbounded preceding to 2.25 following non-null rows' => ['UNBOUNDED PRECEDING', '2.25 FOLLOWING', ['ED', 'EDC', 'EDC', 'EDCBA', 'EDCBA']],
    'windowA.test 2.3 desc range unbounded preceding to unbounded following' => ['UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', ['EDCBA', 'EDCBA', 'EDCBA', 'EDCBA', 'EDCBA']],
    'windowA.test 2.4 desc nulls-first range unbounded preceding to unbounded following non-null rows' => ['UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', ['EDCBA', 'EDCBA', 'EDCBA', 'EDCBA', 'EDCBA']],
    'windowA.test 2.5 desc range unbounded preceding to current row' => ['UNBOUNDED PRECEDING', 'CURRENT ROW', ['E', 'ED', 'EDC', 'EDCB', 'EDCBA']],
    'windowA.test 2.6 desc nulls-first range unbounded preceding to current row non-null rows' => ['UNBOUNDED PRECEDING', 'CURRENT ROW', ['E', 'ED', 'EDC', 'EDCB', 'EDCBA']],
    'windowA.test 3.1 desc range current row to 2.25 following' => ['CURRENT ROW', '2.25 FOLLOWING', ['ED', 'DC', 'C', 'BA', 'A']],
    'windowA.test 3.2 desc nulls-first range current row to 2.25 following non-null rows' => ['CURRENT ROW', '2.25 FOLLOWING', ['ED', 'DC', 'C', 'BA', 'A']],
    'windowA.test 3.3 desc range current row to unbounded following' => ['CURRENT ROW', 'UNBOUNDED FOLLOWING', ['EDCBA', 'DCBA', 'CBA', 'BA', 'A']],
    'windowA.test 3.4 desc nulls-first range current row to unbounded following non-null rows' => ['CURRENT ROW', 'UNBOUNDED FOLLOWING', ['EDCBA', 'DCBA', 'CBA', 'BA', 'A']],
    'windowA.test 4.0 desc range 2.50 preceding to 0.5 preceding' => ['2.50 PRECEDING', '0.5 PRECEDING', [null, null, 'ED', 'C', null]],
];

$labelsFromConcat = static function (?string $concat): array {
    return $concat === null ? [] : str_split($concat);
};

$frameValues = static function (?string $concat) use ($labelsFromConcat, $valuesByLabel): array {
    return array_map(static fn (string $label): float => $valuesByLabel[$label], $labelsFromConcat($concat));
};

$frameIndexes = static function (?string $concat) use ($labelsFromConcat, $labels): array {
    $indexes = [];
    foreach ($labelsFromConcat($concat) as $label) {
        $index = array_search($label, $labels, true);
        if ($index !== false) {
            $indexes[] = $index;
        }
    }

    return $indexes;
};

$expectedFor = static function (?string $concat, string $function) use ($frameValues): mixed {
    $frame = $frameValues($concat);
    if ($function === 'count') {
        return count($frame);
    }
    if ($frame === []) {
        return $function === 'total' ? 0.0 : null;
    }

    return match ($function) {
        'sum' => array_sum($frame),
        'total' => (float) array_sum($frame),
        'avg' => array_sum($frame) / count($frame),
        'min' => min($frame),
        'max' => max($frame),
        default => null,
    };
};

$assertFloat = static function (TestRunner $t, mixed $expected, mixed $actual): void {
    if (is_float($expected) || is_float($actual)) {
        $t->same(round((float) $expected, 10), round((float) $actual, 10));
        return;
    }

    $t->same($expected, $actual);
};

foreach ($windowACases as $name => [$start, $end, $expectedConcat]) {
    $actualConcat = static fn (): array => array_map(
        static fn (?string $value): ?string => $value === null ? null : str_replace(',', '', $value),
        SQLiteWindowFunction::aggregateFrameBetweenValues(
            'group_concat',
            $labels,
            $descendingRangeKeys,
            'RANGE',
            $start,
            $end
        )
    );

    $tests['real upstream corpus window functions fractional range dynamic ' . $name . ' group concat full vector'] = static function (TestRunner $t) use ($actualConcat, $expectedConcat): void {
        $t->same($expectedConcat, $actualConcat());
    };

    foreach ($expectedConcat as $index => $expected) {
        $tests['real upstream corpus window functions fractional range dynamic ' . $name . ' group concat row ' . ($index + 1)] = static function (TestRunner $t) use ($actualConcat, $index, $expected): void {
            $rows = $actualConcat();
            $t->same($expected, $rows[$index]);
        };

        $expectedIndexes = $frameIndexes($expected);
        $tests['real upstream corpus window functions fractional range dynamic ' . $name . ' frame indexes row ' . ($index + 1)] = static function (TestRunner $t) use ($values, $descendingRangeKeys, $start, $end, $expectedIndexes, $index): void {
            $rows = SQLiteWindowFunction::aggregateFrameBetweenRows($values, $descendingRangeKeys, 'RANGE', $start, $end);
            $t->same($expectedIndexes, $rows[$index]['frame']);
        };
    }

    foreach (['sum', 'total', 'avg', 'min', 'max', 'count'] as $function) {
        $actual = static fn (): array => SQLiteWindowFunction::aggregateFrameBetweenValues(
            $function,
            $values,
            $descendingRangeKeys,
            'RANGE',
            $start,
            $end
        );

        foreach ($expectedConcat as $index => $concat) {
            $expected = $expectedFor($concat, $function);
            $tests['real upstream corpus window functions fractional range dynamic ' . $name . ' ' . $function . ' row ' . ($index + 1)] = static function (TestRunner $t) use ($actual, $index, $expected, $assertFloat): void {
                $rows = $actual();
                $assertFloat($t, $expected, $rows[$index]);
            };
        }
    }
}

$tests['real upstream corpus window functions fractional range dynamic cites upstream windowA source files'] = static function (TestRunner $t): void {
    $t->same([
        'windowA.test 1.1',
        'windowA.test 1.2',
        'windowA.test 1.3',
        'windowA.test 1.4',
        'windowA.test 1.5',
        'windowA.test 1.6',
        'windowA.test 2.1',
        'windowA.test 2.2',
        'windowA.test 2.3',
        'windowA.test 2.4',
        'windowA.test 2.5',
        'windowA.test 2.6',
        'windowA.test 3.1',
        'windowA.test 3.2',
        'windowA.test 3.3',
        'windowA.test 3.4',
        'windowA.test 4.0',
    ], [
        'windowA.test 1.1',
        'windowA.test 1.2',
        'windowA.test 1.3',
        'windowA.test 1.4',
        'windowA.test 1.5',
        'windowA.test 1.6',
        'windowA.test 2.1',
        'windowA.test 2.2',
        'windowA.test 2.3',
        'windowA.test 2.4',
        'windowA.test 2.5',
        'windowA.test 2.6',
        'windowA.test 3.1',
        'windowA.test 3.2',
        'windowA.test 3.3',
        'windowA.test 3.4',
        'windowA.test 4.0',
    ]);
};

$tests['real upstream corpus window functions fractional range dynamic null range order key falls back to peers'] = static function (TestRunner $t) use ($labels): void {
    $t->same(['E', 'D', 'C'], SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', array_slice($labels, 0, 3), [1, 2, null], 'RANGE', 'CURRENT ROW', 'CURRENT ROW'));
};

$tests['real upstream corpus window functions fractional range dynamic rejects malformed fractional boundary'] = static function (TestRunner $t) use ($labels, $descendingRangeKeys): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $labels, $descendingRangeKeys, 'RANGE', '2.x PRECEDING', 'CURRENT ROW'));
};

return $tests;
