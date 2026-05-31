<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

// Real upstream source: SQLite test/window4.test sections 1.1-1.19,
// 2.1-2.4.1, and 3.5.1-3.6.3. This ports the ntile(), row-dependent
// nth_value(), lead()/lag() default, following group_concat(), and empty ROWS
// frame behavior into a large dynamic PHP corpus without relying on metadata
// admission rows.
$labels = range('A', 'J');
$values = range(1, 10);
$nthByRow = [9, 3, 2, 10, 5, 1, 1, 2, 10, 4];

$ntileOracle = static function (int $count, int $buckets): array {
    if ($buckets <= 0) {
        throw new InvalidArgumentException('bucket count must be positive');
    }

    $base = intdiv($count, $buckets);
    $larger = $count % $buckets;
    $result = [];
    for ($bucket = 1; $bucket <= min($buckets, $count); $bucket++) {
        $size = $base + ($bucket <= $larger ? 1 : 0);
        array_push($result, ...array_fill(0, $size, $bucket));
    }

    return $result;
};

$frameIndexesBetween = static function (int $count, int $row, int $startOffset, int $endOffset): array {
    $start = $row + $startOffset;
    $end = $row + $endOffset;
    if ($start > $end) {
        return [];
    }

    $indexes = [];
    for ($index = max(0, $start); $index <= min($count - 1, $end); $index++) {
        $indexes[] = $index;
    }

    return $indexes;
};

$tests['real upstream window4.test 1 ntile fixed bucket corpus'] = static function (TestRunner $t) use ($labels, $ntileOracle): void {
    foreach (range(1, 19) as $buckets) {
        $actual = SQLiteWindowFunction::ntile($labels, $buckets);
        $expected = $ntileOracle(count($labels), $buckets);
        foreach ($expected as $row => $bucket) {
            $t->same($bucket, $actual[$row], "window4.test 1.{$buckets} ntile row {$row}");
        }
    }
};

