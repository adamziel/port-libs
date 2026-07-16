<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenSelectCoreBatchRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelectCoreBatch = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $upstream
) use ($flattenSelectCoreBatchRows): void {
    $actual = $flattenSelectCoreBatchRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'flat edge values for ' . $sql,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat fingerprint for ' . $sql,
    );
    $t->contains('.test', $upstream);
};

$select2Tbl1 = [];
for ($i = 0; $i <= 30; $i++) {
    $select2Tbl1[] = ['f1' => $i % 9, 'f2' => $i % 10];
}

$select2Tbl2 = [];
for ($i = 1; $i <= 2400; $i++) {
    $select2Tbl2[] = ['f1' => $i, 'f2' => $i * 2, 'f3' => $i * 3, 'bucket' => $i % 17];
}

$select5T1 = [];
for ($i = 1; $i < 96; $i++) {
    $j = 0;
    while ((1 << $j) < $i) {
        $j++;
    }

    $select5T1[] = ['x' => 96 - $i, 'y' => 14 - $j, 'z' => $i % 7];
}

$select5T8a = [
    ['a' => 'one', 'b' => 1],
    ['a' => 'one', 'b' => 2],
    ['a' => 'two', 'b' => 3],
    ['a' => 'one', 'b' => null],
    ['a' => 'three', 'b' => 4],
    ['a' => 'three', 'b' => 5],
];
$select5T8b = [
    ['rowid' => 1, 'x' => 111],
    ['rowid' => 2, 'x' => 222],
    ['rowid' => 3, 'x' => 333],
    ['rowid' => 4, 'x' => 444],
    ['rowid' => 5, 'x' => 555],
];

$selectCoreBatchTables = [
    'tbl1' => $select2Tbl1,
    'tbl2' => $select2Tbl2,
    'aa' => [['a' => 1], ['a' => 3], ['a' => 5], ['a' => 7]],
    'bb' => [['b' => 0], ['b' => 2], ['b' => 4], ['b' => 6], ['b' => 8]],
    't1' => $select5T1,
    't8a' => $select5T8a,
    't8b' => $select5T8b,
];

$tests = [];

$tests['real upstream corpus select core dynamic select2/select5 batch cites upstream source files'] = static function (TestRunner $t): void {
    $t->contains('/test/select2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test');
    $t->contains('/test/select5.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test');
    $t->contains('select2-1.1', 'select2-1.1 nested SELECT during outer DISTINCT scan');
    $t->contains('select2-4.6', 'select2-4.6 CASE truthiness in WHERE');
    $t->contains('select5-5.11', 'select5-5.11 GROUP BY expression rendering');
    $t->contains('select5-8.1', 'select5-8.1 join aggregate grouped by text key');
};

for ($case = 0; $case < 250; $case++) {
    $lower = $case % 9;
    $upper = min(8, $lower + ($case % 4));
    $expectedRows = [];
    $seen = [];
    foreach ($select2Tbl1 as $row) {
        if ($row['f1'] < $lower || $row['f1'] > $upper || isset($seen[$row['f1']])) {
            continue;
        }

        $seen[$row['f1']] = true;
        $expectedRows[] = ['f1' => $row['f1']];
    }
    usort($expectedRows, static fn (array $left, array $right): int => $left['f1'] <=> $right['f1']);
    $expected = [];
    foreach ($expectedRows as $row) {
        $f1 = $row['f1'];
        $expected[] = $f1;
        $innerRows = array_values(array_filter($select2Tbl1, static fn (array $inner): bool => $inner['f1'] === $f1));
        usort($innerRows, static fn (array $left, array $right): int => $left['f2'] <=> $right['f2']);
        foreach ($innerRows as $inner) {
            $expected[] = $inner['f2'];
        }
    }

    $tests["real upstream corpus select2.test select2-1.1 dynamic nested distinct scan {$case}"] = static function (TestRunner $t) use ($assertSelectCoreBatch, $selectCoreBatchTables, $lower, $upper, $expected): void {
        $outer = SQLiteSelectSql::execute(
            "SELECT DISTINCT f1 FROM tbl1 WHERE f1>={$lower} AND f1<={$upper} ORDER BY f1",
            $selectCoreBatchTables,
        );
        $actual = [];
        foreach ($outer as $row) {
            $f1 = (int) $row['f1'];
            $actual[] = $f1;
            foreach (SQLiteSelectSql::execute("SELECT f2 FROM tbl1 WHERE f1={$f1} ORDER BY f2", $selectCoreBatchTables) as $inner) {
                $actual[] = $inner['f2'];
            }
        }

        $t->same($expected, $actual, 'nested scan output mirrors select2-1.1');
        $t->same(count($expected), count($actual), 'nested scan flat value count');
        $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), 'nested scan fingerprint');
        $assertSelectCoreBatch(
            $t,
            "SELECT DISTINCT f1 FROM tbl1 WHERE f1>={$lower} AND f1<={$upper} ORDER BY f1",
            $selectCoreBatchTables,
            array_column($outer, 'f1'),
            'select2.test select2-1.1/select2-1.2',
        );
    };
}

