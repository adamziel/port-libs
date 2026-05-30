<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    't1' => [
        ['a' => 1, 'b' => 'one', 'c' => 'I'],
        ['a' => 3, 'b' => null, 'c' => null],
        ['a' => 5, 'b' => 'five', 'c' => 'V'],
        ['a' => 7, 'b' => 'seven', 'c' => 'VII'],
        ['a' => 9, 'b' => null, 'c' => null],
        ['a' => 2, 'b' => 'two', 'c' => 'II'],
        ['a' => 4, 'b' => 'four', 'c' => 'IV'],
        ['a' => 6, 'b' => null, 'c' => null],
        ['a' => 8, 'b' => 'eight', 'c' => 'VIII'],
        ['a' => 10, 'b' => 'ten', 'c' => 'X'],
    ],
    't2' => [
        ['d' => 1, 'e' => 'two', 'f' => 'IV'],
        ['d' => 2, 'e' => 'four', 'f' => 'VIII'],
        ['d' => 3, 'e' => null, 'f' => null],
        ['d' => 4, 'e' => 'eight', 'f' => 'XVI'],
        ['d' => 5, 'e' => 'ten', 'f' => 'XX'],
        ['d' => 6, 'e' => null, 'f' => null],
        ['d' => 7, 'e' => 'fourteen', 'f' => 'XXVIII'],
        ['d' => 8, 'e' => 'sixteen', 'f' => 'XXXII'],
        ['d' => 9, 'e' => null, 'f' => null],
        ['d' => 10, 'e' => 'twenty', 'f' => 'XL'],
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

$rowsFromFlatPairs = static function (array $flat): array {
    $rows = [];
    for ($i = 0; $i < count($flat); $i += 2) {
        $rows[] = ['a' => $flat[$i], 'b' => $flat[$i + 1]];
    }

    return $rows;
};

$addCompoundLimitCases = static function (
    array &$tests,
    string $caseName,
    string $sql,
    array $expectedRows,
    int $maxLimit,
    array $tables,
    Closure $flatten
): void {
    $expectedFlat = $flatten($expectedRows);
    $tests['real upstream select9.test ' . $caseName . ' full result'] = static function (TestRunner $t) use ($sql, $tables, $expectedFlat, $flatten, $caseName): void {
        $actual = $flatten(SQLiteSelectSql::execute($sql, $tables));

        $t->same($expectedFlat, $actual, $caseName);
        $t->contains('select9.test', 'select9.test compound full result');
    };

    $rowCount = count($expectedRows);
    for ($offset = 0; $offset <= $rowCount; $offset++) {
        for ($limit = 0; $limit <= $maxLimit; $limit++) {
            $limitedSql = $sql . ' LIMIT ' . $limit;
            if ($offset !== 0) {
                $limitedSql .= ' OFFSET ' . $offset;
            }
            $expected = $flatten(array_slice($expectedRows, $offset, $limit));
            $name = sprintf(
                'real upstream select9.test %s limit %02d offset %02d',
                $caseName,
                $limit,
                $offset,
            );

            $tests[$name] = static function (TestRunner $t) use ($limitedSql, $tables, $expected, $flatten, $caseName, $limit, $offset): void {
                $actual = $flatten(SQLiteSelectSql::execute($limitedSql, $tables));

                $t->same($expected, $actual, $limitedSql);
                $t->same(count($expected), count($actual), 'flat value count for ' . $limitedSql);
                $t->contains('select9.test', 'select9.test compound LIMIT/OFFSET');
                $t->true($limit >= 0 && $offset >= 0, $caseName . ' non-negative bounds');
            };
        }
    }
};

$unionOrderOne = $rowsFromFlatPairs([
    1, 'one', 1, 'two', 2, 'two', 2, 'four', 3, null, 3, null, 4, 'four', 4, 'eight', 5, 'five', 5, 'ten',
    6, null, 6, null, 7, 'seven', 7, 'fourteen', 8, 'eight', 8, 'sixteen', 9, null, 9, null, 10, 'ten', 10, 'twenty',
]);
$unionAllOrderOneTwo = $rowsFromFlatPairs([
    1, 'one', 1, 'two', 2, 'four', 2, 'two', 3, null, 3, null, 4, 'eight', 4, 'four', 5, 'five', 5, 'ten',
    6, null, 6, null, 7, 'fourteen', 7, 'seven', 8, 'eight', 8, 'sixteen', 9, null, 9, null, 10, 'ten', 10, 'twenty',
]);
$unionAllOrderTwoOne = $rowsFromFlatPairs([
    3, null, 3, null, 6, null, 6, null, 9, null, 9, null, 4, 'eight', 8, 'eight', 5, 'five', 2, 'four',
    4, 'four', 7, 'fourteen', 1, 'one', 7, 'seven', 8, 'sixteen', 5, 'ten', 10, 'ten', 10, 'twenty', 1, 'two', 2, 'two',
]);
$exceptOrderTwo = $rowsFromFlatPairs([
    8, 'eight', 5, 'five', 4, 'four', 1, 'one', 7, 'seven', 10, 'ten', 2, 'two',
]);

$addCompoundLimitCases(
    $tests,
    'select9-1.3 union all order by first column',
    'SELECT a, b FROM t1 UNION ALL SELECT d, e FROM t2 ORDER BY 1',
    $unionOrderOne,
    20,
    $tables,
    $flatten,
);
$addCompoundLimitCases(
    $tests,
    'select9-1.5 union all order by first and second columns',
    'SELECT a, b FROM t1 UNION ALL SELECT d, e FROM t2 ORDER BY 1, 2',
    $unionAllOrderOneTwo,
    20,
    $tables,
    $flatten,
);
$addCompoundLimitCases(
    $tests,
    'select9-1.6 union all order by second and first columns',
    'SELECT a, b FROM t1 UNION ALL SELECT d, e FROM t2 ORDER BY 2, 1',
    $unionAllOrderTwoOne,
    20,
    $tables,
    $flatten,
);
$addCompoundLimitCases(
    $tests,
    'select9-1.18 except order by second column',
    'SELECT a, b FROM t1 EXCEPT SELECT d, e FROM t2 ORDER BY 2',
    $exceptOrderTwo,
    12,
    $tables,
    $flatten,
);

return $tests;
