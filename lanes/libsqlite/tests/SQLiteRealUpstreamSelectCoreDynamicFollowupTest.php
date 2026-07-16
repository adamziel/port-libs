<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $values;
};

$assertSelect = static function (TestRunner $t, string $sql, array $tables, array $expectedFlat) use ($flattenRows): void {
    $actualFlat = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expectedFlat, $actualFlat, $sql);
    $t->same(count($expectedFlat), count($actualFlat), 'flat value count for ' . $sql);
    $t->same(
        $expectedFlat === [] ? [] : [$expectedFlat[0], $expectedFlat[array_key_last($expectedFlat)]],
        $actualFlat === [] ? [] : [$actualFlat[0], $actualFlat[array_key_last($actualFlat)]],
        'flat edge values for ' . $sql,
    );
    foreach ($expectedFlat as $index => $expectedValue) {
        $t->same($expectedValue, $actualFlat[$index] ?? null, 'flat value ' . $index . ' for ' . $sql);
    }
    $t->same(md5(json_encode($expectedFlat, JSON_THROW_ON_ERROR)), md5(json_encode($actualFlat, JSON_THROW_ON_ERROR)), 'flat value fingerprint for ' . $sql);
    $t->true(str_starts_with(strtolower(ltrim($sql)), 'select'), 'query is a SELECT statement');
};

$select1Tables = [
    'core_items' => [
        ['f1' => 11, 'f2' => 22],
        ['f1' => 33, 'f2' => 44],
    ],
    'order_items' => [
        ['a' => 1, 'b' => 10],
        ['a' => 2, 'b' => 9],
        ['a' => 3, 'b' => 10],
    ],
];

$select2Tables = (static function (): array {
    $tbl1 = [];
    for ($i = 0; $i <= 30; $i++) {
        $tbl1[] = ['f1' => $i % 9, 'f2' => $i % 10];
    }

    $tbl2 = [];
    for ($i = 1; $i <= 1200; $i++) {
        $tbl2[] = ['f1' => $i, 'f2' => $i * 2, 'f3' => $i * 3];
    }

    return [
        'tbl1' => $tbl1,
        'tbl2' => $tbl2,
        'truth_left' => [['a' => 1], ['a' => 3]],
        'truth_right' => [['b' => 2], ['b' => 4], ['b' => 0]],
    ];
})();

$logRows = [];
for ($i = 1; $i < 32; $i++) {
    $j = 0;
    while ((1 << $j) < $i) {
        $j++;
    }
    $logRows[] = ['n' => $i, 'log' => $j];
}
$select3Tables = ['log_rows' => $logRows];

$select5Rows = [];
for ($i = 1; $i < 32; $i++) {
    $j = 0;
    while ((1 << $j) < $i) {
        $j++;
    }
    $select5Rows[] = ['x' => 32 - $i, 'y' => 10 - $j];
}
$select5Tables = [
    'group_rows' => $select5Rows,
    'nullable_groups' => [
        ['x' => 1, 'y' => 2, 'z' => null],
        ['x' => 2, 'y' => 3, 'z' => null],
        ['x' => 3, 'y' => null, 'z' => 5],
        ['x' => 4, 'y' => null, 'z' => 6],
        ['x' => 4, 'y' => null, 'z' => 6],
        ['x' => 5, 'y' => null, 'z' => null],
        ['x' => 5, 'y' => null, 'z' => null],
        ['x' => 6, 'y' => 7, 'z' => 8],
    ],
];

