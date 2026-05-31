<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$section52Sql = static function (string $table): string {
    return "SELECT id, a, b, count() OVER win1 AS count_win1, count() OVER () AS count_all, sum(c) OVER win2 AS sum_c, first_value(c) OVER win2 AS first_c, count(a) OVER (ORDER BY b) AS count_a FROM {$table} WINDOW win1 AS (ORDER BY a), win2 AS (PARTITION BY 6 COLLATE binary ORDER BY a RANGE BETWEEN 5 PRECEDING AND 0 PRECEDING) ORDER BY a, b, id";
};

$sqlCompare = static function (mixed $left, mixed $right): int {
    if ($left === null || $right === null) {
        return $left === $right ? 0 : ($left === null ? -1 : 1);
    }

    if ((is_int($left) || is_float($left) || is_bool($left)) && (is_int($right) || is_float($right) || is_bool($right))) {
        return ((float) $left) <=> ((float) $right);
    }

    return strcmp((string) $left, (string) $right);
};

$sumNonNull = static function (array $values): int|float|null {
    $seen = false;
    $sum = 0;
    foreach ($values as $value) {
        if ($value === null) {
            continue;
        }
        $seen = true;
        $sum += is_bool($value) ? (int) $value : $value;
    }

    return $seen ? $sum : null;
};

