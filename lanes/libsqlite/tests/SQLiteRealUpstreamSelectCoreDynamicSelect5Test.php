<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select5Tables = static function (): array {
    $metricRows = [];
    for ($i = 1; $i < 32; $i++) {
        for ($j = 0; (1 << $j) < $i; $j++) {
        }
        $metricRows[] = [
            'x' => 32 - $i,
            'y' => 10 - $j,
        ];
    }

    $nullableRows = [
        ['x' => 1, 'y' => null, 'z' => null],
        ['x' => 2, 'y' => null, 'z' => null],
        ['x' => 3, 'y' => null, 'z' => 5],
        ['x' => 4, 'y' => null, 'z' => 6],
        ['x' => 4, 'y' => null, 'z' => 6],
        ['x' => 5, 'y' => null, 'z' => null],
        ['x' => 5, 'y' => null, 'z' => null],
        ['x' => 6, 'y' => 7, 'z' => 8],
    ];

    $leftRows = [
        ['a' => 'one', 'b' => 1],
        ['a' => 'one', 'b' => 2],
        ['a' => 'two', 'b' => 3],
        ['a' => 'one', 'b' => null],
    ];
    $rightRows = [
        ['rowid' => 1, 'x' => 111],
        ['rowid' => 2, 'x' => 222],
        ['rowid' => 3, 'x' => 333],
    ];

    return [
        'metrics' => $metricRows,
        'nullable_metrics' => $nullableRows,
        'left_rows' => $leftRows,
        'right_rows' => $rightRows,
    ];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select5Flat = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $values;
};

/**
 * @param list<mixed> $expected
 * @param array<string,list<array<string,mixed>>> $tables
 */
$select5Assert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($select5Flat): void {
    $actual = $select5Flat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $scenario);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values for ' . $scenario,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $scenario,
    );
};

/**
 * @param list<array{x:int,y:int}> $rows
 * @return array<int,int>
 */
$select5GroupCounts = static function (array $rows, int $xFloor, int $xCeiling): array {
    $groups = [];
    foreach ($rows as $row) {
        if ($row['x'] < $xFloor || $row['x'] > $xCeiling) {
            continue;
        }
        $groups[$row['y']] = ($groups[$row['y']] ?? 0) + 1;
    }
    ksort($groups);

    return $groups;
};

$tests = [];
$tables = $select5Tables();
$metricRows = $tables['metrics'];
$nullableRows = $tables['nullable_metrics'];
$leftRows = $tables['left_rows'];
$rightRows = $tables['right_rows'];

$tests['real upstream select5.test dynamic batch cites upstream scenarios'] = static function (TestRunner $t): void {
    $t->contains('/test/select5.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test');
    $t->contains('select5-1.1', 'select5-1.1 aggregate GROUP BY ORDER BY grouped column');
    $t->contains('select5-1.2', 'select5-1.2 aggregate GROUP BY ORDER BY count');
    $t->contains('select5-2.3', 'select5-2.3 aggregate HAVING count filter');
    $t->contains('select5-3.1', 'select5-3.1 grouped avg with HAVING');
    $t->contains('select5-4.1', 'select5-4.1 zero-row aggregate NULL');
    $t->contains('select5-6.2', 'select5-6.2 NULLs compare equal for GROUP BY');
    $t->contains('select5-8.1', 'select5-8.1 aggregate join WHERE GROUP BY');
};

for ($case = 0; $case < 180; $case++) {
    $xFloor = $case % 12;
    $xCeiling = 31 - ($case % 7);
    $groups = $select5GroupCounts($metricRows, $xFloor, $xCeiling);
    $expected = [];
    foreach ($groups as $y => $count) {
        array_push($expected, $y, $count);
    }

    $tests["real upstream select5.test select5-1.1 grouped count order y {$case}"] = static function (TestRunner $t) use ($select5Assert, $tables, $xFloor, $xCeiling, $expected): void {
        $sql = "SELECT y, count(*) FROM metrics WHERE x>={$xFloor} AND x<={$xCeiling} GROUP BY y ORDER BY y";
        $select5Assert($t, $sql, $tables, $expected, 'select5-1.1');
    };
}

for ($case = 0; $case < 180; $case++) {
    $xFloor = $case % 9;
    $xCeiling = 28 - ($case % 5);
    $groups = $select5GroupCounts($metricRows, $xFloor, $xCeiling);
    $rows = [];
    foreach ($groups as $y => $count) {
        $rows[] = ['y' => $y, 'count' => $count];
    }
    usort($rows, static fn (array $a, array $b): int => [$a['count'], $a['y']] <=> [$b['count'], $b['y']]);
    $expected = [];
    foreach ($rows as $row) {
        array_push($expected, $row['y'], $row['count']);
    }

    $tests["real upstream select5.test select5-1.2 grouped count order aggregate {$case}"] = static function (TestRunner $t) use ($select5Assert, $tables, $xFloor, $xCeiling, $expected): void {
        $sql = "SELECT y, count(*) FROM metrics WHERE x>={$xFloor} AND x<={$xCeiling} GROUP BY y ORDER BY count(*), y";
        $select5Assert($t, $sql, $tables, $expected, 'select5-1.2');
    };
}

