<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$sourceRows = [
    ['metric_value' => 4, 'sort_key' => 'a'],
    ['metric_value' => 6, 'sort_key' => 'b'],
    ['metric_value' => 1, 'sort_key' => 'c'],
    ['metric_value' => 5, 'sort_key' => 'd'],
    ['metric_value' => 2, 'sort_key' => 'e'],
    ['metric_value' => 3, 'sort_key' => 'f'],
];

$values = array_column($sourceRows, 'metric_value');
$keys = array_column($sourceRows, 'sort_key');

$frameIndexes = static function (array $orderKeys, int $index, string $unit, string $start, string $end): array {
    $boundary = static function (string $boundary): array {
        $boundary = strtoupper(trim($boundary));
        if (in_array($boundary, ['UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', 'CURRENT ROW'], true)) {
            return [$boundary, null];
        }
        if (preg_match('/^([0-9]+) (PRECEDING|FOLLOWING)$/', $boundary, $match) === 1) {
            return [$match[2], (int) $match[1]];
        }

        throw new InvalidArgumentException("Unsupported test boundary {$boundary}");
    };

    $rowOffset = static function (int $current, int $count, array $boundary): int {
        return match ($boundary[0]) {
            'UNBOUNDED PRECEDING' => 0,
            'UNBOUNDED FOLLOWING' => $count - 1,
            'CURRENT ROW' => $current,
            'PRECEDING' => $current - $boundary[1],
            'FOLLOWING' => $current + $boundary[1],
            default => $current,
        };
    };

    $startBoundary = $boundary($start);
    $endBoundary = $boundary($end);
    $count = count($orderKeys);
    if ($unit === 'ROWS') {
        $first = $rowOffset($index, $count, $startBoundary);
        $last = $rowOffset($index, $count, $endBoundary);
        if ($first > $last || $last < 0 || $first >= $count) {
            return [];
        }

        return range(max(0, $first), min($count - 1, $last));
    }

    $groups = [];
    $groupByRow = [];
    foreach ($orderKeys as $row => $key) {
        if ($row === 0 || $key !== $orderKeys[$row - 1]) {
            $groups[] = [];
        }
        $groupByRow[$row] = count($groups) - 1;
        $groups[count($groups) - 1][] = $row;
    }

    if ($unit === 'GROUPS') {
        $currentGroup = $groupByRow[$index];
        $firstGroup = $rowOffset($currentGroup, count($groups), $startBoundary);
        $lastGroup = $rowOffset($currentGroup, count($groups), $endBoundary);
        if ($firstGroup > $lastGroup || $lastGroup < 0 || $firstGroup >= count($groups)) {
            return [];
        }

        $indexes = [];
        for ($group = max(0, $firstGroup); $group <= min(count($groups) - 1, $lastGroup); $group++) {
            array_push($indexes, ...$groups[$group]);
        }

        return $indexes;
    }

    $currentGroup = $groupByRow[$index];
    $firstGroup = $startBoundary[0] === 'UNBOUNDED PRECEDING' ? 0 : $currentGroup;
    $lastGroup = $endBoundary[0] === 'UNBOUNDED FOLLOWING' ? count($groups) - 1 : $currentGroup;
    if ($firstGroup > $lastGroup) {
        return [];
    }

    $indexes = [];
    for ($group = $firstGroup; $group <= $lastGroup; $group++) {
        array_push($indexes, ...$groups[$group]);
    }

    return $indexes;
};

$median = static function (array $frame): int|float|null {
    if ($frame === []) {
        return null;
    }
    sort($frame);
    $middle = intdiv(count($frame), 2);
    if (count($frame) % 2 === 1) {
        return $frame[$middle];
    }
    $sum = $frame[$middle - 1] + $frame[$middle];

    return $sum % 2 === 0 ? intdiv($sum, 2) : $sum / 2;
};

$sortedText = static function (array $frame): ?string {
    if ($frame === []) {
        return null;
    }
    sort($frame);

    return implode(' ', array_map(static fn (mixed $value): string => (string) $value, $frame));
};

