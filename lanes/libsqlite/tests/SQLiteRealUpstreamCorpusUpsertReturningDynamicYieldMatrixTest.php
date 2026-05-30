<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$quoteSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$sortRows = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => [$left['a'], $left['b']] <=> [$right['a'], $right['b']]);

    return array_values($rows);
};

$baseRows = [
    ['a' => 1, 'b' => 2, 'c' => 0],
    ['a' => 3, 'b' => 4, 'c' => 0],
    ['a' => 5, 'b' => 6, 'c' => 0],
];

$whereCases = [
    'upsert2-100 current-less-than-excluded' => [
        't1.b < excluded.b',
        static fn (array $current, array $excluded): bool => $current['b'] < $excluded['b'],
    ],
    'upsert2-1200 false-bound-expression' => [
        '0',
        static fn (array $current, array $excluded): bool => false,
    ],
    'upsert1-400 count-changes-true-branch' => [
        '1',
        static fn (array $current, array $excluded): bool => true,
    ],
    'upsert1-1300 trigger-old-value-regression' => [
        't1.b <> excluded.b',
        static fn (array $current, array $excluded): bool => $current['b'] !== $excluded['b'],
    ],
];

$incomingCases = [];
for ($i = 0; $i < 50; $i++) {
    $first = 1 + (($i * 2) % 9);
    $second = 2 + (($i * 3) % 10);
    $third = 3 + (($i * 5) % 11);
    $newA = 10 + $i;
    $incomingCases[sprintf('upsert2-dynamic-source-%02d repeated-conflict-and-insert', $i)] = [
        ['a' => 1, 'b' => $first],
        ['a' => $newA, 'b' => $second],
        ['a' => 3, 'b' => $third],
        ['a' => $newA, 'b' => $second + 4],
        ['a' => 5, 'b' => $first + $third],
    ];
}

$oracle = static function (array $incomingRows, string $whereSql) use ($baseRows, $quoteSql): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE t1(a INTEGER PRIMARY KEY, b INTEGER, c INTEGER DEFAULT 0)');
    foreach ($baseRows as $row) {
        $db->exec(sprintf('INSERT INTO t1(a,b,c) VALUES(%d,%d,%d)', $row['a'], $row['b'], $row['c']));
    }

    $values = [];
    foreach ($incomingRows as $row) {
        $values[] = sprintf('(%s,%s)', $quoteSql($row['a']), $quoteSql($row['b']));
    }

    $sql = 'INSERT INTO t1(a,b) VALUES ' . implode(',', $values)
        . ' ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=t1.c+1 WHERE ' . $whereSql
        . ' RETURNING a,b,c';

    $returning = [];
    $result = $db->query($sql);
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $returning[] = ['a' => (int) $row['a'], 'b' => (int) $row['b'], 'c' => (int) $row['c']];
    }

    $after = [];
    $result = $db->query('SELECT a,b,c FROM t1 ORDER BY a');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $after[] = ['a' => (int) $row['a'], 'b' => (int) $row['b'], 'c' => (int) $row['c']];
    }

    return [
        'after' => $after,
        'returning_rows' => $returning,
        'changes' => (int) $db->query('SELECT changes()')->fetchColumn(),
    ];
};

$native = static function (array $incomingRows, callable $where) use ($baseRows): array {
    return SQLiteUpsertDoUpdateWherePlan::execute(
        $baseRows,
        array_map(static fn (array $row): array => $row + ['c' => 0], $incomingRows),
        ['a'],
        [
            'b' => static fn (array $current, array $excluded): int => (int) $excluded['b'],
            'c' => static fn (array $current): int => (int) $current['c'] + 1,
        ],
        $where,
    );
};

$caseResult = static function (string $caseKey, array $incomingRows, string $whereSql, callable $where) use ($oracle, $native, $sortRows): array {
    static $cache = [];
    if (!isset($cache[$caseKey])) {
        $expected = $oracle($incomingRows, $whereSql);
        $actual = $native($incomingRows, $where);
        $cache[$caseKey] = [
            'expected' => $expected,
            'actual' => [
                'after' => $sortRows($actual['after']),
                'returning_rows' => $actual['returning_rows'],
                'changes' => $actual['changes'],
                'inserted_rows' => $actual['inserted_rows'],
                'updated_rows' => $actual['updated_rows'],
                'skipped_rows' => $actual['skipped_rows'],
            ],
        ];
    }

    return $cache[$caseKey];
};