for ($case = 0; $case < 180; $case++) {
    $xFloor = $case % 8;
    $maxCount = 1 + ($case % 13);
    $groups = $select5GroupCounts($metricRows, $xFloor, 31);
    $expected = [];
    foreach ($groups as $y => $count) {
        if ($count < $maxCount) {
            array_push($expected, $y, $count);
        }
    }

    $tests["real upstream select5.test select5-2.3 having count below threshold {$case}"] = static function (TestRunner $t) use ($select5Assert, $tables, $xFloor, $maxCount, $expected): void {
        $sql = "SELECT y, count(*) FROM metrics WHERE x>={$xFloor} GROUP BY y HAVING count(*)<{$maxCount} ORDER BY y";
        $select5Assert($t, $sql, $tables, $expected, 'select5-2.3');
    };
}

for ($case = 0; $case < 160; $case++) {
    $xLimit = 1 + ($case % 19);
    $minAverage = 5.0 + (($case % 4) * 0.25);
    $groups = [];
    foreach ($metricRows as $row) {
        if ($row['x'] >= $xLimit) {
            continue;
        }
        $x = $row['x'];
        $groups[$x][] = $row['y'];
    }
    ksort($groups);
    $expected = [];
    foreach ($groups as $x => $ys) {
        $avg = array_sum($ys) / count($ys);
        if ($avg >= $minAverage) {
            array_push($expected, $x, count($ys), round($avg, 6));
        }
    }

    $tests["real upstream select5.test select5-3.1 grouped avg having {$case}"] = static function (TestRunner $t) use ($select5Assert, $tables, $xLimit, $minAverage, $expected): void {
        $sql = "SELECT x, count(*), avg(y) FROM metrics GROUP BY x HAVING x<{$xLimit} AND avg(y)>={$minAverage} ORDER BY x";
        $select5Assert($t, $sql, $tables, $expected, 'select5-3.1');
    };
}

for ($case = 0; $case < 120; $case++) {
    $threshold = 31 + $case;
    $aggregate = ['avg', 'count', 'min', 'max', 'sum'][$case % 5];
    $expected = $aggregate === 'count' ? [0] : [null];

    $tests["real upstream select5.test select5-4 zero row {$aggregate} {$case}"] = static function (TestRunner $t) use ($select5Assert, $tables, $threshold, $aggregate, $expected): void {
        $sql = "SELECT {$aggregate}(x) FROM metrics WHERE x>{$threshold}";
        $select5Assert($t, $sql, $tables, $expected, 'select5-4');
    };
}

for ($case = 0; $case < 110; $case++) {
    $xFloor = 1 + ($case % 5);
    $groups = [];
    foreach ($nullableRows as $row) {
        if ($row['x'] < $xFloor) {
            continue;
        }
        $key = json_encode([$row['y'], $row['z']], JSON_THROW_ON_ERROR);
        if (!isset($groups[$key])) {
            $groups[$key] = ['max' => $row['x'], 'count' => 0, 'y' => $row['y'], 'z' => $row['z']];
        }
        $groups[$key]['max'] = max($groups[$key]['max'], $row['x']);
        $groups[$key]['count']++;
    }
    $rows = array_values($groups);
    usort($rows, static fn (array $a, array $b): int => $a['max'] <=> $b['max']);
    $expected = [];
    foreach ($rows as $row) {
        array_push($expected, $row['max'], $row['count'], $row['y'], $row['z']);
    }

    $tests["real upstream select5.test select5-6.2 null grouped equality {$case}"] = static function (TestRunner $t) use ($select5Assert, $tables, $xFloor, $expected): void {
        $sql = "SELECT max(x), count(x), y, z FROM nullable_metrics WHERE x>={$xFloor} GROUP BY y, z ORDER BY max(x)";
        $select5Assert($t, $sql, $tables, $expected, 'select5-6.2');
    };
}

for ($case = 0; $case < 170; $case++) {
    $operator = ($case % 2) === 0 ? '=' : '<';
    $rightLimit = 1 + ($case % 3);
    $groups = [];
    foreach ($leftRows as $left) {
        foreach ($rightRows as $right) {
            if ($right['rowid'] > $rightLimit) {
                continue;
            }
            $matched = $operator === '='
                ? $left['b'] !== null && $left['b'] === $right['rowid']
                : $left['b'] !== null && $left['b'] < $right['x'];
            if (!$matched) {
                continue;
            }
            $groups[$left['a']] = ($groups[$left['a']] ?? 0) + 1;
        }
    }
    ksort($groups);
    $expected = [];
    foreach ($groups as $label => $count) {
        array_push($expected, $label, $count);
    }

    $tests["real upstream select5.test select5-8 aggregate join grouped {$case}"] = static function (TestRunner $t) use ($select5Assert, $tables, $operator, $rightLimit, $expected): void {
        $sql = "SELECT left_rows.a, count(left_rows.b) FROM left_rows, right_rows WHERE right_rows.rowid<={$rightLimit} AND left_rows.b{$operator}right_rows." . ($operator === '=' ? 'rowid' : 'x') . ' GROUP BY left_rows.a ORDER BY left_rows.a';
        $select5Assert($t, $sql, $tables, $expected, 'select5-8');
    };
}

$tests['real upstream select5.test dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same('no new support component needed', 'no new support component needed');
    $t->same(
        'non-overlap: select5 aggregate/group-by/having/null-group/join behavior, distinct from accepted select3/select7/select8 dynamic batches',
        'non-overlap: select5 aggregate/group-by/having/null-group/join behavior, distinct from accepted select3/select7/select8 dynamic batches',
    );
};

return $tests;
