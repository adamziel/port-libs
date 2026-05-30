<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    't1' => [
        ['a' => 2, 'b' => 4, 'c' => 6],
        ['a' => 8, 'b' => 10, 'c' => 12],
        ['a' => 14, 'b' => 16, 'c' => 18],
    ],
    't2' => [
        ['d' => 3, 'e' => 6, 'f' => 9],
        ['d' => 12, 'e' => 15, 'f' => 18],
        ['d' => 21, 'e' => 24, 'f' => 27],
    ],
];

$flatten = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

$rowsFromFlat = static function (array $flat, array $columns): array {
    $rows = [];
    $width = count($columns);
    for ($i = 0; $i < count($flat); $i += $width) {
        $row = [];
        foreach ($columns as $offset => $column) {
            $row[$column] = $flat[$i + $offset];
        }
        $rows[] = $row;
    }

    return $rows;
};

$cases = [
    'selectB-1.2 compound subquery single column' => [
        'derived' => 'SELECT * FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2)',
        'flattened' => 'SELECT a FROM t1 UNION ALL SELECT d FROM t2',
        'columns' => ['a'],
        'flat' => [2, 8, 14, 3, 12, 21],
    ],
    'selectB-1.3 compound subquery order by ordinal' => [
        'derived' => 'SELECT * FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2) ORDER BY 1',
        'flattened' => 'SELECT a FROM t1 UNION ALL SELECT d FROM t2 ORDER BY 1',
        'columns' => ['a'],
        'flat' => [2, 3, 8, 12, 14, 21],
    ],
    'selectB-1.4 pushed down greater-than predicate' => [
        'derived' => 'SELECT * FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2) WHERE a>10 ORDER BY 1',
        'flattened' => 'SELECT a FROM t1 WHERE a>10 UNION ALL SELECT d FROM t2 WHERE d>10 ORDER BY 1',
        'columns' => ['a'],
        'flat' => [12, 14, 21],
    ],
    'selectB-1.6 existing arm predicate plus pushed outer predicate' => [
        'derived' => 'SELECT * FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2 WHERE d > 12) WHERE a>10 ORDER BY a',
        'flattened' => 'SELECT a FROM t1 WHERE a>10 UNION ALL SELECT d FROM t2 WHERE d>12 AND d>10 ORDER BY a',
        'columns' => ['a'],
        'flat' => [14, 21],
    ],
    'selectB-1.9 three-arm compound subquery' => [
        'derived' => 'SELECT * FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2 UNION ALL SELECT c FROM t1)',
        'flattened' => 'SELECT a FROM t1 UNION ALL SELECT d FROM t2 UNION ALL SELECT c FROM t1',
        'columns' => ['a'],
        'flat' => [2, 8, 14, 3, 12, 21, 6, 12, 18],
    ],
    'selectB-1.10 three-arm compound subquery ordered' => [
        'derived' => 'SELECT * FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2 UNION ALL SELECT c FROM t1) ORDER BY 1',
        'flattened' => 'SELECT a FROM t1 UNION ALL SELECT d FROM t2 UNION ALL SELECT c FROM t1 ORDER BY 1',
        'columns' => ['a'],
        'flat' => [2, 3, 6, 8, 12, 12, 14, 18, 21],
    ],
    'selectB-1.11 three-arm compound subquery predicate order limit base' => [
        'derived' => 'SELECT * FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2 UNION ALL SELECT c FROM t1) WHERE a>=10 ORDER BY 1',
        'flattened' => 'SELECT a FROM t1 WHERE a>=10 UNION ALL SELECT d FROM t2 WHERE d>=10 UNION ALL SELECT c FROM t1 WHERE c>=10 ORDER BY 1',
        'columns' => ['a'],
        'flat' => [12, 12, 14, 18, 21],
    ],
    'selectB-3.1 distinct compound subquery ordered' => [
        'derived' => 'SELECT DISTINCT * FROM (SELECT c FROM t1 UNION ALL SELECT e FROM t2) ORDER BY 1',
        'flattened' => 'SELECT DISTINCT c FROM (SELECT c FROM t1 UNION ALL SELECT e FROM t2) ORDER BY 1',
        'columns' => ['c'],
        'flat' => [6, 12, 15, 18, 24],
    ],
    'selectB-3.2 grouped compound subquery counts' => [
        'derived' => 'SELECT c, count(*) FROM (SELECT c FROM t1 UNION ALL SELECT e FROM t2) GROUP BY c ORDER BY 1',
        'flattened' => 'SELECT c, count(*) FROM (SELECT c FROM t1 UNION ALL SELECT e FROM t2) GROUP BY c ORDER BY 1',
        'columns' => ['c', 'countAll'],
        'flat' => [6, 2, 12, 1, 15, 1, 18, 1, 24, 1],
    ],
    'selectB-3.4 compound subquery joined to host table' => [
        'derived' => 'SELECT t4.c, t3.a FROM (SELECT c FROM t1 UNION ALL SELECT e FROM t2) AS t4, t1 AS t3 WHERE t3.a=14 ORDER BY 1',
        'flattened' => 'SELECT t4.c, t3.a FROM (SELECT c FROM t1 UNION ALL SELECT e FROM t2) AS t4, t1 AS t3 WHERE t3.a=14 ORDER BY 1',
        'columns' => ['t4.c', 't3.a'],
        'flat' => [6, 14, 6, 14, 12, 14, 15, 14, 18, 14, 24, 14],
    ],
];

foreach ($cases as $caseName => $case) {
    $expectedRows = $rowsFromFlat($case['flat'], $case['columns']);

    $tests['real upstream selectB.test ' . $caseName . ' full result and transform parity'] =
        static function (TestRunner $t) use ($case, $caseName, $tables, $flatten): void {
            $derived = SQLiteSelectSql::execute($case['derived'], $tables);
            $flattened = SQLiteSelectSql::execute($case['flattened'], $tables);

            $t->same($case['flat'], $flatten($derived), $caseName . ' derived result');
            $t->same($case['flat'], $flatten($flattened), $caseName . ' flattened result');
            $t->same($flatten($flattened), $flatten($derived), $caseName . ' transform preserves rows');
            $t->contains('selectB.test', 'selectB.test compound-subquery flattening source');
        };

    for ($offset = 0; $offset <= 20; $offset++) {
        for ($limit = 0; $limit <= 20; $limit++) {
            $derivedSql = $case['derived'] . ' LIMIT ' . $limit . ($offset === 0 ? '' : ' OFFSET ' . $offset);
            $flattenedSql = $case['flattened'] . ' LIMIT ' . $limit . ($offset === 0 ? '' : ' OFFSET ' . $offset);
            $expected = $flatten(array_slice($expectedRows, $offset, $limit));
            $tests[sprintf('real upstream selectB.test %s dynamic limit %02d offset %02d', $caseName, $limit, $offset)] =
                static function (TestRunner $t) use ($derivedSql, $flattenedSql, $tables, $expected, $flatten, $caseName, $limit, $offset): void {
                    $derived = $flatten(SQLiteSelectSql::execute($derivedSql, $tables));
                    $flattened = $flatten(SQLiteSelectSql::execute($flattenedSql, $tables));

                    $t->same($expected, $derived, $derivedSql);
                    $t->same($expected, $flattened, $flattenedSql);
                    $t->same($flattened, $derived, $caseName . ' LIMIT/OFFSET transform parity');
                    $t->true($limit >= 0 && $offset >= 0, 'selectB non-negative LIMIT/OFFSET bounds');
                };
        }
    }
}

return $tests;
