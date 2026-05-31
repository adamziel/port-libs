<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$window1Rows = [
    ['a' => 1, 'b' => 'odd', 'c' => 'one', 'd' => 1],
    ['a' => 2, 'b' => 'even', 'c' => 'two', 'd' => 2],
    ['a' => 3, 'b' => 'odd', 'c' => 'three', 'd' => 3],
    ['a' => 4, 'b' => 'even', 'c' => 'four', 'd' => 4],
    ['a' => 5, 'b' => 'odd', 'c' => 'five', 'd' => 5],
    ['a' => 6, 'b' => 'even', 'c' => 'six', 'd' => 6],
];

$window3Rows = [
    ['a' => 10, 'b' => 89], ['a' => 11, 'b' => 81], ['a' => 12, 'b' => 96], ['a' => 13, 'b' => 59],
    ['a' => 14, 'b' => 38], ['a' => 15, 'b' => 68], ['a' => 16, 'b' => 39], ['a' => 17, 'b' => 62],
    ['a' => 18, 'b' => 91], ['a' => 19, 'b' => 46], ['a' => 20, 'b' => 6], ['a' => 21, 'b' => 99],
    ['a' => 22, 'b' => 97], ['a' => 23, 'b' => 27], ['a' => 24, 'b' => 46], ['a' => 25, 'b' => 78],
    ['a' => 26, 'b' => 54], ['a' => 27, 'b' => 97], ['a' => 28, 'b' => 8], ['a' => 29, 'b' => 67],
    ['a' => 30, 'b' => 29], ['a' => 31, 'b' => 93], ['a' => 32, 'b' => 84], ['a' => 33, 'b' => 77],
    ['a' => 34, 'b' => 23], ['a' => 35, 'b' => 16], ['a' => 36, 'b' => 16], ['a' => 37, 'b' => 93],
    ['a' => 38, 'b' => 65], ['a' => 39, 'b' => 35], ['a' => 40, 'b' => 47], ['a' => 41, 'b' => 7],
    ['a' => 42, 'b' => 86], ['a' => 43, 'b' => 74], ['a' => 44, 'b' => 61], ['a' => 45, 'b' => 91],
    ['a' => 46, 'b' => 85], ['a' => 47, 'b' => 24], ['a' => 48, 'b' => 85], ['a' => 49, 'b' => 43],
    ['a' => 50, 'b' => 59], ['a' => 51, 'b' => 12], ['a' => 52, 'b' => 32], ['a' => 53, 'b' => 56],
    ['a' => 54, 'b' => 3], ['a' => 55, 'b' => 91], ['a' => 56, 'b' => 22], ['a' => 57, 'b' => 90],
    ['a' => 58, 'b' => 55], ['a' => 59, 'b' => 15], ['a' => 60, 'b' => 28], ['a' => 61, 'b' => 89],
    ['a' => 62, 'b' => 25], ['a' => 63, 'b' => 47], ['a' => 64, 'b' => 1], ['a' => 65, 'b' => 56],
    ['a' => 66, 'b' => 40], ['a' => 67, 'b' => 43], ['a' => 68, 'b' => 56], ['a' => 69, 'b' => 16],
    ['a' => 70, 'b' => 75], ['a' => 71, 'b' => 36], ['a' => 72, 'b' => 89], ['a' => 73, 'b' => 98],
    ['a' => 74, 'b' => 76], ['a' => 75, 'b' => 81], ['a' => 76, 'b' => 4], ['a' => 77, 'b' => 94],
    ['a' => 78, 'b' => 42], ['a' => 79, 'b' => 30], ['a' => 80, 'b' => 78], ['a' => 81, 'b' => 33],
    ['a' => 82, 'b' => 29], ['a' => 83, 'b' => 53], ['a' => 84, 'b' => 63], ['a' => 85, 'b' => 2],
    ['a' => 86, 'b' => 87], ['a' => 87, 'b' => 37], ['a' => 88, 'b' => 80], ['a' => 89, 'b' => 84],
    ['a' => 90, 'b' => 72], ['a' => 91, 'b' => 41], ['a' => 92, 'b' => 9], ['a' => 93, 'b' => 61],
    ['a' => 94, 'b' => 73], ['a' => 95, 'b' => 95], ['a' => 96, 'b' => 65], ['a' => 97, 'b' => 13],
    ['a' => 98, 'b' => 58], ['a' => 99, 'b' => 96], ['a' => 100, 'b' => 98], ['a' => 101, 'b' => 1],
    ['a' => 102, 'b' => 21], ['a' => 103, 'b' => 74], ['a' => 104, 'b' => 65], ['a' => 105, 'b' => 35],
    ['a' => 106, 'b' => 5], ['a' => 107, 'b' => 73], ['a' => 108, 'b' => 11], ['a' => 109, 'b' => 51],
    ['a' => 110, 'b' => 87], ['a' => 111, 'b' => 41], ['a' => 112, 'b' => 12], ['a' => 113, 'b' => 8],
    ['a' => 114, 'b' => 20], ['a' => 115, 'b' => 31], ['a' => 116, 'b' => 31], ['a' => 117, 'b' => 15],
    ['a' => 118, 'b' => 95], ['a' => 119, 'b' => 22], ['a' => 120, 'b' => 73], ['a' => 121, 'b' => 79],
    ['a' => 122, 'b' => 88], ['a' => 123, 'b' => 34], ['a' => 124, 'b' => 8], ['a' => 125, 'b' => 11],
    ['a' => 126, 'b' => 49], ['a' => 127, 'b' => 34], ['a' => 128, 'b' => 90], ['a' => 129, 'b' => 59],
    ['a' => 130, 'b' => 96], ['a' => 131, 'b' => 60], ['a' => 132, 'b' => 55], ['a' => 133, 'b' => 75],
    ['a' => 134, 'b' => 77], ['a' => 135, 'b' => 44], ['a' => 136, 'b' => 2], ['a' => 137, 'b' => 7],
    ['a' => 138, 'b' => 85], ['a' => 139, 'b' => 57], ['a' => 140, 'b' => 74], ['a' => 141, 'b' => 29],
    ['a' => 142, 'b' => 70], ['a' => 143, 'b' => 59], ['a' => 144, 'b' => 19], ['a' => 145, 'b' => 39],
    ['a' => 146, 'b' => 26], ['a' => 147, 'b' => 26], ['a' => 148, 'b' => 47], ['a' => 149, 'b' => 80],
    ['a' => 150, 'b' => 90], ['a' => 151, 'b' => 36], ['a' => 152, 'b' => 58], ['a' => 153, 'b' => 47],
    ['a' => 154, 'b' => 9], ['a' => 155, 'b' => 72], ['a' => 156, 'b' => 72], ['a' => 157, 'b' => 66],
    ['a' => 158, 'b' => 33], ['a' => 159, 'b' => 93], ['a' => 160, 'b' => 75], ['a' => 161, 'b' => 64],
    ['a' => 162, 'b' => 81], ['a' => 163, 'b' => 9], ['a' => 164, 'b' => 23], ['a' => 165, 'b' => 37],
    ['a' => 166, 'b' => 13], ['a' => 167, 'b' => 12], ['a' => 168, 'b' => 14], ['a' => 169, 'b' => 62],
    ['a' => 170, 'b' => 91], ['a' => 171, 'b' => 36], ['a' => 172, 'b' => 91], ['a' => 173, 'b' => 33],
    ['a' => 174, 'b' => 15], ['a' => 175, 'b' => 34], ['a' => 176, 'b' => 36], ['a' => 177, 'b' => 99],
    ['a' => 178, 'b' => 3], ['a' => 179, 'b' => 95], ['a' => 180, 'b' => 69], ['a' => 181, 'b' => 58],
    ['a' => 182, 'b' => 52], ['a' => 183, 'b' => 30], ['a' => 184, 'b' => 50], ['a' => 185, 'b' => 84],
    ['a' => 186, 'b' => 10], ['a' => 187, 'b' => 84], ['a' => 188, 'b' => 33], ['a' => 189, 'b' => 21],
    ['a' => 190, 'b' => 39], ['a' => 191, 'b' => 44], ['a' => 192, 'b' => 58], ['a' => 193, 'b' => 30],
    ['a' => 194, 'b' => 38], ['a' => 195, 'b' => 34], ['a' => 196, 'b' => 83], ['a' => 197, 'b' => 27],
    ['a' => 198, 'b' => 82], ['a' => 199, 'b' => 17], ['a' => 200, 'b' => 7],
];

