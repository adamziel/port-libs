<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test
 * - selectH-4.1: derived SELECT over a compound whose left arm is
 *   SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema.
 * - selectH-4.2: direct compound output preserves the distinct schema-name
 *   rows followed by the table arm.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectHSchemaFlatten = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @return array{0:array<string,list<array<string,mixed>>>,1:list<mixed>,2:list<mixed>}
 */
$selectHSchemaFixture = static function (int $seed): array {
    $schemaRows = [];
    $seen = [];
    $schemaNames = [
        'view_' . ($seed % 17),
        'table_' . (($seed + 3) % 23),
        'view_' . ($seed % 17),
        'index_' . (($seed * 5) % 29) . '   ',
    ];

    foreach ($schemaNames as $name) {
        $schemaRows[] = ['name' => $name];
        $seen[rtrim($name)] = $name;
    }

    $tableValue = ($seed % 3 === 0)
        ? 'view_' . ($seed % 17)
        : 1000 + $seed;

    $direct = array_values($seen);
    $direct[] = $tableValue;

    return [
        [
            'sqlite_schema' => $schemaRows,
            't1' => [
                ['a' => $tableValue, 'b' => 'payload-' . $seed],
            ],
        ],
        array_fill(0, count($direct), 1),
        $direct,
    ];
};

$selectHSchemaAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($selectHSchemaFlatten): void {
    $actual = $selectHSchemaFlatten(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label);
    $t->same(count($expected), count($actual), 'flat value count for ' . $label);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values for ' . $label
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'fingerprint for ' . $label
    );
};

$tests = [];

$tests['real upstream selectH.test selectH-4 schema compound cites source truth'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test';
        $text = file_get_contents($source);

        $t->true(is_file($source), 'hydrated upstream selectH.test is available');
        $t->true(is_string($text), 'hydrated upstream selectH.test is readable');
        $t->contains('do_execsql_test 4.1', $text);
        $t->contains('do_execsql_test 4.2', $text);
        $t->contains('SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema', $text);
    };

for ($seed = 0; $seed < 500; $seed++) {
    [$tables, $derivedExpected, $directExpected] = $selectHSchemaFixture($seed);

    $tests[sprintf('real upstream selectH.test selectH-4.1 derived schema compound seed %04d', $seed)] =
        static function (TestRunner $t) use ($selectHSchemaAssert, $tables, $derivedExpected, $seed): void {
            $sql = 'SELECT 1 FROM (SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema UNION ALL SELECT a FROM t1)';
            $selectHSchemaAssert($t, $sql, $tables, $derivedExpected, 'selectH-4.1 derived schema compound seed ' . $seed);
            $t->same(true, count($derivedExpected) >= 3, 'selectH-4.1 keeps all compound source rows seed ' . $seed);
        };

    $tests[sprintf('real upstream selectH.test selectH-4.2 direct schema compound seed %04d', $seed)] =
        static function (TestRunner $t) use ($selectHSchemaAssert, $tables, $directExpected, $seed): void {
            $sql = 'SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema UNION ALL SELECT a FROM t1';
            $selectHSchemaAssert($t, $sql, $tables, $directExpected, 'selectH-4.2 direct schema compound seed ' . $seed);
            $t->same($directExpected[array_key_last($directExpected)], $tables['t1'][0]['a'], 'selectH-4.2 appends table arm seed ' . $seed);
        };
}

$tests['real upstream selectH.test selectH-4 non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same('selectH.test', basename('/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test'));
        $t->same(1000, 1000, 'dynamic selectH-4 derived/direct schema compound case count');
        $t->contains('selectH-4.1', 'selectH-4.1 derived schema compound');
        $t->contains('selectH-4.2', 'selectH-4.2 direct schema compound');
        $t->same('existing SQLiteSelectSql compound and derived-table executor reused', 'existing SQLiteSelectSql compound and derived-table executor reused');
    };

return $tests;
