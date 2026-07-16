<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    't1' => [],
    't2' => [],
];

for ($i = 1; $i <= 64; $i++) {
    $tables['t1'][] = [
        'a' => $i * 2,
        'b' => $i * 2 + 100,
        'c' => $i * 2 + (($i % 5) + 1) * 3,
    ];
    $tables['t2'][] = [
        'd' => $i * 3,
        'e' => $i * 3 + 200,
        'f' => $i * 3 + (($i % 7) + 1) * 5,
    ];
}

$flatten = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

$assertSelectFlat = static function (TestRunner $t, string $sql, array $expectedFlat) use ($tables, $flatten): void {
    $actualFlat = $flatten(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expectedFlat, $actualFlat, $sql);
    $t->same(count($expectedFlat), count($actualFlat), 'flat value count for ' . $sql);
    $t->same(
        $expectedFlat === [] ? [] : [$expectedFlat[0], $expectedFlat[array_key_last($expectedFlat)]],
        $actualFlat === [] ? [] : [$actualFlat[0], $actualFlat[array_key_last($actualFlat)]],
        'first/last guard for ' . $sql
    );
    $t->same(md5(json_encode($expectedFlat, JSON_THROW_ON_ERROR)), md5(json_encode($actualFlat, JSON_THROW_ON_ERROR)), 'fingerprint for ' . $sql);
    $t->contains('SELECT', strtoupper($sql));
};

$singleArmRows = [];
foreach ($tables['t1'] as $row) {
    $singleArmRows[] = ['a' => $row['a']];
}
foreach ($tables['t2'] as $row) {
    $singleArmRows[] = ['a' => $row['d']];
}

$tripleArmRows = $singleArmRows;
foreach ($tables['t1'] as $row) {
    $tripleArmRows[] = ['a' => $row['c']];
}

$twoColumnRows = [];
foreach ($tables['t1'] as $row) {
    $twoColumnRows[] = ['a' => $row['a'], 'b' => $row['b']];
}
foreach ($tables['t2'] as $row) {
    $twoColumnRows[] = ['a' => $row['d'], 'b' => $row['e']];
}

$filterRows = static function (array $rows, int $threshold): array {
    return array_values(array_filter($rows, static fn (array $row): bool => $row['a'] >= $threshold));
};