for ($case = 0; $case < 250; $case++) {
    $target = 2 + (($case * 19) % 4600);
    $window = 12 + ($case % 29);
    $expectedRows = array_values(array_filter(
        $select2Tbl2,
        static fn (array $row): bool => $row['f2'] >= $target && $row['f2'] <= $target + $window
    ));
    usort($expectedRows, static fn (array $left, array $right): int => ($left['f2'] <=> $right['f2']) ?: ($left['f1'] <=> $right['f1']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['f1'], $row['f2']);
    }

    $tests["real upstream corpus select2.test select2-3.1 dynamic commuted equality range {$case}"] = static function (TestRunner $t) use ($assertSelectCoreBatch, $selectCoreBatchTables, $target, $window, $expected): void {
        $assertSelectCoreBatch(
            $t,
            "SELECT f1, f2 FROM tbl2 WHERE {$target}<=f2 AND f2<={$target} + {$window} ORDER BY f2, f1",
            $selectCoreBatchTables,
            $expected,
            'select2.test select2-3.1/select2-3.2 commuted indexed predicate shape',
        );
    };
}

for ($case = 0; $case < 250; $case++) {
    $offset = $case % 5;
    $caseGate = 1 + ($case % 8);
    $expectedRows = [];
    foreach ($selectCoreBatchTables['aa'] as $left) {
        foreach ($selectCoreBatchTables['bb'] as $right) {
            $expr = ($left['a'] === $right['b'] - 1 + $offset) ? 1 : (($left['a'] + $right['b']) >= $caseGate ? 1 : 0);
            if ($expr) {
                $expectedRows[] = ['a' => $left['a'], 'b' => $right['b']];
            }
        }
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($left['a'] <=> $right['a']) ?: ($left['b'] <=> $right['b']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['a'], $row['b']);
    }

    $tests["real upstream corpus select2.test select2-4.6 dynamic case truth join {$case}"] = static function (TestRunner $t) use ($assertSelectCoreBatch, $selectCoreBatchTables, $offset, $caseGate, $expected): void {
        $assertSelectCoreBatch(
            $t,
            "SELECT a, b FROM aa, bb WHERE CASE WHEN a=b-1+{$offset} THEN 1 ELSE a+b>={$caseGate} END ORDER BY a, b",
            $selectCoreBatchTables,
            $expected,
            'select2.test select2-4.6/select2-4.7 CASE truthiness joins',
        );
    };
}

for ($case = 0; $case < 250; $case++) {
    $minY = 7 + ($case % 8);
    $maxZ = $case % 7;
    $expectedRows = [];
    for ($y = 7; $y <= 14; $y++) {
        $members = array_values(array_filter(
            $select5T1,
            static fn (array $row): bool => $row['y'] === $y && $row['z'] <= $maxZ
        ));
        if ($y < $minY || $members === []) {
            continue;
        }

        $expectedRows[] = [
            'y' => $y,
            'total' => count($members),
            'low_x' => min(array_column($members, 'x')),
            'expr_key' => $y * count($members),
        ];
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($left['expr_key'] <=> $right['expr_key']) ?: ($left['y'] <=> $right['y']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['y'], $row['total'], $row['low_x'], $row['expr_key']);
    }

    $tests["real upstream corpus select5.test select5-5.11 dynamic group expression order {$case}"] = static function (TestRunner $t) use ($assertSelectCoreBatch, $selectCoreBatchTables, $minY, $maxZ, $expected): void {
        $assertSelectCoreBatch(
            $t,
            "SELECT y, count(*), min(x), y*count(*) FROM t1 WHERE z<={$maxZ} GROUP BY y HAVING y>={$minY} ORDER BY y*count(*), y",
            $selectCoreBatchTables,
            $expected,
            'select5.test select5-5.11 GROUP BY expression rendering and aggregate ORDER BY',
        );
    };
}

for ($case = 0; $case < 250; $case++) {
    $xFloor = 100 + (($case * 37) % 500);
    $minCount = 1 + ($case % 4);
    $expectedRows = [];
    foreach (['one', 'three', 'two'] as $group) {
        $members = [];
        foreach ($select5T8a as $left) {
            foreach ($select5T8b as $right) {
                if ($left['a'] === $group && $left['b'] !== null && $left['b'] === $right['rowid'] && $right['x'] >= $xFloor) {
                    $members[] = $left['b'];
                }
            }
        }

        if (count($members) >= $minCount) {
            $expectedRows[] = ['a' => $group, 'count_b' => count($members), 'max_b' => max($members)];
        }
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($left['count_b'] <=> $right['count_b']) ?: strcmp((string) $left['a'], (string) $right['a']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['a'], $row['count_b'], $row['max_b']);
    }

    $tests["real upstream corpus select5.test select5-8 dynamic join aggregate count {$case}"] = static function (TestRunner $t) use ($assertSelectCoreBatch, $selectCoreBatchTables, $xFloor, $minCount, $expected): void {
        $assertSelectCoreBatch(
            $t,
            "SELECT a, count(b), max(b) FROM t8a, t8b WHERE b=t8b.rowid AND x>={$xFloor} GROUP BY a HAVING count(b)>={$minCount} ORDER BY count(b), a",
            $selectCoreBatchTables,
            $expected,
            'select5.test select5-8.1/select5-8.6 join aggregate grouping',
        );
    };
}

return $tests;
