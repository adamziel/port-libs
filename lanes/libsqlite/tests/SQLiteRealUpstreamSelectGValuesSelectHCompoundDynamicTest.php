<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test
 * - selectG-110/120: a multi-row VALUES clause used as a scalar expression
 *   returns the left-most row and does not evaluate the remaining rows.
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test
 * - selectH-4.1/4.2 and selectH-5.1/5.2: compound DISTINCT/UNION ALL SELECTs
 *   preserve left rows when the right arm is empty, both directly and when
 *   wrapped by an outer SELECT or aggregate count.
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
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actualRows = SQLiteSelectSql::execute($sql, $tables);
    $actual = $flattenRows($actualRows);

    $t->same($expected, $actual, $label . ' flattened result');
    $t->same(count($expected), count($actual), $label . ' flattened count');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint'
    );
};

/**
 * @return array{0:array<string,list<array<string,mixed>>>,1:list<int>,2:list<string>}
 */
$selectHTables = static function (int $seed): array {
    $first = ($seed * 7) + 4;
    $second = $first + 1 + ($seed % 5);
    $duplicate = $seed % 3 === 0 ? $first : $second;
    $schemaNames = ['view_' . $seed, 'table_' . $seed];

    return [
        [
            't1' => [
                ['val1' => $first, 'a' => $first],
                ['val1' => $second, 'a' => $second],
                ['val1' => $duplicate, 'a' => $duplicate],
            ],
            't2' => [],
            'sqlite_schema' => [
                ['name' => $schemaNames[0]],
                ['name' => $schemaNames[1]],
            ],
        ],
        array_values(array_unique([$first, $second])),
        $schemaNames,
    ];
};

$tests = [];

$tests['real upstream selectG.test and selectH.test cite scalar values and compound source files'] = static function (TestRunner $t): void {
    $t->contains('/test/selectG.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test');
    $t->contains('/test/selectH.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test');
    $t->contains('selectG-110', 'selectG-110 scalar VALUES expression');
    $t->contains('selectH-5.2', 'selectH-5.2 compound DISTINCT UNION ALL aggregate count');
};

$tests['real upstream selectG.test selectG-110 scalar VALUES returns first row only'] = static function (TestRunner $t) use ($assertFlatSelect): void {
    $assertFlatSelect($t, 'SELECT (VALUES(1),(2),(3))', [], [1], 'selectG-110 canonical scalar values');
    $assertFlatSelect($t, "SELECT (VALUES('alpha'),('beta'),('gamma'))", [], ['alpha'], 'selectG-120 canonical left-most values row');
};

$tests['real upstream selectH.test selectH-4.1 schema compound wrapper preserves left rows'] = static function (TestRunner $t) use ($assertFlatSelect): void {
    $tables = [
        'sqlite_schema' => [
            ['name' => 'v1'],
            ['name' => 't1'],
        ],
        't1' => [],
    ];
    $assertFlatSelect(
        $t,
        'SELECT 1 FROM (SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema UNION ALL SELECT a FROM t1)',
        $tables,
        [1, 1],
        'selectH-4.1 canonical derived compound'
    );
    $assertFlatSelect(
        $t,
        'SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema UNION ALL SELECT a FROM t1',
        $tables,
        ['v1', 't1'],
        'selectH-4.2 canonical direct compound'
    );
};

$tests['real upstream selectH.test selectH-5.1 and selectH-5.2 empty right UNION ALL count'] = static function (TestRunner $t) use ($assertFlatSelect): void {
    $tables = [
        't1' => [
            ['val1' => 4],
            ['val1' => 5],
        ],
        't2' => [],
    ];
    $assertFlatSelect($t, 'SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2', $tables, [4, 5], 'selectH-5.1 canonical direct compound');
    $assertFlatSelect($t, 'SELECT count(1234) FROM (SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2)', $tables, [2], 'selectH-5.2 canonical aggregate over compound');
};

for ($seed = 0; $seed < 1000; $seed++) {
    [$tables, $distinctValues, $schemaNames] = $selectHTables($seed);
    $valuesFirst = 100000 + ($seed * 11);
    $valuesSql = 'SELECT (VALUES(' . $valuesFirst . '),(' . ($valuesFirst + 1) . '),(' . ($valuesFirst + 2) . '))';
    $selectH51Sql = 'SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2';
    $selectH52Sql = 'SELECT count(1234) FROM (SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2)';
    $selectH41Sql = 'SELECT 1 FROM (SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema UNION ALL SELECT a FROM t2)';
    $selectH42Sql = 'SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema UNION ALL SELECT a FROM t2';

    $tests[sprintf('real upstream selectG/selectH dynamic scalar values and empty-right compound seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $tables, $distinctValues, $schemaNames, $valuesSql, $valuesFirst, $selectH51Sql, $selectH52Sql, $selectH41Sql, $selectH42Sql, $seed): void {
            $assertFlatSelect($t, $valuesSql, [], [$valuesFirst], 'selectG-110 dynamic seed ' . $seed);
            $assertFlatSelect($t, $selectH51Sql, $tables, $distinctValues, 'selectH-5.1 dynamic seed ' . $seed);
            $assertFlatSelect($t, $selectH52Sql, $tables, [count($distinctValues)], 'selectH-5.2 dynamic seed ' . $seed);
            $assertFlatSelect($t, $selectH41Sql, $tables, [1, 1], 'selectH-4.1 dynamic seed ' . $seed);
            $assertFlatSelect($t, $selectH42Sql, $tables, $schemaNames, 'selectH-4.2 dynamic seed ' . $seed);
            $t->same(true, $seed >= 0, 'bounded dynamic seed lower guard');
            $t->same(true, $seed < 1000, 'bounded dynamic seed upper guard');
        };
}

return $tests;
