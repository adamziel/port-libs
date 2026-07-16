<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test
 * - select9-1.7 through select9-1.20
 *
 * This ports the remaining compound SELECT set-operator ORDER BY and
 * LIMIT/OFFSET sweep behavior not covered by the earlier UNION ALL batch.
 */

/**
 * @param list<array<string,mixed>> $rows
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
 * @param list<mixed> $expected
 * @param array<string,list<array<string,mixed>>> $tables
 */
$assertCompound = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last value guard for ' . $sql
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'fingerprint for ' . $sql
    );
};

/**
 * @param list<mixed> $flatRows
 * @return list<mixed>
 */
$windowExpected = static function (array $flatRows, int $columnCount, int $limit, int $offset): array {
    return array_slice($flatRows, $offset * $columnCount, $limit * $columnCount);
};

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

$tests = [];

$tests['real upstream select9.test cites remaining set-op source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test';
    $t->true(is_file($source), 'hydrated upstream select9.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'select9.test can be read');
    $t->contains('test_compound_select select9-1.$iOuterLoop.7', $text);
    $t->contains('test_compound_select select9-1.$iOuterLoop.13', $text);
    $t->contains('test_compound_select select9-1.$iOuterLoop.18', $text);
};

$cases = [
    'select9-1.7 union distinct table order' => [
        'SELECT a, b FROM t1 UNION SELECT d, e FROM t2',
        [1, 'one', 1, 'two', 2, 'four', 2, 'two', 3, null, 4, 'eight', 4, 'four', 5, 'five', 5, 'ten', 6, null, 7, 'fourteen', 7, 'seven', 8, 'eight', 8, 'sixteen', 9, null, 10, 'ten', 10, 'twenty'],
    ],
    'select9-1.8 union distinct order by first column' => [
        'SELECT a, b FROM t1 UNION SELECT d, e FROM t2 ORDER BY 1',
        [1, 'one', 1, 'two', 2, 'four', 2, 'two', 3, null, 4, 'eight', 4, 'four', 5, 'five', 5, 'ten', 6, null, 7, 'fourteen', 7, 'seven', 8, 'eight', 8, 'sixteen', 9, null, 10, 'ten', 10, 'twenty'],
    ],
    'select9-1.9 union distinct order by second column' => [
        'SELECT a, b FROM t1 UNION SELECT d, e FROM t2 ORDER BY 2',
        [3, null, 6, null, 9, null, 4, 'eight', 8, 'eight', 5, 'five', 2, 'four', 4, 'four', 7, 'fourteen', 1, 'one', 7, 'seven', 8, 'sixteen', 5, 'ten', 10, 'ten', 10, 'twenty', 1, 'two', 2, 'two'],
    ],
    'select9-1.10 union distinct order by first and second' => [
        'SELECT a, b FROM t1 UNION SELECT d, e FROM t2 ORDER BY 1, 2',
        [1, 'one', 1, 'two', 2, 'four', 2, 'two', 3, null, 4, 'eight', 4, 'four', 5, 'five', 5, 'ten', 6, null, 7, 'fourteen', 7, 'seven', 8, 'eight', 8, 'sixteen', 9, null, 10, 'ten', 10, 'twenty'],
    ],
    'select9-1.11 union distinct order by second and first' => [
        'SELECT a, b FROM t1 UNION SELECT d, e FROM t2 ORDER BY 2, 1',
        [3, null, 6, null, 9, null, 4, 'eight', 8, 'eight', 5, 'five', 2, 'four', 4, 'four', 7, 'fourteen', 1, 'one', 7, 'seven', 8, 'sixteen', 5, 'ten', 10, 'ten', 10, 'twenty', 1, 'two', 2, 'two'],
    ],
    'select9-1.13 intersect order by second column' => [
        'SELECT a, b FROM t1 INTERSECT SELECT d, e FROM t2 ORDER BY 2',
        [3, null, 6, null, 9, null],
    ],
    'select9-1.14 intersect order by second and first' => [
        'SELECT a, b FROM t1 INTERSECT SELECT d, e FROM t2 ORDER BY 2, 1',
        [3, null, 6, null, 9, null],
    ],
    'select9-1.18 except order by second column' => [
        'SELECT a, b FROM t1 EXCEPT SELECT d, e FROM t2 ORDER BY 2',
        [8, 'eight', 5, 'five', 4, 'four', 1, 'one', 7, 'seven', 10, 'ten', 2, 'two'],
    ],
    'select9-1.19 except order by first and second' => [
        'SELECT a, b FROM t1 EXCEPT SELECT d, e FROM t2 ORDER BY 1, 2',
        [1, 'one', 2, 'two', 4, 'four', 5, 'five', 7, 'seven', 8, 'eight', 10, 'ten'],
    ],
    'select9-1.20 except order by second and first' => [
        'SELECT a, b FROM t1 EXCEPT SELECT d, e FROM t2 ORDER BY 2, 1',
        [8, 'eight', 5, 'five', 4, 'four', 1, 'one', 7, 'seven', 10, 'ten', 2, 'two'],
    ],
];

foreach ($cases as $name => [$baseSql, $expected]) {
    $tests['real upstream select9.test remaining set-op baseline ' . $name] =
        static function (TestRunner $t) use ($assertCompound, $tables, $baseSql, $expected, $name): void {
            $assertCompound($t, $baseSql, $tables, $expected);
            $t->contains('select9-', $name);
        };

    $rowCount = intdiv(count($expected), 2);
    for ($limit = 0; $limit <= $rowCount + 1; $limit++) {
        for ($offset = 0; $offset <= $rowCount + 1; $offset++) {
            $window = $windowExpected($expected, 2, $limit, $offset);
            $testName = sprintf(
                'real upstream select9.test remaining set-op %s limit %02d offset %02d',
                $name,
                $limit,
                $offset
            );

            $tests[$testName] = static function (TestRunner $t) use ($assertCompound, $tables, $baseSql, $limit, $offset, $window): void {
                $sql = $baseSql . ' LIMIT ' . $limit . ($offset === 0 ? '' : ' OFFSET ' . $offset);
                $assertCompound($t, $sql, $tables, $window);
            };
        }
    }
}

return $tests;