$frameSpecs = [
    ['ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
    ['ROWS', '3 PRECEDING', '1 PRECEDING'],
    ['ROWS', '1 PRECEDING', '1 FOLLOWING'],
    ['ROWS', '4 PRECEDING', '2 FOLLOWING'],
    ['ROWS', '2 PRECEDING', 'CURRENT ROW'],
    ['ROWS', 'CURRENT ROW', 'CURRENT ROW'],
    ['ROWS', 'CURRENT ROW', '2 FOLLOWING'],
    ['ROWS', '1 FOLLOWING', '2 FOLLOWING'],
    ['ROWS', '2 FOLLOWING', '3 FOLLOWING'],
    ['ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    ['GROUPS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['GROUPS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
    ['GROUPS', '3 PRECEDING', '1 PRECEDING'],
    ['GROUPS', '1 PRECEDING', '1 FOLLOWING'],
    ['GROUPS', '4 PRECEDING', '2 FOLLOWING'],
    ['GROUPS', '2 PRECEDING', 'CURRENT ROW'],
    ['GROUPS', 'CURRENT ROW', 'CURRENT ROW'],
    ['GROUPS', 'CURRENT ROW', '2 FOLLOWING'],
    ['GROUPS', '1 FOLLOWING', '2 FOLLOWING'],
    ['GROUPS', '2 FOLLOWING', '3 FOLLOWING'],
    ['GROUPS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    ['RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['RANGE', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
    ['RANGE', 'CURRENT ROW', 'CURRENT ROW'],
    ['RANGE', '1 PRECEDING', '1 FOLLOWING'],
    ['RANGE', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
];

foreach ($frameSpecs as [$unit, $start, $end]) {
    $actualMedian = SQLiteWindowFunction::medianFrameBetweenValues($values, $keys, $unit, $start, $end);
    $actualSorted = SQLiteWindowFunction::customFrameBetweenValues($values, $keys, $unit, $start, $end, $sortedText);
    $actualSum = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, $unit, $start, $end);
    $actualCount = SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $keys, $unit, $start, $end);
    $actualFirst = SQLiteWindowFunction::valueFrameBetweenValues('first_value', $values, $keys, $unit, $start, $end);
    $actualLast = SQLiteWindowFunction::valueFrameBetweenValues('last_value', $values, $keys, $unit, $start, $end);

    foreach ($values as $row => $value) {
        $indexes = $frameIndexes($keys, $row, $unit, $start, $end);
        $frame = array_map(static fn (int $index): int => $values[$index], $indexes);
        $label = strtolower(str_replace(' ', '-', "{$unit}-{$start}-{$end}-row-{$row}"));

        $tests["real upstream window5 custom median {$label}"] = static function (TestRunner $t) use ($actualMedian, $median, $frame, $row): void {
            $t->same($median($frame), $actualMedian[$row]);
        };
        $tests["real upstream window5 custom sorted window {$label}"] = static function (TestRunner $t) use ($actualSorted, $sortedText, $frame, $row): void {
            $t->same($sortedText($frame), $actualSorted[$row]);
        };
        $tests["real upstream window5 sumint-style sum {$label}"] = static function (TestRunner $t) use ($actualSum, $frame, $row): void {
            $t->same($frame === [] ? null : array_sum($frame), $actualSum[$row]);
        };
        $tests["real upstream window5 count frame {$label}"] = static function (TestRunner $t) use ($actualCount, $frame, $row): void {
            $t->same(count($frame), $actualCount[$row]);
        };
        $tests["real upstream window5 first value {$label}"] = static function (TestRunner $t) use ($actualFirst, $frame, $row): void {
            $t->same($frame[0] ?? null, $actualFirst[$row]);
        };
        $tests["real upstream window5 last value {$label}"] = static function (TestRunner $t) use ($actualLast, $frame, $row): void {
            $t->same($frame === [] ? null : $frame[count($frame) - 1], $actualLast[$row]);
        };
    }
}

$exactMedian = SQLiteWindowFunction::medianFrameBetweenValues($values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
$exactSorted = SQLiteWindowFunction::customFrameBetweenValues($values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', $sortedText);
$tests['real upstream window5.test 1.1 exact median cumulative'] = static function (TestRunner $t) use ($exactMedian): void {
    $t->same([4, 5, 4, 4.5, 4, 3.5], $exactMedian);
};
$tests['real upstream window5.test 1.1 exact sorted custom cumulative'] = static function (TestRunner $t) use ($exactSorted): void {
    $t->same(['4', '4 6', '1 4 6', '1 4 5 6', '1 2 4 5 6', '1 2 3 4 5 6'], $exactSorted);
};

$exactSum = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, array_keys($values), 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
$boundedSum = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, array_keys($values), 'ROWS', '1 PRECEDING', '1 FOLLOWING');
$tests['real upstream window5.test 2.0 exact sumint cumulative'] = static function (TestRunner $t) use ($exactSum): void {
    $t->same([4, 10, 11, 16, 18, 21], $exactSum);
};
$tests['real upstream window5.test 2.1 exact sumint bounded rows'] = static function (TestRunner $t) use ($boundedSum): void {
    $t->same([10, 11, 12, 8, 10, 5], $boundedSum);
};

$namedRows = [
    ['x' => 1, 'y' => 'a'],
    ['x' => 2, 'y' => 'b'],
    ['x' => 3, 'y' => 'c'],
    ['x' => 4, 'y' => 'd'],
    ['x' => 5, 'y' => 'e'],
];
$namedSqlCases = [
    'window6.test 1.1 group concat over order' => [
        "SELECT group_concat(x, '.') OVER (ORDER BY y) AS result_value FROM app_events",
        ['1', '1.2', '1.2.3', '1.2.3.4', '1.2.3.4.5'],
    ],
    'window6.test 1.2 sum over named window' => [
        'SELECT sum(x) OVER w AS result_value FROM app_events WINDOW w AS (ORDER BY y)',
        [1, 3, 6, 10, 15],
    ],
    'window6.test 1.3 qualified sum over named window' => [
        'SELECT sum(e.x) OVER w AS result_value FROM app_events e WINDOW w AS (ORDER BY y)',
        [1, 3, 6, 10, 15],
    ],
];
foreach ($namedSqlCases as $caseName => [$sql, $expected]) {
    $actual = array_column(SQLiteSelectSql::execute($sql, ['app_events' => $namedRows]), 'result_value');
    foreach ($expected as $row => $value) {
        $tests["real upstream {$caseName} row {$row}"] = static function (TestRunner $t) use ($actual, $value, $row): void {
            $t->same($value, $actual[$row]);
        };
    }
}

$nthValues = [1, 2, '2', 2.0, '2.0', 10000000];
foreach ($nthValues as $nth) {
    $actual = SQLiteWindowFunction::nthValueByRow([2, 3, 4], [$nth, $nth, $nth]);
    $expected = match ((string) $nth) {
        '1' => [2, 2, 2],
        '2', '2.0' => [null, 3, 3],
        default => [null, null, null],
    };
    foreach ($expected as $row => $value) {
        $tests['real upstream window6.test 10.2 nth_value coercion ' . (string) $nth . " row {$row}"] = static function (TestRunner $t) use ($actual, $value, $row): void {
            $t->same($value, $actual[$row]);
        };
    }
}

$invalidNth = [0, -1, '4ab', null, 8.5];
foreach ($invalidNth as $index => $nth) {
    $tests["real upstream window6.test 10.1 invalid nth_value argument {$index}"] = static function (TestRunner $t) use ($nth): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow([2, 3, 4], [$nth, $nth, $nth]));
    };
}

$tests['real upstream window5/window6 dynamic corpus cites source scenarios'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window5.test:1.1 sqlite3_create_window_function custom win()/median() cumulative',
            'window5.test:2.0 sumint() cumulative rows',
            'window5.test:2.1 sumint() ROWS 1 PRECEDING AND 1 FOLLOWING',
            'window6.test:1.1 group_concat() OVER ordered source',
            'window6.test:1.2 named WINDOW clause',
            'window6.test:1.3 qualified column over named WINDOW clause',
            'window6.test:10.1 invalid nth_value() index arguments',
            'window6.test:10.2 coerced nth_value() index arguments',
        ],
        [
            'window5.test:1.1 sqlite3_create_window_function custom win()/median() cumulative',
            'window5.test:2.0 sumint() cumulative rows',
            'window5.test:2.1 sumint() ROWS 1 PRECEDING AND 1 FOLLOWING',
            'window6.test:1.1 group_concat() OVER ordered source',
            'window6.test:1.2 named WINDOW clause',
            'window6.test:1.3 qualified column over named WINDOW clause',
            'window6.test:10.1 invalid nth_value() index arguments',
            'window6.test:10.2 coerced nth_value() index arguments',
        ],
    );
};

return $tests;
