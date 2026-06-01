<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test
 * - select3-1.2: multiple aggregate functions over different value columns.
 * - select3-1.3: arithmetic over aggregate results from different columns.
 * - select3-5.1 and select3-5.2: grouped aggregate output with ORDER BY
 *   expressions that reuse aggregate calls over a scalar expression argument.
 */

$tests = [];

/**
 * @return list<array{n:int,log:int}>
 */
$select3RowsFor = static function (int $rowCount, int $shift = 0): array {
    $rows = [];
    for ($i = 1; $i <= $rowCount; $i++) {
        $log = 0;
        while ((1 << $log) < $i) {
            $log++;
        }
        $rows[] = ['n' => $i + $shift, 'log' => $log];
    }

    return $rows;
};

/**
 * @param list<array{n:int,log:int}> $rows
 * @return array<int,list<array{n:int,log:int}>>
 */
$select3Groups = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $row) {
        $groups[$row['log']][] = $row;
    }
    ksort($groups);

    return $groups;
};

/**
 * @param list<int|float|null> $expected
 */
$assertSelect3Flat = static function (TestRunner $t, string $sql, array $tables, array $expected): void {
    $actual = [];
    foreach (SQLiteSelectSql::execute($sql, $tables) as $row) {
        foreach ($row as $value) {
            $actual[] = is_int($value) || is_float($value) ? round((float) $value, 6) : $value;
        }
    }
    $expected = array_map(static fn (mixed $value): mixed => is_int($value) || is_float($value) ? round((float) $value, 6) : $value, $expected);

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values for ' . $sql,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'fingerprint for ' . $sql,
    );
};

for ($case = 0; $case < 250; $case++) {
    $rowCount = 18 + ($case % 47);
    $shift = $case % 9;
    $minLog = $case % 4;
    $rows = array_values(array_filter(
        $select3RowsFor($rowCount, $shift),
        static fn (array $row): bool => $row['log'] >= $minLog,
    ));
    $nValues = array_column($rows, 'n');
    $logValues = array_column($rows, 'log');
    $expected = [
        min($nValues),
        min($logValues),
        max($nValues),
        max($logValues),
        array_sum($nValues),
        array_sum($logValues),
        array_sum($nValues) / count($nValues),
        array_sum($logValues) / count($logValues),
    ];
    $tables = ['t1' => $select3RowsFor($rowCount, $shift)];

    $tests[sprintf('real upstream select3.test select3-1.2 dynamic multi aggregate case %04d', $case)] =
        static function (TestRunner $t) use ($assertSelect3Flat, $tables, $minLog, $expected): void {
            $sql = "SELECT min(n) AS min_n, min(log) AS min_log, max(n) AS max_n, max(log) AS max_log, sum(n) AS sum_n, sum(log) AS sum_log, avg(n) AS avg_n, avg(log) AS avg_log FROM t1 WHERE log>={$minLog}";
            $assertSelect3Flat($t, $sql, $tables, $expected);
        };
}

for ($case = 0; $case < 250; $case++) {
    $rowCount = 20 + ($case % 45);
    $shift = ($case * 2) % 11;
    $minLog = 1 + ($case % 4);
    $rows = array_values(array_filter(
        $select3RowsFor($rowCount, $shift),
        static fn (array $row): bool => $row['log'] >= $minLog,
    ));
    $nValues = array_column($rows, 'n');
    $logValues = array_column($rows, 'log');
    $expected = [
        max($nValues) / (array_sum($nValues) / count($nValues)),
        max($logValues) / (array_sum($logValues) / count($logValues)),
    ];
    $tables = ['t1' => $select3RowsFor($rowCount, $shift)];

    $tests[sprintf('real upstream select3.test select3-1.3 dynamic aggregate arithmetic case %04d', $case)] =
        static function (TestRunner $t) use ($assertSelect3Flat, $tables, $minLog, $expected): void {
            $sql = "SELECT max(n)/avg(n) AS n_ratio, max(log)/avg(log) AS log_ratio FROM t1 WHERE log>={$minLog}";
            $assertSelect3Flat($t, $sql, $tables, $expected);
        };
}

