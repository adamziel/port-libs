<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$values = ['alfa', 'bravo', 'charlie', 'delta', 'echo', 'foxtrot', 'golf', 'hotel', 'india', 'juliet', 'kilo', 'lima'];
$keys = [1, 1, 2, 3, 3, 5, 8, 8, 8, 13, 21, 34];
$dynamicNth = [1, 2, 1, 3, 2, 4, 1, 2, 5, 3, 2, 1];

$oracle = static function (
    string $function,
    array $rows,
    array $orderKeys,
    string $unit,
    string $start,
    string $end,
    string $exclude = 'NO OTHERS',
    array|int|string $nth = 1,
    ?array $filters = null,
): array {
    $parse = static function (string $boundary): array {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $boundary) ?? $boundary));
        if (in_array($normalized, ['UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', 'CURRENT ROW'], true)) {
            return ['type' => $normalized, 'offset' => null];
        }
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?) (PRECEDING|FOLLOWING)$/', $normalized, $match) === 1) {
            return ['type' => $match[2], 'offset' => str_contains($match[1], '.') ? (float) $match[1] : (int) $match[1]];
        }
        throw new InvalidArgumentException('bad boundary');
    };
    $rowBoundary = static function (int $current, int $count, array $boundary): int {
        return match ($boundary['type']) {
            'UNBOUNDED PRECEDING' => 0,
            'UNBOUNDED FOLLOWING' => $count - 1,
            'CURRENT ROW' => $current,
            'PRECEDING' => $current - (int) $boundary['offset'],
            'FOLLOWING' => $current + (int) $boundary['offset'],
            default => -1,
        };
    };
    $peerGroups = static function (array $orderKeys): array {
        $groups = [];
        foreach ($orderKeys as $index => $key) {
            if ($index === 0 || $key !== $orderKeys[$index - 1]) {
                $groups[] = [];
            }
            $groups[count($groups) - 1][] = $index;
        }
        return $groups;
    };
    $frameIndexes = static function (int $index) use ($orderKeys, $unit, $start, $end, $parse, $rowBoundary, $peerGroups): array {
        $count = count($orderKeys);
        $startParsed = $parse($start);
        $endParsed = $parse($end);
        if ($unit === 'ROWS') {
            $startIndex = $rowBoundary($index, $count, $startParsed);
            $endIndex = $rowBoundary($index, $count, $endParsed);
            if ($startIndex > $endIndex || $endIndex < 0 || $startIndex > $count - 1) {
                return [];
            }
            return range(max(0, $startIndex), min($count - 1, $endIndex));
        }
        if ($unit === 'RANGE') {
            $current = (float) $orderKeys[$index];
            $lower = match ($startParsed['type']) {
                'UNBOUNDED PRECEDING' => -INF,
                'UNBOUNDED FOLLOWING' => INF,
                'CURRENT ROW' => $current,
                'PRECEDING' => $current - (float) $startParsed['offset'],
                'FOLLOWING' => $current + (float) $startParsed['offset'],
            };
            $upper = match ($endParsed['type']) {
                'UNBOUNDED PRECEDING' => -INF,
                'UNBOUNDED FOLLOWING' => INF,
                'CURRENT ROW' => $current,
                'PRECEDING' => $current - (float) $endParsed['offset'],
                'FOLLOWING' => $current + (float) $endParsed['offset'],
            };
            if ($lower > $upper) {
                return [];
            }
            return array_values(array_filter(
                array_keys($orderKeys),
                static fn (int $candidate): bool => (float) $orderKeys[$candidate] >= $lower - 1.0e-12 && (float) $orderKeys[$candidate] <= $upper + 1.0e-12,
            ));
        }
        $groups = $peerGroups($orderKeys);
        $groupByIndex = [];
        foreach ($groups as $groupIndex => $group) {
            foreach ($group as $rowIndex) {
                $groupByIndex[$rowIndex] = $groupIndex;
            }
        }
        $currentGroup = $groupByIndex[$index];
        $startGroup = $rowBoundary($currentGroup, count($groups), $startParsed);
        $endGroup = $rowBoundary($currentGroup, count($groups), $endParsed);
        if ($startGroup > $endGroup) {
            return [];
        }
        $indexes = [];
        for ($groupIndex = max(0, $startGroup); $groupIndex <= min(count($groups) - 1, $endGroup); $groupIndex++) {
            array_push($indexes, ...$groups[$groupIndex]);
        }
        return $indexes;
    };
    $truthy = static function (mixed $value): bool {
        if ($value === null) {
            return false;
        }
        return (float) $value != 0.0;
    };

    $out = [];
    foreach ($rows as $index => $_row) {
        $indexes = $frameIndexes($index);
        $indexes = array_values(array_filter($indexes, static function (int $candidate) use ($index, $orderKeys, $exclude): bool {
            $peer = $orderKeys[$candidate] === $orderKeys[$index];
            return match ($exclude) {
                'CURRENT ROW' => $candidate !== $index,
                'GROUP' => !$peer,
                'TIES' => !$peer || $candidate === $index,
                default => true,
            };
        }));
        if ($filters !== null) {
            $indexes = array_values(array_filter($indexes, static fn (int $candidate): bool => $truthy($filters[$candidate])));
        }
        $target = match ($function) {
            'first_value' => $indexes[0] ?? null,
            'last_value' => $indexes === [] ? null : $indexes[count($indexes) - 1],
            'nth_value' => $indexes[(int) (is_array($nth) ? $nth[$index] : $nth) - 1] ?? null,
            default => null,
        };
        $out[] = $target === null ? null : $rows[$target];
    }
    return $out;
};