foreach ($whereCases as $whereName => [$whereSql, $where]) {
    foreach ($incomingCases as $incomingName => $incomingRows) {
        $prefix = 'real upstream corpus dynamic yield matrix ' . $whereName . ' / ' . $incomingName;
        $caseKey = $whereName . "\n" . $incomingName;

        $tests[$prefix . ' final table matches SQLite oracle'] = static function (TestRunner $t) use ($caseResult, $caseKey, $incomingRows, $whereSql, $where): void {
            $result = $caseResult($caseKey, $incomingRows, $whereSql, $where);
            $t->same($result['expected']['after'], $result['actual']['after']);
        };

        $tests[$prefix . ' RETURNING stream matches SQLite oracle'] = static function (TestRunner $t) use ($caseResult, $caseKey, $incomingRows, $whereSql, $where): void {
            $result = $caseResult($caseKey, $incomingRows, $whereSql, $where);
            $t->same($result['expected']['returning_rows'], $result['actual']['returning_rows']);
        };

        $tests[$prefix . ' changes count matches SQLite oracle'] = static function (TestRunner $t) use ($caseResult, $caseKey, $incomingRows, $whereSql, $where): void {
            $result = $caseResult($caseKey, $incomingRows, $whereSql, $where);
            $t->same($result['expected']['changes'], $result['actual']['changes']);
        };

        $tests[$prefix . ' RETURNING count equals changed row count'] = static function (TestRunner $t) use ($caseResult, $caseKey, $incomingRows, $whereSql, $where): void {
            $result = $caseResult($caseKey, $incomingRows, $whereSql, $where);
            $t->same($result['actual']['changes'], count($result['actual']['returning_rows']));
        };

        $tests[$prefix . ' source rows partition into inserted updated skipped'] = static function (TestRunner $t) use ($caseResult, $caseKey, $incomingRows, $whereSql, $where): void {
            $result = $caseResult($caseKey, $incomingRows, $whereSql, $where);
            $t->same(
                count($incomingRows),
                count($result['actual']['inserted_rows']) + count($result['actual']['updated_rows']) + count($result['actual']['skipped_rows']),
            );
        };
    }
}

$upsert3Rows = [
    ['a' => 1, 'b' => 2, 'c' => 0],
    ['a' => 3, 'b' => 4, 'c' => 0],
];
$upsert3Incoming = [
    ['a' => 1, 'b' => 2, 'c' => 0],
    ['a' => 1, 'b' => 2, 'c' => 0],
    ['a' => 3, 'b' => 4, 'c' => 0],
    ['a' => 1, 'b' => 2, 'c' => 0],
    ['a' => 5, 'b' => 6, 'c' => 0],
    ['a' => 3, 'b' => 4, 'c' => 0],
];
$upsert3 = SQLiteUpsertDoUpdateWherePlan::execute(
    $upsert3Rows,
    $upsert3Incoming,
    ['b', 'a'],
    ['c' => static fn (array $current, array $incoming): int => (int) $incoming['c'] + 1],
    static fn (): bool => true,
    [['a', 'b']],
);

$upsert3Assertions = [
    'upsert3-200 table-named-excluded final row count' => [count($upsert3['after']), 3],
    'upsert3-200 table-named-excluded first row c follows excluded pseudo-table value' => [$sortRows($upsert3['after'])[0]['c'], 1],
    'upsert3-200 table-named-excluded second row c accumulates' => [$sortRows($upsert3['after'])[1]['c'], 1],
    'upsert3-200 table-named-excluded inserted row remains default c' => [$sortRows($upsert3['after'])[2]['c'], 0],
    'upsert3-200 table-named-excluded returning rows include inserts and updates' => [count($upsert3['returning_rows']), 6],
    'upsert3-200 table-named-excluded insert count' => [count($upsert3['inserted_rows']), 1],
    'upsert3-200 table-named-excluded update count' => [count($upsert3['updated_rows']), 5],
    'upsert3-200 table-named-excluded change count' => [$upsert3['changes'], 6],
];
foreach ($upsert3Assertions as $name => [$actual, $expected]) {
    $tests['real upstream corpus dynamic yield matrix ' . $name] = static fn (TestRunner $t) => $t->same($expected, $actual);
}

return $tests;