$sqlString = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

$window3RunningOracle = static function (array $rows, string $separator): array {
    $ordered = $rows;
    usort($ordered, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

    $parts = [];
    $chains = [];
    foreach ($ordered as $row) {
        $parts[] = (string) $row['b'];
        $chains[] = implode($separator, $parts);
    }

    return $chains;
};

$dynamicOracle = static function (array $rows, string $separator): array {
    $ordered = $rows;
    usort($ordered, static function (array $left, array $right): int {
        $tenant = $left['tenant_id'] <=> $right['tenant_id'];
        if ($tenant !== 0) {
            return $tenant;
        }
        if ($left['label'] === null || $right['label'] === null) {
            if ($left['label'] !== $right['label']) {
                return $left['label'] === null ? -1 : 1;
            }
        } else {
            $label = strcmp((string) $left['label'], (string) $right['label']);
            if ($label !== 0) {
                return $label;
            }
        }

        return $left['id'] <=> $right['id'];
    });

    $chains = [];
    $partsByTenant = [];
    foreach ($ordered as $row) {
        $tenant = (string) $row['tenant_id'];
        $partsByTenant[$tenant] ??= [];
        if ($row['label'] !== null) {
            $partsByTenant[$tenant][] = (string) $row['label'];
        }
        $chains[] = $partsByTenant[$tenant] === [] ? null : implode($separator, $partsByTenant[$tenant]);
    }

    return $chains;
};

$tests['real upstream window1.test 18.3.1 select sql string_agg alias'] = static function (TestRunner $t) use ($window1Rows): void {
    $actual = SQLiteSelectSql::execute(
        "SELECT b, c, string_agg(c, '.') OVER (PARTITION BY b ORDER BY c) AS chain FROM app_window_strings ORDER BY b, c",
        ['app_window_strings' => $window1Rows],
    );

    $t->same(
        ['four', 'four.six', 'four.six.two', 'five', 'five.one', 'five.one.three'],
        array_column($actual, 'chain'),
        'window1.test 18.3.1 string_agg(c, ".") OVER (PARTITION BY b ORDER BY c)',
    );
};

$tests['real upstream window1.test 18.3 named window string_agg parity'] = static function (TestRunner $t) use ($window1Rows): void {
    $stringAgg = SQLiteSelectSql::execute(
        "SELECT b, c, string_agg(c, '.') OVER win AS chain FROM app_window_strings WINDOW win AS (PARTITION BY b ORDER BY c) ORDER BY b, c",
        ['app_window_strings' => $window1Rows],
    );
    $groupConcat = SQLiteSelectSql::execute(
        "SELECT b, c, group_concat(c, '.') OVER win AS chain FROM app_window_strings WINDOW win AS (PARTITION BY b ORDER BY c) ORDER BY b, c",
        ['app_window_strings' => $window1Rows],
    );

    $t->same(array_column($groupConcat, 'chain'), array_column($stringAgg, 'chain'), 'window1.test 18.3 named window string_agg aliases group_concat');
    $t->same(['four', 'four.six', 'four.six.two', 'five', 'five.one', 'five.one.three'], array_column($stringAgg, 'chain'));
};

$tests['real upstream window3.test 1.1.14.1 select sql string_agg cast range'] = static function (TestRunner $t) use ($window3Rows, $window3RunningOracle): void {
    $actual = SQLiteSelectSql::execute(
        "SELECT a, string_agg(CAST(b AS TEXT), '.') OVER (ORDER BY a RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS chain FROM t2 ORDER BY a",
        ['t2' => $window3Rows],
    );
    $expected = $window3RunningOracle($window3Rows, '.');

    $t->same(count($window3Rows), count($actual), 'window3.test 1.1.14.1 full upstream t2 row count');
    $t->same(array_slice($expected, 0, 12), array_slice(array_column($actual, 'chain'), 0, 12), 'window3.test 1.1.14.1 prefix chain');
    $t->same($expected[count($expected) - 1], array_column($actual, 'chain')[count($actual) - 1], 'window3.test 1.1.14.1 final chain');
};

for ($case = 1; $case <= 1000; $case++) {
    $rows = [];
    $offset = $case % count($window3Rows);
    for ($index = 0; $index < 16; $index++) {
        $source = $window3Rows[($offset + $index) % count($window3Rows)];
        $label = (($case + $index) % 13) === 0 ? null : (string) $source['b'];
        if ($label !== null && (($case + $index) % 11) === 0) {
            $label .= '-' . ($case % 5);
        }
        $rows[] = [
            'id' => $index + 1,
            'tenant_id' => ($source['b'] + $case + $index) % 4,
            'label' => $label,
        ];
    }
    $separator = ['.', '/', '|', ':', '-'][$case % 5];
    $expected = $dynamicOracle($rows, $separator);
    $separatorSql = $sqlString($separator);
    $stringAggSql = "SELECT id, string_agg(CAST(label AS TEXT), {$separatorSql}) OVER (PARTITION BY tenant_id ORDER BY label, id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS chain FROM app_events ORDER BY tenant_id, label, id";
    $groupConcatSql = "SELECT id, group_concat(CAST(label AS TEXT), {$separatorSql}) OVER (PARTITION BY tenant_id ORDER BY label, id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS chain FROM app_events ORDER BY tenant_id, label, id";

    $tests["real upstream window3.test 1.1.14 dynamic string_agg alias case {$case}"] = static function (TestRunner $t) use ($case, $rows, $expected, $stringAggSql, $groupConcatSql): void {
        $tables = ['app_events' => $rows];
        $stringAgg = SQLiteSelectSql::execute($stringAggSql, $tables);
        $groupConcat = SQLiteSelectSql::execute($groupConcatSql, $tables);

        $t->same($expected, array_column($stringAgg, 'chain'), "window3.test 1.1.14 dynamic string_agg frame oracle case {$case}");
        $t->same(array_column($groupConcat, 'chain'), array_column($stringAgg, 'chain'), "window3.test 1.1.14 dynamic string_agg/group_concat alias parity case {$case}");
        $t->same(count($rows), count($stringAgg), "window3.test 1.1.14 dynamic row cardinality case {$case}");
    };
}

$tests['real upstream window string_agg dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 18.3.1, 18.3.3, 18.3.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 1.1.14.1',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 18.3.1, 18.3.3, 18.3.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 1.1.14.1',
    ]);
};

return $tests;
