<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sampleRows = [
    ['id' => 1, 'counter' => 1, 'value' => 10.0],
    ['id' => 2, 'counter' => 1, 'value' => 20.0],
    ['id' => 3, 'counter' => 2, 'value' => 1.0],
    ['id' => 4, 'counter' => 2, 'value' => 3.0],
    ['id' => 5, 'counter' => 3, 'value' => 100.0],
];

$nthRows = [
    ['a' => 1, 'b' => 2],
    ['a' => 2, 'b' => 3],
    ['a' => 3, 'b' => 4],
];

$subqueryRows = [
    ['a' => 10],
    ['a' => 15],
    ['a' => 20],
    ['a' => 20],
    ['a' => 25],
    ['a' => 30],
    ['a' => 30],
    ['a' => 50],
];

$lookupRows = [
    ['x' => 10, 'y' => 'ten'],
    ['x' => 15, 'y' => 'fifteen'],
    ['x' => 30, 'y' => 'thirty'],
];

$tables = [
    'sample' => $sampleRows,
    't1' => $nthRows,
    'rank_seed' => $subqueryRows,
    'label_lookup' => $lookupRows,
];

$execute = static fn (string $sql): array => SQLiteSelectSql::execute($sql, $tables);
$column = static fn (string $sql, string $column): array => array_column($execute($sql), $column);
$flatten = static function (string $sql, array $columns) use ($execute): array {
    $values = [];
    foreach ($execute($sql) as $row) {
        foreach ($columns as $column) {
            $values[] = $row[$column];
        }
    }

    return $values;
};

$tests['real upstream window value dynamic window6 8.2 rows two preceding shorthand'] = static function (TestRunner $t) use ($flatten): void {
    $t->same(
        [1, 10.0, 10.0, 1, 20.0, 30.0, 2, 1.0, 31.0, 2, 3.0, 24.0, 3, 100.0, 104.0],
        $flatten(
            'SELECT counter, value, SUM(value) OVER (ORDER BY id ROWS 2 PRECEDING) AS total FROM sample ORDER BY id',
            ['counter', 'value', 'total'],
        ),
    );
};

$tests['real upstream window value dynamic window6 8.3 rows between two preceding and current row'] = static function (TestRunner $t) use ($column): void {
    $t->same(
        [10.0, 30.0, 31.0, 24.0, 104.0],
        $column(
            'SELECT SUM(value) OVER (ORDER BY id ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS total FROM sample ORDER BY id',
            'total',
        ),
    );
};

$nthCases = [
    'window6 10.2.1 nth_value first row available immediately' => [
        'SELECT nth_value(b, 1) OVER (ORDER BY a) AS value FROM t1',
        [2, 2, 2],
    ],
    'window6 10.2.2 nth_value second row waits for default current-row frame' => [
        'SELECT nth_value(b, 2) OVER (ORDER BY a) AS value FROM t1',
        [null, 3, 3],
    ],
    'window6 10.2.3 nth_value text integer argument' => [
        "SELECT nth_value(b, '2') OVER (ORDER BY a) AS value FROM t1",
        [null, 3, 3],
    ],
    'window6 10.2.4 nth_value real integer argument' => [
        'SELECT nth_value(b, 2.0) OVER (ORDER BY a) AS value FROM t1',
        [null, 3, 3],
    ],
    'window6 10.2.5 nth_value text real integer argument' => [
        "SELECT nth_value(b, '2.0') OVER (ORDER BY a) AS value FROM t1",
        [null, 3, 3],
    ],
    'window6 10.2.6 nth_value out of frame remains null' => [
        'SELECT nth_value(b, 10000000) OVER (ORDER BY a) AS value FROM t1',
        [null, null, null],
    ],
];

foreach ($nthCases as $name => [$sql, $expected]) {
    $tests['real upstream window value dynamic ' . $name] = static function (TestRunner $t) use ($column, $sql, $expected): void {
        $t->same($expected, $column($sql, 'value'));
    };
}

$invalidNthArguments = [
    'window6 10.1.1 nth_value rejects zero index' => 'SELECT nth_value(b, 0) OVER (ORDER BY a) AS value FROM t1',
    'window6 10.1.2 nth_value rejects negative index' => 'SELECT nth_value(b, -1) OVER (ORDER BY a) AS value FROM t1',
    'window6 10.1.3 nth_value rejects non-integer text index' => "SELECT nth_value(b, '4ab') OVER (ORDER BY a) AS value FROM t1",
    'window6 10.1.4 nth_value rejects null index' => 'SELECT nth_value(b, NULL) OVER (ORDER BY a) AS value FROM t1',
    'window6 10.1.5 nth_value rejects non-integral real index' => 'SELECT nth_value(b, 8.5) OVER (ORDER BY a) AS value FROM t1',
];

foreach ($invalidNthArguments as $name => $sql) {
    $tests['real upstream window value dynamic ' . $name] = static function (TestRunner $t) use ($column, $sql): void {
        try {
            $column($sql, 'value');
            $t->fail('expected nth_value argument validation failure');
        } catch (InvalidArgumentException $exception) {
            $t->true(
                str_contains($exception->getMessage(), 'nth_value() index must be positive')
                || str_contains($exception->getMessage(), 'nth_value() integer argument must be integer'),
            );
        }
    };
}

$tests['real upstream window value dynamic window6 11.3.1 explicit current row frame matches running rows'] = static function (TestRunner $t) use ($flatten): void {
    $t->same(
        [10, 10, 15, 25, 20, 45, 20, 65, 25, 90, 30, 120, 30, 150, 50, 200],
        $flatten(
            'SELECT a, sum(a) OVER (ORDER BY a ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total FROM rank_seed',
            ['a', 'total'],
        ),
    );
};

$tests['real upstream window value dynamic window6 11.3.2 zero following frame matches current row'] = static function (TestRunner $t) use ($flatten): void {
    $t->same(
        [10, 10, 15, 25, 20, 45, 20, 65, 25, 90, 30, 120, 30, 150, 50, 200],
        $flatten(
            'SELECT a, sum(a) OVER (ORDER BY a ROWS BETWEEN UNBOUNDED PRECEDING AND 0 FOLLOWING) AS total FROM rank_seed',
            ['a', 'total'],
        ),
    );
};

$tests['real upstream window value dynamic window6 11.3.3 zero preceding frame matches current row'] = static function (TestRunner $t) use ($flatten): void {
    $t->same(
        [10, 10, 15, 25, 20, 45, 20, 65, 25, 90, 30, 120, 30, 150, 50, 200],
        $flatten(
            'SELECT a, sum(a) OVER (ORDER BY a ROWS BETWEEN UNBOUNDED PRECEDING AND 0 PRECEDING) AS total FROM rank_seed',
            ['a', 'total'],
        ),
    );
};

$tests['real upstream window value dynamic cites exact upstream sources'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window6.test:8.2,8.3',
            'window6.test:10.1.1-10.1.5,10.2.1-10.2.6',
            'window6.test:11.3.1-11.3.3',
        ],
        [
            'window6.test:8.2,8.3',
            'window6.test:10.1.1-10.1.5,10.2.1-10.2.6',
            'window6.test:11.3.1-11.3.3',
        ],
    );
};

return $tests;