$expectedSection52Rows = static function (array $rows) use ($sqlCompare, $sumNonNull): array {
    $rowsByA = $rows;
    usort($rowsByA, static function (array $left, array $right) use ($sqlCompare): int {
        $comparison = $sqlCompare($left['a'], $right['a']);
        return $comparison === 0 ? ($left['id'] <=> $right['id']) : $comparison;
    });

    $aGroups = [];
    foreach ($rowsByA as $row) {
        $aGroups[(string) $row['a']][] = $row;
    }

    $countThroughA = [];
    $sumByA = [];
    $firstByA = [];
    $seen = 0;
    foreach ($aGroups as $a => $groupRows) {
        $seen += count($groupRows);
        $countThroughA[$a] = $seen;
        $sumByA[$a] = $sumNonNull(array_column($groupRows, 'c'));
        $firstByA[$a] = $groupRows[0]['c'];
    }

    $bGroups = [];
    foreach ($rows as $row) {
        $bGroups[(string) $row['b']][] = $row;
    }
    uksort($bGroups, static fn (string $left, string $right): int => strcmp($left, $right));

    $countThroughB = [];
    $seenNonNullA = 0;
    foreach ($bGroups as $b => $groupRows) {
        foreach ($groupRows as $row) {
            if ($row['a'] !== null) {
                $seenNonNullA++;
            }
        }
        $countThroughB[$b] = $seenNonNullA;
    }

    $expected = [];
    foreach ($rows as $row) {
        $a = (string) $row['a'];
        $b = (string) $row['b'];
        $expected[] = [
            'id' => $row['id'],
            'a' => $row['a'],
            'b' => $row['b'],
            'count_win1' => $countThroughA[$a],
            'count_all' => count($rows),
            'sum_c' => $sumByA[$a],
            'first_c' => $firstByA[$a],
            'count_a' => $countThroughB[$b],
        ];
    }

    usort($expected, static function (array $left, array $right) use ($sqlCompare): int {
        foreach (['a', 'b', 'id'] as $column) {
            $comparison = $sqlCompare($left[$column], $right[$column]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    });

    return $expected;
};

$upstreamRows = [
    ['id' => 1, 'a' => 'AA', 'b' => 'bb', 'c' => 356],
    ['id' => 2, 'a' => 'CC', 'b' => 'aa', 'c' => 158],
    ['id' => 3, 'a' => 'BB', 'b' => 'aa', 'c' => 399],
    ['id' => 4, 'a' => 'FF', 'b' => 'bb', 'c' => 938],
];

$tests['real upstream window1 52.2 named count empty-arg window exact rows'] = static function (TestRunner $t) use ($section52Sql, $expectedSection52Rows, $upstreamRows): void {
    $actual = SQLiteSelectSql::execute($section52Sql('t1'), ['t1' => $upstreamRows]);
    $expected = $expectedSection52Rows($upstreamRows);

    $t->same(array_column($expected, 'count_win1'), array_column($actual, 'count_win1'), 'window1.test 52.2 count() OVER win1');
    $t->same(array_column($expected, 'sum_c'), array_column($actual, 'sum_c'), 'window1.test 52.2 sum(c) OVER win2');
    $t->same(array_column($expected, 'first_c'), array_column($actual, 'first_c'), 'window1.test 52.2 first_value(c) OVER win2');
    $t->same(array_column($expected, 'count_a'), array_column($actual, 'count_a'), 'window1.test 52.2 count(a) OVER ORDER BY b');
};

$tests['real upstream window1 52.3 whole partition count empty-arg window exact rows'] = static function (TestRunner $t) use ($section52Sql, $upstreamRows): void {
    $actual = SQLiteSelectSql::execute($section52Sql('t1'), ['t1' => $upstreamRows]);

    $t->same([4, 4, 4, 4], array_column($actual, 'count_all'), 'window1.test 52.3 count() OVER ()');
};

$tests['real upstream window1 52.4 collated constant partition matches uncollated constant'] = static function (TestRunner $t) use ($section52Sql, $expectedSection52Rows, $upstreamRows): void {
    $actual = SQLiteSelectSql::execute($section52Sql('t1'), ['t1' => $upstreamRows]);
    $expected = $expectedSection52Rows($upstreamRows);

    $t->same($expected, $actual, 'window1.test 52.4 PARTITION BY 6 COLLATE binary');
};

$buildRows = static function (int $case): array {
    $aValues = ['AA', 'BB', 'CC', 'DD', 'EE', 'FF'];
    $bValues = ['aa', 'bb', 'cc', 'dd'];
    $rowCount = 5 + ($case % 10);
    $rows = [];

    for ($index = 0; $index < $rowCount; $index++) {
        $rows[] = [
            'id' => $index + 1,
            'a' => $aValues[($case * 3 + $index * 2 + intdiv($index, 3)) % count($aValues)],
            'b' => $bValues[($case + $index * 3 + intdiv($index, 2)) % count($bValues)],
            'c' => (($case * 17 + $index * 11) % 97) - 31,
        ];
    }

    return $rows;
};

for ($case = 1; $case <= 1000; $case++) {
    $tests[sprintf('real upstream window1 52 dynamic named count corpus case %04d', $case)] = static function (TestRunner $t) use ($case, $section52Sql, $expectedSection52Rows, $buildRows): void {
        $rows = $buildRows($case);
        $actual = SQLiteSelectSql::execute($section52Sql('app_metrics'), ['app_metrics' => $rows]);
        $expected = $expectedSection52Rows($rows);

        $t->same(array_column($expected, 'id'), array_column($actual, 'id'), "window1.test 52 dynamic output order {$case}");
        $t->same(array_column($expected, 'count_win1'), array_column($actual, 'count_win1'), "window1.test 52.2 dynamic count() OVER win1 {$case}");
        $t->same(array_column($expected, 'count_all'), array_column($actual, 'count_all'), "window1.test 52.3 dynamic count() OVER () {$case}");
        $t->same(array_column($expected, 'sum_c'), array_column($actual, 'sum_c'), "window1.test 52.2 dynamic sum(c) peer RANGE {$case}");
        $t->same(array_column($expected, 'first_c'), array_column($actual, 'first_c'), "window1.test 52.2 dynamic first_value(c) peer RANGE {$case}");
        $t->same(array_column($expected, 'count_a'), array_column($actual, 'count_a'), "window1.test 52.4 dynamic count(a) ordered peers {$case}");
    };
}

return $tests;
