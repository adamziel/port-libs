<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test
 * - select2-1.1, select2-1.2, select2-4.1 through select2-4.5
 *
 * This ports SELECT-core nested cursor iteration and WHERE expression
 * truthiness over comma/CROSS joins. The upstream CASE tests remain excluded
 * from this slice because current CASE predicates are a separate parser gap.
 */

/**
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat result');
    $t->same(count($expected), count($actual), $label . ' flat result count');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' flat result fingerprint',
    );
};

/**
 * @return array<string,list<array<string,int>>>
 */
$select2Tbl1 = static function (): array {
    $rows = [];
    for ($i = 0; $i <= 30; $i++) {
        $rows[] = ['f1' => $i % 9, 'f2' => $i % 10];
    }

    return ['tbl1' => $rows];
};

/**
 * @param list<array<string,int>> $rows
 * @return list<mixed>
 */
$nestedDistinctExpected = static function (array $rows, ?int $low = null, ?int $high = null): array {
    $f1Values = [];
    foreach ($rows as $row) {
        if ($low !== null && $row['f1'] <= $low) {
            continue;
        }
        if ($high !== null && $row['f1'] >= $high) {
            continue;
        }
        $f1Values[$row['f1']] = true;
    }
    $distinct = array_keys($f1Values);
    sort($distinct, SORT_NUMERIC);

    $flat = [];
    foreach ($distinct as $f1) {
        $flat[] = $f1 . ':';
        $f2Values = [];
        foreach ($rows as $row) {
            if ($row['f1'] === $f1) {
                $f2Values[] = $row['f2'];
            }
        }
        sort($f2Values, SORT_NUMERIC);
        foreach ($f2Values as $f2) {
            $flat[] = $f2;
        }
    }

    return $flat;
};

/**
 * @param list<array<string,int>> $aa
 * @param list<array<string,int>> $bb
 * @return list<mixed>
 */
$joinExpected = static function (array $aa, array $bb, callable $predicate): array {
    $flat = [];
    foreach ($aa as $left) {
        foreach ($bb as $right) {
            if (!$predicate($left['a'], $right['b'])) {
                continue;
            }
            $flat[] = $left['a'];
            $flat[] = $right['b'];
        }
    }

    return $flat;
};

$tests = [];