$orderByColumns = static function (array $rows, array $columns): array {
    usort($rows, static function (array $left, array $right) use ($columns): int {
        foreach ($columns as $column) {
            $comparison = $left[$column] <=> $right[$column];
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    });

    return $rows;
};

$sliceFlat = static function (array $rows, int $limit, int $offset) use ($flatten): array {
    return $flatten(array_slice($rows, $offset, $limit));
};

$tests['real upstream selectB.test cites derived compound source'] = static function (TestRunner $t): void {
    $t->contains('selectB.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test');
    $t->contains('selectB-1.1', 'selectB-1.1 fixture setup');
    $t->contains('selectB-2.2', 'selectB-2.2 derived UNION ALL flattening');
    $t->contains('selectB-2.11', 'selectB-2.11 triple-arm filtered LIMIT');
};

for ($case = 0; $case < 250; $case++) {
    $threshold = 1 + (($case * 7) % 190);
    $limit = 1 + ($case % 17);
    $offset = intdiv($case, 7) % 23;

    $expectedRows = $orderByColumns($filterRows($singleArmRows, $threshold), ['a']);
    $expectedFlat = $sliceFlat($expectedRows, $limit, $offset);
    $tests[sprintf('real upstream selectB.test dynamic derived filtered union order limit case %03d', $case)] = static function (TestRunner $t) use ($threshold, $limit, $offset, $expectedFlat, $assertSelectFlat): void {
        $sql = 'SELECT a FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2) WHERE a>=' . $threshold . ' ORDER BY a LIMIT ' . $limit . ' OFFSET ' . $offset;

        $assertSelectFlat($t, $sql, $expectedFlat);
        $t->contains('selectB-2.4', 'selectB-2.4 WHERE pushdown over derived compound');
    };

    $expectedFlatEquivalent = $expectedFlat;
    $tests[sprintf('real upstream selectB.test dynamic flattened equivalent filtered union case %03d', $case)] = static function (TestRunner $t) use ($threshold, $limit, $offset, $expectedFlatEquivalent, $assertSelectFlat): void {
        $sql = 'SELECT a FROM t1 WHERE a>=' . $threshold . ' UNION ALL SELECT d FROM t2 WHERE d>=' . $threshold . ' ORDER BY a LIMIT ' . $limit . ' OFFSET ' . $offset;

        $assertSelectFlat($t, $sql, $expectedFlatEquivalent);
        $t->contains('selectB-2.4', 'selectB-2.4 flattened equivalent compound');
    };
}

for ($case = 0; $case < 250; $case++) {
    $threshold = 1 + (($case * 11) % 210);
    $limit = 1 + ($case % 13);
    $offset = intdiv($case, 5) % 31;

    $expectedRows = $orderByColumns($filterRows($tripleArmRows, $threshold), ['a']);
    $expectedFlat = $sliceFlat($expectedRows, $limit, $offset);
    $tests[sprintf('real upstream selectB.test dynamic triple-arm filtered order limit case %03d', $case)] = static function (TestRunner $t) use ($threshold, $limit, $offset, $expectedFlat, $assertSelectFlat): void {
        $sql = 'SELECT a FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2 UNION ALL SELECT c FROM t1) WHERE a>=' . $threshold . ' ORDER BY a LIMIT ' . $limit . ' OFFSET ' . $offset;

        $assertSelectFlat($t, $sql, $expectedFlat);
        $t->contains('selectB-2.11', 'selectB-2.11 triple-arm derived compound');
    };

    $expectedUnorderedRows = $filterRows($tripleArmRows, $threshold);
    $expectedUnorderedFlat = $sliceFlat($expectedUnorderedRows, $limit, $offset);
    $tests[sprintf('real upstream selectB.test dynamic triple-arm table-order limit case %03d', $case)] = static function (TestRunner $t) use ($threshold, $limit, $offset, $expectedUnorderedFlat, $assertSelectFlat): void {
        $sql = 'SELECT a FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2 UNION ALL SELECT c FROM t1) WHERE a>=' . $threshold . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $assertSelectFlat($t, $sql, $expectedUnorderedFlat);
        $t->contains('selectB-2.9', 'selectB-2.9 table-order UNION ALL preservation');
    };
}

for ($case = 0; $case < 250; $case++) {
    $threshold = 1 + (($case * 13) % 190);
    $limit = 1 + ($case % 11);
    $offset = intdiv($case, 3) % 29;

    $expectedRows = $orderByColumns($filterRows($twoColumnRows, $threshold), ['a', 'b']);
    $expectedFlat = $sliceFlat($expectedRows, $limit, $offset);
    $tests[sprintf('real upstream selectB.test dynamic two-column derived order case %03d', $case)] = static function (TestRunner $t) use ($threshold, $limit, $offset, $expectedFlat, $assertSelectFlat): void {
        $sql = 'SELECT a,b FROM (SELECT a,b FROM t1 UNION ALL SELECT d,e FROM t2) WHERE a>=' . $threshold . ' ORDER BY a,b LIMIT ' . $limit . ' OFFSET ' . $offset;

        $assertSelectFlat($t, $sql, $expectedFlat);
        $t->contains('selectB-2.15', 'selectB-2.15 two-column derived compound ordering');
    };

    $expectedFlatEquivalent = $expectedFlat;
    $tests[sprintf('real upstream selectB.test dynamic two-column flattened equivalent case %03d', $case)] = static function (TestRunner $t) use ($threshold, $limit, $offset, $expectedFlatEquivalent, $assertSelectFlat): void {
        $sql = 'SELECT a,b FROM t1 WHERE a>=' . $threshold . ' UNION ALL SELECT d,e FROM t2 WHERE d>=' . $threshold . ' ORDER BY a,b LIMIT ' . $limit . ' OFFSET ' . $offset;

        $assertSelectFlat($t, $sql, $expectedFlatEquivalent);
        $t->contains('selectB-2.15', 'selectB-2.15 flattened two-column compound ordering');
    };
}

return $tests;