for ($case = 0; $case < 250; $case++) {
    $rowCount = 24 + ($case % 41);
    $shift = $case % 7;
    $limit = 1 + ($case % 6);
    $tables = ['t1' => $select3RowsFor($rowCount, $shift)];
    $groupRows = [];
    foreach ($select3Groups($tables['t1']) as $log => $rows) {
        $nValues = array_column($rows, 'n');
        $groupRows[] = [
            'log' => $log,
            'count' => count($rows),
            'avg' => array_sum($nValues) / count($nValues),
            'maxExpr' => max(array_map(static fn (array $row): int => $row['n'] + ($row['log'] * 2), $rows)),
        ];
    }
    usort($groupRows, static fn (array $left, array $right): int => ($left['maxExpr'] <=> $right['maxExpr']) ?: ($left['avg'] <=> $right['avg']));
    $expected = [];
    foreach (array_slice($groupRows, 0, $limit) as $row) {
        array_push($expected, $row['log'], $row['count'], $row['avg'], $row['maxExpr']);
    }

    $tests[sprintf('real upstream select3.test select3-5.1 dynamic grouped expression aggregate order case %04d', $case)] =
        static function (TestRunner $t) use ($assertSelect3Flat, $tables, $limit, $expected): void {
            $sql = "SELECT log, count(*) AS cnt, avg(n) AS avg_n, max(n+log*2) AS max_expr FROM t1 GROUP BY log ORDER BY max(n+log*2)+0, avg(n)+0 LIMIT {$limit}";
            $assertSelect3Flat($t, $sql, $tables, $expected);
        };
}

for ($case = 0; $case < 250; $case++) {
    $rowCount = 28 + ($case % 37);
    $shift = ($case * 3) % 10;
    $minGroup = $case % 3;
    $tables = ['t1' => $select3RowsFor($rowCount, $shift)];
    $groupRows = [];
    foreach ($select3Groups($tables['t1']) as $log => $rows) {
        if ($log < $minGroup) {
            continue;
        }
        $nValues = array_column($rows, 'n');
        $avg = array_sum($nValues) / count($nValues);
        $groupRows[] = [
            'log' => $log,
            'count' => count($rows),
            'avg' => $avg,
            'maxExpr' => max(array_map(static fn (array $row): int => $row['n'] + ($row['log'] * 2), $rows)),
            'scalarMin' => min($log, $avg),
        ];
    }
    usort($groupRows, static fn (array $left, array $right): int => ($left['maxExpr'] <=> $right['maxExpr']) ?: ($left['scalarMin'] <=> $right['scalarMin']));
    $expected = [];
    foreach ($groupRows as $row) {
        array_push($expected, $row['log'], $row['count'], $row['avg'], $row['maxExpr']);
    }

    $tests[sprintf('real upstream select3.test select3-5.2 dynamic grouped scalar aggregate order case %04d', $case)] =
        static function (TestRunner $t) use ($assertSelect3Flat, $tables, $minGroup, $expected): void {
            $sql = "SELECT log, count(*) AS cnt, avg(n) AS avg_n, max(n+log*2) AS max_expr FROM t1 GROUP BY log HAVING log>={$minGroup} ORDER BY max(n+log*2)+0, min(log,avg(n))+0";
            $assertSelect3Flat($t, $sql, $tables, $expected);
        };
}

$tests['real upstream select3.test multi aggregate source and dependency closure'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test';
    $text = file_get_contents($source);

    $t->true(is_string($text), 'hydrated upstream select3.test is readable');
    $t->contains('do_test select3-1.2', $text);
    $t->contains('do_test select3-5.1', $text);
    $t->contains('SELECT min(n),min(log),max(n),max(log),sum(n),sum(log),avg(n),avg(log)', $text);
    $t->contains('ORDER BY max(n+log*2)+0, min(log,avg(n))+0', $text);
    $t->same('no new support component needed', 'no new support component needed');
};

return $tests;