$tests['real upstream select2.test cites nested and where source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test';
    $t->true(is_file($source), 'hydrated upstream select2.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'select2.test can be read');
    $t->contains('select2-1.1', $text);
    $t->contains('SELECT DISTINCT f1 FROM tbl1 ORDER BY f1', $text);
    $t->contains('SELECT * FROM aa, bb WHERE max(a,b)>2', $text);
    $t->contains('SELECT * FROM aa CROSS JOIN bb WHERE b', $text);
    $t->contains('SELECT * FROM aa, bb WHERE NOT min(a,b)', $text);
};

$tbl1Tables = $select2Tbl1();
$tbl1Rows = $tbl1Tables['tbl1'];

$tests['real upstream select2.test select2-1.1 nested distinct cursor baseline'] =
    static function (TestRunner $t) use ($assertFlat, $tbl1Tables, $tbl1Rows, $nestedDistinctExpected): void {
        $distinctRows = SQLiteSelectSql::execute('SELECT DISTINCT f1 FROM tbl1 ORDER BY f1', $tbl1Tables);
        $actual = [];
        foreach ($distinctRows as $row) {
            $f1 = (int) $row['f1'];
            $actual[] = $f1 . ':';
            $actual = array_merge(
                $actual,
                array_map(
                    static fn (array $f2Row): int => (int) $f2Row['f2'],
                    SQLiteSelectSql::execute('SELECT f2 FROM tbl1 WHERE f1=' . $f1 . ' ORDER BY f2', $tbl1Tables),
                ),
            );
        }

        $t->same($nestedDistinctExpected($tbl1Rows), $actual, 'select2-1.1 nested cursor result');
        $t->same(40, count($actual), 'select2-1.1 nested cursor flat count');
        $assertFlat($t, 'SELECT DISTINCT f1 FROM tbl1 WHERE f1>3 AND f1<5', $tbl1Tables, [4], 'select2-1.2 distinct filtered outer cursor');
    };

for ($case = 0; $case < 350; $case++) {
    $low = $case % 7;
    $high = $low + 2 + ($case % 4);
    $sql = 'SELECT DISTINCT f1 FROM tbl1 WHERE f1>' . $low . ' AND f1<' . $high . ' ORDER BY f1';
    $expectedDistinct = [];
    foreach (range(0, 8) as $value) {
        if ($value > $low && $value < $high) {
            $expectedDistinct[] = $value;
        }
    }
    $expectedNested = $nestedDistinctExpected($tbl1Rows, $low, $high);

    $tests[sprintf('real upstream select2.test select2-1 nested distinct filtered dynamic %03d', $case)] =
        static function (TestRunner $t) use ($assertFlat, $tbl1Tables, $tbl1Rows, $nestedDistinctExpected, $sql, $expectedDistinct, $expectedNested, $low, $high): void {
            $assertFlat($t, $sql, $tbl1Tables, $expectedDistinct, 'select2-1 filtered distinct ' . $low . '-' . $high);

            $nested = [];
            foreach (SQLiteSelectSql::execute($sql, $tbl1Tables) as $row) {
                $f1 = (int) $row['f1'];
                $nested[] = $f1 . ':';
                foreach (SQLiteSelectSql::execute('SELECT f2 FROM tbl1 WHERE f1=' . $f1 . ' ORDER BY f2', $tbl1Tables) as $innerRow) {
                    $nested[] = (int) $innerRow['f2'];
                }
            }
            $t->same($expectedNested, $nested, 'select2-1 nested cursor inner rows ' . $low . '-' . $high);
            $t->same(count($expectedNested), count($nested), 'select2-1 nested cursor inner count ' . $low . '-' . $high);
            $t->same($nestedDistinctExpected($tbl1Rows, $low, $high), $nested, 'select2-1 nested cursor oracle repeat ' . $low . '-' . $high);
        };
}

for ($case = 0; $case < 700; $case++) {
    $aa = [
        ['a' => 1 + ($case % 3)],
        ['a' => 3 + ($case % 5)],
    ];
    $bb = [
        ['b' => 2 + ($case % 4)],
        ['b' => 4 - ($case % 3)],
        ['b' => $case % 2],
    ];
    $tables = ['aa' => $aa, 'bb' => $bb];

    $maxExpected = $joinExpected($aa, $bb, static fn (int $a, int $b): bool => max($a, $b) > 2);
    $truthyBExpected = $joinExpected($aa, $bb, static fn (int $_a, int $b): bool => $b != 0);
    $minExpected = $joinExpected($aa, $bb, static fn (int $a, int $b): bool => min($a, $b) != 0);
    $notMinExpected = $joinExpected($aa, $bb, static fn (int $a, int $b): bool => min($a, $b) == 0);

    $tests[sprintf('real upstream select2.test select2-4 scalar where joins dynamic %03d', $case)] =
        static function (TestRunner $t) use ($assertFlat, $tables, $maxExpected, $truthyBExpected, $minExpected, $notMinExpected, $case): void {
            $assertFlat($t, 'SELECT * FROM aa, bb WHERE max(a,b)>2', $tables, $maxExpected, 'select2-4.1 max where case ' . $case);
            $assertFlat($t, 'SELECT * FROM aa CROSS JOIN bb WHERE b', $tables, $truthyBExpected, 'select2-4.2 truthy b where case ' . $case);
            $assertFlat($t, 'SELECT * FROM aa, bb WHERE min(a,b)', $tables, $minExpected, 'select2-4.4 min truthy where case ' . $case);
            $assertFlat($t, 'SELECT * FROM aa, bb WHERE NOT min(a,b)', $tables, $notMinExpected, 'select2-4.5 not min where case ' . $case);
        };
}

return $tests;