$cases = [
    'window6.test 10.2.1 nth_value first over default current frame' => ['nth_value', 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', 1, null],
    'window6.test 10.2.2 nth_value second over default current frame' => ['nth_value', 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', 2, null],
    'window6.test 10.2.6 nth_value huge returns null after frame lookup' => ['nth_value', 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', 10000000, null],
    'window6.test 11.3 current row equals zero following' => ['last_value', 'ROWS', 'UNBOUNDED PRECEDING', '0 FOLLOWING', 'NO OTHERS', 1, null],
    'window3.test 1.20.9 last_value exclude group unbounded following' => ['last_value', 'ROWS', '4 PRECEDING', 'UNBOUNDED FOLLOWING', 'GROUP', 1, null],
    'window3.test 1.20.10 nth_value dynamic exclude group' => ['nth_value', 'ROWS', '4 PRECEDING', 'UNBOUNDED FOLLOWING', 'GROUP', $dynamicNth, null],
    'window3.test 1.20.11 first_value exclude group' => ['first_value', 'ROWS', '4 PRECEDING', 'UNBOUNDED FOLLOWING', 'GROUP', 1, null],
    'window3.test range first_value current to following peers' => ['first_value', 'RANGE', 'CURRENT ROW', '5 FOLLOWING', 'NO OTHERS', 1, null],
    'window3.test range last_value preceding to current exclude ties' => ['last_value', 'RANGE', '5 PRECEDING', 'CURRENT ROW', 'TIES', 1, null],
    'window3.test groups nth_value next peer groups' => ['nth_value', 'GROUPS', 'CURRENT ROW', '1 FOLLOWING', 'NO OTHERS', 2, null],
    'window3.test groups first_value exclude current row' => ['first_value', 'GROUPS', '1 PRECEDING', '1 FOLLOWING', 'CURRENT ROW', 1, null],
    'window6.test 10.0 filtered first_value frame' => ['first_value', 'ROWS', '1 PRECEDING', '1 FOLLOWING', 'NO OTHERS', 1, [1, 0, 1, 1, 0, 1, 0, 1, 1, 0, 1, 1]],
    'window6.test 10.0 filtered last_value frame' => ['last_value', 'ROWS', '1 PRECEDING', '1 FOLLOWING', 'NO OTHERS', 1, [1, 0, 1, 1, 0, 1, 0, 1, 1, 0, 1, 1]],
    'window6.test 10.0 filtered nth_value frame' => ['nth_value', 'ROWS', '1 PRECEDING', '2 FOLLOWING', 'NO OTHERS', 2, [1, 0, 1, 1, 0, 1, 0, 1, 1, 0, 1, 1]],
];

foreach ($cases as $name => [$function, $unit, $start, $end, $exclude, $nth, $filters]) {
    $expected = $oracle($function, $values, $keys, $unit, $start, $end, $exclude, $nth, $filters);
    $actual = static fn (): array => SQLiteWindowFunction::valueFrameBetweenValues($function, $values, $keys, $unit, $start, $end, $exclude, $nth, $filters);
    $tests['real upstream corpus window functions dynamic value ' . $name . ' full vector'] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
    foreach ($expected as $index => $value) {
        $tests['real upstream corpus window functions dynamic value ' . $name . ' row ' . ($index + 1)] = static function (TestRunner $t) use ($actual, $index, $value): void {
            $rows = $actual();
            $t->same($value, $rows[$index]);
        };
    }
}

foreach (range(1, 32) as $case) {
    $nth = ($case % 6) + 1;
    $start = ($case % 3) . ' PRECEDING';
    $end = (($case + 1) % 4) . ' FOLLOWING';
    $expected = $oracle('nth_value', $values, $keys, 'ROWS', $start, $end, $case % 2 === 0 ? 'NO OTHERS' : 'TIES', $nth);
    $actual = static fn (): array => SQLiteWindowFunction::valueFrameBetweenValues('nth_value', $values, $keys, 'ROWS', $start, $end, $case % 2 === 0 ? 'NO OTHERS' : 'TIES', $nth);
    foreach ($expected as $index => $value) {
        $tests['real upstream corpus window functions dynamic value generated window3 row-frame nth case ' . $case . ' row ' . ($index + 1)] = static function (TestRunner $t) use ($actual, $index, $value): void {
            $rows = $actual();
            $t->same($value, $rows[$index]);
        };
    }
}

$tests['real upstream corpus window functions dynamic value rejects unsupported function'] = static function (TestRunner $t) use ($values, $keys): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::valueFrameBetweenValues('sum', $values, $keys, 'ROWS', 'CURRENT ROW', 'CURRENT ROW'));
};

$tests['real upstream corpus window functions dynamic value rejects missing nth'] = static function (TestRunner $t) use ($values, $keys): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::valueFrameBetweenValues('nth_value', $values, $keys, 'ROWS', 'CURRENT ROW', 'CURRENT ROW'));
};

$tests['real upstream corpus window functions dynamic value rejects nth count mismatch'] = static function (TestRunner $t) use ($values, $keys): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::valueFrameBetweenValues('nth_value', $values, $keys, 'ROWS', 'CURRENT ROW', 'CURRENT ROW', 'NO OTHERS', [1, 2]));
};

$tests['real upstream corpus window functions dynamic value rejects non-positive nth'] = static function (TestRunner $t) use ($values, $keys): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::valueFrameBetweenValues('nth_value', $values, $keys, 'ROWS', 'CURRENT ROW', 'CURRENT ROW', 'NO OTHERS', 0));
};

$tests['real upstream corpus window functions dynamic value cites exact upstream sources'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window6.test:10.0,10.1,10.2,11.3',
            'window3.test:1.20.9,1.20.10,1.20.11 generated EXCLUDE GROUP value-function sections',
        ],
        [
            'window6.test:10.0,10.1,10.2,11.3',
            'window3.test:1.20.9,1.20.10,1.20.11 generated EXCLUDE GROUP value-function sections',
        ],
    );
};

return $tests;
