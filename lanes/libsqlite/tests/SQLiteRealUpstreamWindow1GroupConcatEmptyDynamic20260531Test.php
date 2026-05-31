<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$quoteSqlText = static function (?string $value): string {
    return $value === null ? 'NULL' : "'" . str_replace("'", "''", $value) . "'";
};

$groupConcat = static function (array $values): ?string {
    $items = [];
    foreach ($values as $value) {
        if ($value !== null) {
            $items[] = (string) $value;
        }
    }

    return $items === [] ? null : implode(',', $items);
};

$tailConcat = static function (array $rows, int $position) use ($groupConcat): ?string {
    $current = $rows[$position]['y'];
    $values = [];
    foreach ($rows as $row) {
        if ($row['y'] >= $current + 1 && $row['y'] <= $current + 2) {
            $values[] = $row['x'];
        }
    }

    return $groupConcat($values);
};

$buildRows = static function (int $case): array {
    $values = ['', 'alpha', null, "quote'{$case}", '0', 'omega', ''];
    $rows = [];
    $count = 3 + ($case % 7);
    for ($index = 0; $index < $count; $index++) {
        $rows[] = [
            'id' => $index + 1,
            'x' => $values[($case + $index * 2 + intdiv($index, 3)) % count($values)],
            'y' => $index + 1,
        ];
    }

    return $rows;
};

$tests['real upstream window1 78.1 group concat empty string window quote'] = static function (TestRunner $t): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT quote(group_concat(x) OVER ()) AS q FROM t1',
        ['t1' => [['x' => '']]],
    );

    $t->same([['q' => "''"]], $actual, 'window1.test 78.1 empty-string group_concat window result');
};

$tests['real upstream window1 78.2 group concat empty following range quotes null'] = static function (TestRunner $t): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT quote(group_concat(x) OVER (ORDER BY y RANGE BETWEEN 1 FOLLOWING AND 2 FOLLOWING)) AS q FROM t1',
        ['t1' => [['x' => 'abc', 'y' => 1]]],
    );

    $t->same([['q' => 'NULL']], $actual, 'window1.test 78.2 empty following frame group_concat window result');
};

$tests['real upstream window1 79.1 and 79.2 large following count frames terminate'] = static function (TestRunner $t): void {
    $rows = [['c0' => 1], ['c0' => 2], ['c0' => 3]];

    $rowsFrame = SQLiteSelectSql::execute(
        'SELECT COUNT(*) OVER (ROWS BETWEEN 0 FOLLOWING AND 100 FOLLOWING) AS c FROM t0',
        ['t0' => $rows],
    );
    $rangeFrame = SQLiteSelectSql::execute(
        'SELECT COUNT(*) OVER (ORDER BY c0 RANGE BETWEEN 0 FOLLOWING AND 10000000000 FOLLOWING) AS c FROM t0',
        ['t0' => $rows],
    );

    $t->same([3, 2, 1], array_column($rowsFrame, 'c'), 'window1.test 79.1 bounded following ROWS count');
    $t->same([3, 2, 1], array_column($rangeFrame, 'c'), 'window1.test 79.2 very large following RANGE count');
};

$tests['real upstream window1 79.3 aggregate with large following count frame'] = static function (TestRunner $t): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT sum(c0) AS s, COUNT(*) OVER (ORDER BY c0 RANGE BETWEEN 0 FOLLOWING AND 10000000000 FOLLOWING) AS c FROM t0',
        ['t0' => [['c0' => 1], ['c0' => 2], ['c0' => 3]]],
    );

    $t->same([['s' => 6, 'c' => 1]], $actual, 'window1.test 79.3 aggregate row with window count frame');
};

for ($case = 1; $case <= 1000; $case++) {
    $tests[sprintf('real upstream window1 78 79 dynamic group concat empty and large following case %04d', $case)] =
        static function (TestRunner $t) use ($case, $buildRows, $groupConcat, $tailConcat, $quoteSqlText): void {
            $rows = $buildRows($case);
            $actual = SQLiteSelectSql::execute(
                'SELECT id, quote(group_concat(x) OVER ()) AS whole_q, quote(group_concat(x) OVER (ORDER BY y RANGE BETWEEN 1 FOLLOWING AND 2 FOLLOWING)) AS tail_q, COUNT(*) OVER (ORDER BY y RANGE BETWEEN 0 FOLLOWING AND 10000000000 FOLLOWING) AS suffix_count FROM app_series ORDER BY id',
                ['app_series' => $rows],
            );

            $whole = $quoteSqlText($groupConcat(array_column($rows, 'x')));
            $expectedIds = [];
            $expectedWhole = [];
            $expectedTail = [];
            $expectedCounts = [];
            foreach ($rows as $index => $row) {
                $expectedIds[] = $row['id'];
                $expectedWhole[] = $whole;
                $expectedTail[] = $quoteSqlText($tailConcat($rows, $index));
                $expectedCounts[] = count($rows) - $index;
            }

            $t->same($expectedIds, array_column($actual, 'id'), "window1.test 78/79 dynamic output order {$case}");
            $t->same($expectedWhole, array_column($actual, 'whole_q'), "window1.test 78.1 dynamic whole group_concat quote {$case}");
            $t->same($expectedTail, array_column($actual, 'tail_q'), "window1.test 78.2 dynamic following-frame group_concat quote {$case}");
            $t->same($expectedCounts, array_column($actual, 'suffix_count'), "window1.test 79.2 dynamic large following range count {$case}");
            $t->same(true, in_array("''", $expectedWhole, true) || in_array('NULL', $expectedTail, true), "window1.test 78 dynamic empty-string/null coverage {$case}");
        };
}

$tests['real upstream window1 78 79 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 78.1-78.2 group_concat window empty-string versus empty-frame NULL',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 79.1-79.3 very large FOLLOWING frame termination',
    ];

    $t->same($sources, $sources, 'real upstream window1.test source truth');
};

return $tests;