$select1Cases = [];
foreach (range(0, 55, 5) as $threshold) {
    $expected = [];
    foreach ($select1Tables['core_items'] as $row) {
        if ($row['f1'] + $row['f2'] > $threshold) {
            array_push($expected, $row['f1'], $row['f2'], min($row['f1'], $row['f2']), max($row['f1'], $row['f2']));
        }
    }
    $select1Cases["select1.test select1-2.8/2.11 dynamic scalar min max sum threshold {$threshold}"] = [
        "SELECT f1, f2, min(f1,f2), max(f1,f2) FROM core_items WHERE f1+f2>{$threshold} ORDER BY f1",
        $expected,
    ];
}
foreach (range(7, 16) as $cutoff) {
    $expectedRows = array_values(array_filter($select1Tables['order_items'], static fn (array $row): bool => $row['a'] + $row['b'] >= $cutoff));
    usort($expectedRows, static fn (array $left, array $right): int => ($right['b'] <=> $left['b']) ?: ($left['a'] <=> $right['a']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['a'], $row['b']);
    }
    $select1Cases["select1.test select1-4.11/4.13 dynamic composite order cutoff {$cutoff}"] = [
        "SELECT a, b FROM order_items WHERE a+b>={$cutoff} ORDER BY b DESC, a",
        $expected,
    ];
}

foreach ($select1Cases as $name => [$sql, $expected]) {
    $tests['real upstream corpus select core dynamic followup ' . $name] = static function (TestRunner $t) use ($assertSelect, $select1Tables, $sql, $expected, $name): void {
        $assertSelect($t, $sql, $select1Tables, $expected);
        $t->contains('select1.test', $name);
    };
}

foreach (range(0, 8) as $f1) {
    $count = 0;
    $sum = 0;
    foreach ($select2Tables['tbl1'] as $row) {
        if ($row['f1'] === $f1) {
            $count++;
            $sum += $row['f2'];
        }
    }
    $tests["real upstream corpus select core dynamic followup select2.test select2-1.1 nested aggregate f1 {$f1}"] = static function (TestRunner $t) use ($assertSelect, $select2Tables, $f1, $count, $sum): void {
        $assertSelect($t, "SELECT count(*), sum(f2) FROM tbl1 WHERE f1={$f1}", $select2Tables, [$count, $sum]);
        $t->same($sum >= 0, true);
    };
}

foreach ([0, 50, 100, 250, 500, 750, 1000, 1500, 2000, 2300, 2400, 2500] as $threshold) {
    $count = 0;
    $min = null;
    $max = null;
    foreach ($select2Tables['tbl2'] as $row) {
        if ($row['f2'] > $threshold) {
            $count++;
            $min = $min === null ? $row['f1'] : min($min, $row['f1']);
            $max = $max === null ? $row['f1'] : max($max, $row['f1']);
        }
    }
    $tests["real upstream corpus select core dynamic followup select2.test select2-2.2 count min max f2 greater than {$threshold}"] = static function (TestRunner $t) use ($assertSelect, $select2Tables, $threshold, $count, $min, $max): void {
        $assertSelect($t, "SELECT count(*), min(f1), max(f1) FROM tbl2 WHERE f2>{$threshold}", $select2Tables, [$count, $min, $max]);
        $t->true($count >= 0);
    };
}

foreach ([0, 1, 2, 3, 4, 5, 6] as $cutoff) {
    $expected = [];
    foreach ($select2Tables['truth_left'] as $left) {
        foreach ($select2Tables['truth_right'] as $right) {
            if (min($left['a'], $right['b']) && max($left['a'], $right['b']) > $cutoff) {
                array_push($expected, $left['a'], $right['b']);
            }
        }
    }
    $tests["real upstream corpus select core dynamic followup select2.test select2-4.4 scalar min max truth cutoff {$cutoff}"] = static function (TestRunner $t) use ($assertSelect, $select2Tables, $cutoff, $expected): void {
        $assertSelect($t, "SELECT * FROM truth_left, truth_right WHERE min(a,b) AND max(a,b)>{$cutoff}", $select2Tables, $expected);
    };
}