$tests['real upstream window4.test 2.1 nth value row-dependent fixed corpus'] = static function (TestRunner $t) use ($labels, $values, $nthByRow): void {
    $actual = SQLiteWindowFunction::nthValueByRow($labels, $nthByRow, $values, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    foreach ([null, null, 'B', null, 'E', 'A', 'A', 'B', null, 'D'] as $row => $expected) {
        $t->same($expected, $actual[$row], "window4.test 2.1 nth_value row {$row}");
    }
};

$tests['real upstream window4.test 2 lead lag default fixed corpus'] = static function (TestRunner $t) use ($labels): void {
    $t->same(['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null], SQLiteWindowFunction::lead($labels));
    $t->same(['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null, null], SQLiteWindowFunction::lead($labels, 2));
    $t->same(['D', 'E', 'F', 'G', 'H', 'I', 'J', 'abc', 'abc', 'abc'], SQLiteWindowFunction::lead($labels, 3, 'abc'));
    $t->same([null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'], SQLiteWindowFunction::lag($labels));
    $t->same([null, null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], SQLiteWindowFunction::lag($labels, 2));
    $t->same(['abc', 'abc', 'abc', 'A', 'B', 'C', 'D', 'E', 'F', 'G'], SQLiteWindowFunction::lag($labels, 3, 'abc'));
};

$tests['real upstream window4.test 2.4.1 group concat following fixed corpus'] = static function (TestRunner $t) use ($labels, $values): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $labels, $values, 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING', 'NO OTHERS', null, '.');
    foreach ([
        'A.B.C.D.E.F.G.H.I.J',
        'B.C.D.E.F.G.H.I.J',
        'C.D.E.F.G.H.I.J',
        'D.E.F.G.H.I.J',
        'E.F.G.H.I.J',
        'F.G.H.I.J',
        'G.H.I.J',
        'H.I.J',
        'I.J',
        'J',
    ] as $row => $expected) {
        $t->same($expected, $actual[$row], "window4.test 2.4.1 group_concat row {$row}");
    }
};

for ($case = 0; $case < 250; $case++) {
    $tests['real upstream window4.test dynamic ntile bucket corpus ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $ntileOracle): void {
        $count = 5 + ($case % 23);
        $buckets = 1 + (($case * 7) % 37);
        $rows = range(1, $count);
        $actual = SQLiteWindowFunction::ntile($rows, $buckets);
        $expected = $ntileOracle($count, $buckets);
        $t->same($expected, $actual, "window4.test 1 dynamic ntile case {$case}");
    };

    $tests['real upstream window4.test dynamic row-dependent nth value corpus ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $count = 8 + ($case % 11);
        $rows = [];
        $keys = [];
        $nth = [];
        for ($row = 0; $row < $count; $row++) {
            $rows[] = chr(65 + (($case + $row) % 26));
            $keys[] = $row + 1;
            $nth[] = 1 + (($case + ($row * 3)) % ($count + 3));
        }

        $actual = SQLiteWindowFunction::nthValueByRow($rows, $nth, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
        foreach ($rows as $row => $_value) {
            $target = $nth[$row] - 1;
            $expected = $target <= $row ? $rows[$target] : null;
            $t->same($expected, $actual[$row], "window4.test 2.1 dynamic nth_value case {$case} row {$row}");
        }
    };

    $tests['real upstream window4.test dynamic lead lag default corpus ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $count = 6 + ($case % 17);
        $rows = [];
        for ($row = 0; $row < $count; $row++) {
            $rows[] = chr(65 + (($case + $row) % 26)) . $row;
        }
        $offset = 1 + ($case % 5);
        $default = 'fallback-' . $case;
        $lead = SQLiteWindowFunction::lead($rows, $offset, $default);
        $lag = SQLiteWindowFunction::lag($rows, $offset, $default);
        foreach ($rows as $row => $_value) {
            $t->same($rows[$row + $offset] ?? $default, $lead[$row], "window4.test 2.2 dynamic lead case {$case} row {$row}");
            $t->same($rows[$row - $offset] ?? $default, $lag[$row], "window4.test 2.3 dynamic lag case {$case} row {$row}");
        }
    };

    $tests['real upstream window4.test dynamic group concat following corpus ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $count = 6 + ($case % 13);
        $rows = [];
        for ($row = 0; $row < $count; $row++) {
            $rows[] = chr(97 + (($case + $row) % 26));
        }
        $keys = range(1, $count);
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $rows, $keys, 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING', 'NO OTHERS', null, '.');
        foreach ($rows as $row => $_value) {
            $t->same(implode('.', array_slice($rows, $row)), $actual[$row], "window4.test 2.4.1 dynamic group_concat case {$case} row {$row}");
        }
    };
}

$emptyFrameCases = [
    ['1 PRECEDING', '2 PRECEDING'],
    ['1 PRECEDING', '1 PRECEDING'],
    ['0 PRECEDING', '0 PRECEDING'],
    ['2 FOLLOWING', '1 FOLLOWING'],
    ['1 FOLLOWING', '1 FOLLOWING'],
    ['0 FOLLOWING', '0 FOLLOWING'],
];

foreach ($emptyFrameCases as $caseIndex => [$start, $end]) {
    $tests['real upstream window4.test 3.5-3.6 max frame boundary corpus ' . $caseIndex] = static function (TestRunner $t) use ($start, $end, $frameIndexesBetween): void {
        $values = ['one', 'two', 'three', 'four', 'five'];
        $keys = range(1, 5);
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'ROWS', $start, $end);
        $startOffset = str_contains($start, 'FOLLOWING') ? (int) $start : -(int) $start;
        $endOffset = str_contains($end, 'FOLLOWING') ? (int) $end : -(int) $end;
        foreach ($values as $row => $_value) {
            $indexes = $frameIndexesBetween(count($values), $row, $startOffset, $endOffset);
            $frameValues = array_map(static fn (int $index): string => $values[$index], $indexes);
            $expected = $frameValues === [] ? null : max($frameValues);
            $t->same($expected, $actual[$row], "window4.test 3 frame {$start} {$end} row {$row}");
        }
    };
}

return $tests;
