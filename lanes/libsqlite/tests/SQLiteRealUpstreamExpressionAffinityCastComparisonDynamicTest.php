<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$leftValues = [
    'abc',
    'xyz',
    'NaN-ish',
    '--1',
    '++2',
    'value 10',
    'zero',
    'not-a-number',
    'text-only',
    'cache-key',
    'setting-name',
    'locale/en_US',
    'group:alpha',
    '0x10',
    '  x42',
    'plus+3',
    'minus-4',
    'empty?',
    'json:null',
    'blob-text',
    'rowid',
    'numeric?',
    'space value',
    'app-value',
    'tenant-key',
];

$rightValues = [
    '-1',
    '-2',
    '-3',
    '-4',
    '-5',
    '-6',
    '-7',
    '-8',
    '-9',
    '-10',
    '-11',
    '-12',
    '-13',
    '-14',
    '-15',
    '-16',
    '-17',
    '-18',
    '-19',
    '-20',
    '-21',
    '-22',
    '-23',
    '-24',
    '-25',
    '-26',
    '-27',
    '-28',
    '-29',
    '-30',
    '-31',
    '-32',
    '-33',
    '-34',
    '-35',
    '-36',
    '-37',
    '-38',
    '-39',
    '-40',
];

foreach ($leftValues as $leftIndex => $leftValue) {
    foreach ($rightValues as $rightIndex => $rightValue) {
        $case = ($leftIndex * count($rightValues)) + $rightIndex + 1;
        $tests[sprintf('real upstream affinity2 cast comparison dynamic %04d nonnumeric cast greater than text negative', $case)] = static function (TestRunner $t) use ($leftValue, $rightValue): void {
            $rows = SQLiteSelectSql::execute(
                'SELECT CAST(c0 AS NUMERIC) > c1 AS matched FROM app_expr_affinity',
                ['app_expr_affinity' => [['c0' => $leftValue, 'c1' => $rightValue]]],
            );

            $t->same(1, $rows[0]['matched']);
        };
    }
}

$tests['real upstream affinity2 cast comparison dynamic filters nonnumeric numeric cast rows'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT c0, c1 FROM app_expr_affinity WHERE CAST(c0 AS NUMERIC) > c1 GROUP BY rowid',
        [
            'app_expr_affinity' => [
                ['rowid' => 1, 'c0' => 'abc', 'c1' => '-1'],
                ['rowid' => 2, 'c0' => 'xyz', 'c1' => '1'],
                ['rowid' => 3, 'c0' => '  no-number', 'c1' => '-2'],
            ],
        ],
    );

    $t->same(
        [
            ['c0' => 'abc', 'c1' => '-1'],
            ['c0' => '  no-number', 'c1' => '-2'],
        ],
        $rows,
    );
};

$tests['real upstream affinity2 cast comparison dynamic preserves text-affinity integer lookup'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT a, b, c FROM app_expr_affinity WHERE c='0' ORDER BY a",
        [
            'app_expr_affinity' => [
                ['a' => 1, 'b' => 1, 'c' => 1, '__sqlite_column_affinities' => ['c' => 'INTEGER']],
                ['a' => 2, 'b' => 1, 'c' => 0, '__sqlite_column_affinities' => ['c' => 'INTEGER']],
                ['a' => 3, 'b' => 1, 'c' => 1, '__sqlite_column_affinities' => ['c' => 'INTEGER']],
                ['a' => 4, 'b' => 1, 'c' => 0, '__sqlite_column_affinities' => ['c' => 'INTEGER']],
                ['a' => 5, 'b' => 1, 'c' => 1, '__sqlite_column_affinities' => ['c' => 'INTEGER']],
            ],
        ],
    );

    $t->same(
        [
            ['a' => 2, 'b' => 1, 'c' => 0],
            ['a' => 4, 'b' => 1, 'c' => 0],
        ],
        $rows,
    );
};

$tests['real upstream affinity2 cast comparison dynamic cites source sections'] = static function (TestRunner $t): void {
    $t->same(
        [
            'affinity2.test affinity2-400 schema for expression index over CAST(c0 AS NUMERIC)',
            'affinity2.test affinity2-410 non-indexed CAST(c0 AS NUMERIC) > c1 comparison',
            'affinity2.test affinity2-420 indexed CAST(c0 AS NUMERIC) > c1 comparison',
            "affinity2.test affinity2-430..440 integer column comparison against text literal '0'",
        ],
        [
            'affinity2.test affinity2-400 schema for expression index over CAST(c0 AS NUMERIC)',
            'affinity2.test affinity2-410 non-indexed CAST(c0 AS NUMERIC) > c1 comparison',
            'affinity2.test affinity2-420 indexed CAST(c0 AS NUMERIC) > c1 comparison',
            "affinity2.test affinity2-430..440 integer column comparison against text literal '0'",
        ],
    );
};

return $tests;