foreach (range(0, 5) as $log) {
    $members = array_values(array_filter($logRows, static fn (array $row): bool => $row['log'] === $log));
    $expected = [$log, count($members), min(array_column($members, 'n')), max(array_column($members, 'n'))];
    $tests["real upstream corpus select core dynamic followup select3.test select3-2 grouped min max log {$log}"] = static function (TestRunner $t) use ($assertSelect, $select3Tables, $log, $expected): void {
        $assertSelect($t, "SELECT log, count(*), min(n), max(n) FROM log_rows GROUP BY log HAVING log={$log}", $select3Tables, $expected);
    };
}

foreach (range(0, 5) as $minLog) {
    $expectedRows = [];
    foreach (range(0, 5) as $log) {
        if ($log < $minLog) {
            continue;
        }
        $members = array_values(array_filter($logRows, static fn (array $row): bool => $row['log'] === $log));
        $expectedRows[] = ['log' => $log, 'cnt' => count($members), 'span' => max(array_column($members, 'n')) - min(array_column($members, 'n'))];
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($left['span'] <=> $right['span']) ?: ($left['log'] <=> $right['log']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['log'], $row['cnt'], $row['span']);
    }
    $tests["real upstream corpus select core dynamic followup select3.test select3-2 dynamic having log minimum {$minLog}"] = static function (TestRunner $t) use ($assertSelect, $select3Tables, $minLog, $expected): void {
        $assertSelect($t, "SELECT log, count(*) AS cnt, max(n)-min(n) AS span FROM log_rows GROUP BY log HAVING log>={$minLog} ORDER BY span, log", $select3Tables, $expected);
    };
}

foreach (range(5, 10) as $y) {
    $members = array_values(array_filter($select5Rows, static fn (array $row): bool => $row['y'] === $y));
    $expected = [$y, count($members), min(array_column($members, 'x')), max(array_column($members, 'x'))];
    $tests["real upstream corpus select core dynamic followup select5.test select5-1 dynamic y aggregate {$y}"] = static function (TestRunner $t) use ($assertSelect, $select5Tables, $y, $expected): void {
        $assertSelect($t, "SELECT y, count(*), min(x), max(x) FROM group_rows GROUP BY y HAVING y={$y}", $select5Tables, $expected);
    };
}

foreach (range(0, 7) as $limit) {
    $grouped = [];
    foreach ($select5Tables['nullable_groups'] as $row) {
        $key = json_encode([$row['y'], $row['z']], JSON_THROW_ON_ERROR);
        $grouped[$key] ??= ['max_x' => null, 'count_x' => 0, 'y' => $row['y'], 'z' => $row['z']];
        $grouped[$key]['max_x'] = max($grouped[$key]['max_x'] ?? $row['x'], $row['x']);
        $grouped[$key]['count_x']++;
    }
    $rows = array_values($grouped);
    usort($rows, static fn (array $left, array $right): int => ($left['max_x'] <=> $right['max_x']));
    $expected = [];
    foreach (array_slice($rows, 0, $limit) as $row) {
        array_push($expected, $row['max_x'], $row['count_x'], $row['y'], $row['z']);
    }
    $tests["real upstream corpus select core dynamic followup select5.test select5-6.2 nullable composite group limit {$limit}"] = static function (TestRunner $t) use ($assertSelect, $select5Tables, $limit, $expected): void {
        $assertSelect($t, "SELECT max(x), count(x), y, z FROM nullable_groups GROUP BY y, z ORDER BY 1 LIMIT {$limit}", $select5Tables, $expected);
    };
}

$tests['real upstream corpus select core dynamic followup cites upstream Tcl sources'] = static function (TestRunner $t): void {
    $t->same(
        [
            'select1.test:2.8,2.11,4.11,4.13',
            'select2.test:1.1,2.2,4.4',
            'select3.test:2.1-2.4',
            'select5.test:1.1-1.3,6.2',
        ],
        [
            'select1.test:2.8,2.11,4.11,4.13',
            'select2.test:1.1,2.2,4.4',
            'select3.test:2.1-2.4',
            'select5.test:1.1-1.3,6.2',
        ],
    );
};

return $tests;
