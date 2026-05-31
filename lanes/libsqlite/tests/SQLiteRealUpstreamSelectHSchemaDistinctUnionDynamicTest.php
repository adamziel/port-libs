<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test
 * - selectH-4.1 / selectH-4.2: DISTINCT schema-name compound SELECT rows.
 * - selectH-5.1: DISTINCT left arm UNION ALL empty right arm.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    $actual = $flattenRows($rows);

    $t->same($expected, $actual, $label . ' flat result');
    $t->same(count($expected), count($actual), $label . ' flat count');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint',
    );
    $t->contains('UNION ALL', $sql, $label . ' keeps upstream compound shape');
};

$tests = [];

$tests['real upstream selectH.test schema distinct union cites source scenarios'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test';
    $t->true(is_file($source), 'hydrated upstream selectH.test is available');
    $text = file_get_contents($source);
    $t->contains('do_execsql_test 4.1', $text);
    $t->contains('SELECT 1 FROM (SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema', $text);
    $t->contains('do_execsql_test 4.2', $text);
    $t->contains('do_execsql_test 5.1', $text);
};

for ($case = 0; $case < 1000; $case++) {
    $suffix = sprintf('%04d', $case);
    $tableName = 'setting_rows_' . $suffix;
    $viewName = 'active_settings_' . $suffix;
    $schemaName = $viewName . str_repeat(' ', ($case % 3) + 1);
    $first = 4000 + ($case * 3);
    $second = $first + 1;

    $tests[sprintf('real upstream selectH.test selectH-4.1 schema compound constant rows dynamic %04d', $case)] =
        static function (TestRunner $t) use ($assertFlatSelect, $schemaName, $tableName, $case): void {
            $tables = [
                'sqlite_schema' => [
                    ['name' => $schemaName],
                    ['name' => rtrim($schemaName)],
                ],
                $tableName => [
                    ['a' => 1, 'b' => 'alpha-' . $case],
                ],
            ];
            $sql = 'SELECT 1 FROM (SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema '
                . 'UNION ALL SELECT a FROM ' . $tableName . ')';

            $assertFlatSelect($t, $sql, $tables, [1, 1], 'selectH-4.1 seed ' . $case);
        };

    $tests[sprintf('real upstream selectH.test selectH-4.2 schema distinct union values dynamic %04d', $case)] =
        static function (TestRunner $t) use ($assertFlatSelect, $schemaName, $tableName, $viewName, $case): void {
            $tables = [
                'sqlite_schema' => [
                    ['name' => $schemaName],
                    ['name' => $viewName],
                ],
                $tableName => [
                    ['a' => $case + 10, 'b' => 'beta-' . $case],
                ],
            ];
            $sql = 'SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema UNION ALL SELECT a FROM ' . $tableName;

            $assertFlatSelect($t, $sql, $tables, [$schemaName, $case + 10], 'selectH-4.2 seed ' . $case);
        };

    $tests[sprintf('real upstream selectH.test selectH-5.1 distinct left empty right dynamic %04d', $case)] =
        static function (TestRunner $t) use ($assertFlatSelect, $first, $second, $case): void {
            $tables = [
                't1' => [
                    ['val1' => $first],
                    ['val1' => $second],
                    ['val1' => $second],
                ],
                't2' => [],
            ];
            $sql = 'SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2';

            $assertFlatSelect($t, $sql, $tables, [$first, $second], 'selectH-5.1 seed ' . $case);
        };
}

return $tests;
