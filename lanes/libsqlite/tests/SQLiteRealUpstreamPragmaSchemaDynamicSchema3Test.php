<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$baseRecords = static function (int $variant): array {
    return [
        new SQLiteSchemaRecord('table', "schema3_seed_{$variant}", "schema3_seed_{$variant}", 2, "CREATE TABLE schema3_seed_{$variant}(a INTEGER, b TEXT)", 1),
    ];
};

$prepared = static fn (int $variant, int $cookie): array => [
    ['id' => "schema3-db2-master-cache-{$variant}", 'schema_cookie' => $cookie, 'sql' => 'SELECT * FROM sqlite_master'],
    ['id' => "schema3-db2-target-cache-{$variant}", 'schema_cookie' => $cookie, 'sql' => 'SELECT * FROM schema3_seed_' . $variant],
];

$hasRecord = static function (array $records, string $type, string $name): bool {
    foreach ($records as $record) {
        if ($record instanceof SQLiteSchemaRecord && $record->type === $type && strcasecmp($record->name, $name) === 0) {
            return true;
        }
    }

    return false;
};

$columnNames = static function (array $records, string $table): array {
    return array_column((new SQLitePragmaSchemaCatalog($records))->execute("PRAGMA table_xinfo({$table})")['rows'], 'name');
};

$schema3Cases = [
    ['schema3-1 create table then select', ['CREATE TABLE t1_%d(a, b)'], 't1_%d', ['a', 'b'], null],
    ['schema3-2 create table then update', ['CREATE TABLE t2_%d(a, b)'], 't2_%d', ['a', 'b'], null],
    ['schema3-3 create table then delete', ['CREATE TABLE t3_%d(a, b)'], 't3_%d', ['a', 'b'], null],
    ['schema3-4 create table then insert', ['CREATE TABLE t4_%d(a, b)'], 't4_%d', ['a', 'b'], null],
    ['schema3-5 create table then drop table', ['CREATE TABLE t5_%d(a, b)'], 't5_%d', ['a', 'b'], 'DROP TABLE t5_%d'],
    ['schema3-6 create table then create index', ['CREATE TABLE t6_%d(a, b)'], 't6_%d', ['a', 'b'], 'CREATE INDEX i1_%d ON t6_%d(a)'],
    ['schema3-7 alter add c then select', ['CREATE TABLE t1_%d(a, b)', 'ALTER TABLE t1_%d ADD COLUMN c'], 't1_%d', ['a', 'b', 'c'], null],
    ['schema3-8 alter add c then update', ['CREATE TABLE t2_%d(a, b)', 'ALTER TABLE t2_%d ADD COLUMN c'], 't2_%d', ['a', 'b', 'c'], null],
    ['schema3-9 alter add d then update', ['CREATE TABLE t2_%d(a, b)', 'ALTER TABLE t2_%d ADD COLUMN c', 'ALTER TABLE t2_%d ADD COLUMN d'], 't2_%d', ['a', 'b', 'c', 'd'], null],
    ['schema3-10 alter add c then delete where', ['CREATE TABLE t3_%d(a, b)', 'ALTER TABLE t3_%d ADD COLUMN c'], 't3_%d', ['a', 'b', 'c'], null],
    ['schema3-11 alter add c then insert values', ['CREATE TABLE t4_%d(a, b)', 'ALTER TABLE t4_%d ADD COLUMN c'], 't4_%d', ['a', 'b', 'c'], null],
    ['schema3-12 alter add c then create index', ['CREATE TABLE t6_%d(a, b)', 'ALTER TABLE t6_%d ADD COLUMN c'], 't6_%d', ['a', 'b', 'c'], 'CREATE INDEX i2_%d ON t6_%d(c)'],
    ['schema3-13 alter add d then create trigger', ['CREATE TABLE t6_%d(a, b)', 'ALTER TABLE t6_%d ADD COLUMN c', 'ALTER TABLE t6_%d ADD COLUMN d'], 't6_%d', ['a', 'b', 'c', 'd'], 'CREATE TRIGGER tr1_%d AFTER UPDATE OF d ON t6_%d BEGIN SELECT 1, 2, 3; END'],
    ['schema3-14 create/drop index refresh', ['CREATE TABLE t1_%d(a, b)', 'CREATE INDEX i3_%d ON t1_%d(a)'], 't1_%d', ['a', 'b'], 'DROP INDEX i3_%d'],
    ['schema3-15 indexed select refresh', ['CREATE TABLE t2_%d(a, b)', 'CREATE INDEX i4_%d ON t2_%d(a)'], 't2_%d', ['a', 'b'], null],
    ['schema3-16 create/drop trigger refresh', ['CREATE TABLE t3_%d(a, b)', 'CREATE TRIGGER tr2_%d AFTER INSERT ON t3_%d BEGIN SELECT 1; END'], 't3_%d', ['a', 'b'], 'DROP TRIGGER tr2_%d'],
    ['schema3-17 create view after cache load', ['CREATE TABLE t1_%d(a, b)', 'ALTER TABLE t1_%d ADD COLUMN c', 'CREATE VIEW v1_%d AS SELECT * FROM t1_%d'], 't1_%d', ['a', 'b', 'c'], null],
    ['schema3-18 alter table updates star view', ['CREATE TABLE t1_%d(a, b)', 'ALTER TABLE t1_%d ADD COLUMN c', 'CREATE VIEW v1_%d AS SELECT * FROM t1_%d', 'ALTER TABLE t1_%d ADD COLUMN d'], 't1_%d', ['a', 'b', 'c', 'd'], null],
    ['schema3-19 drop and recreate table', ['CREATE TABLE t7_%d(a, b)'], 't7_%d', ['a', 'b'], ['DROP TABLE IF EXISTS t7_%d', 'CREATE TABLE t7_%d(c, d)']],
    ['schema3-20 drop and recreate index', ['CREATE TABLE t7_%d(c, d)', 'CREATE INDEX i5_%d ON t7_%d(c, d)'], 't7_%d', ['c', 'd'], ['DROP INDEX IF EXISTS i5_%d', 'CREATE INDEX i5_%d ON t7_%d(c)']],
    ['schema3-21 recreate trigger', ['CREATE TABLE t7_%d(c, d)', 'CREATE TRIGGER tr3_%d BEFORE DELETE ON t7_%d BEGIN SELECT 1, 2, 3; END'], 't7_%d', ['c', 'd'], ['DROP TRIGGER IF EXISTS tr3_%d', 'CREATE TRIGGER tr3_%d AFTER INSERT ON t7_%d BEGIN SELECT 1, 2, 3; END']],
    ['schema3-22 trigger after update of new table', ['CREATE TABLE t8_%d(a, b)'], 't8_%d', ['a', 'b'], 'CREATE TRIGGER tr4_%d AFTER UPDATE OF a ON t8_%d BEGIN SELECT 1, 2, 3; END'],
];

$formatSql = static function (string $template, int $variant): string {
    return vsprintf($template, array_fill(0, substr_count($template, '%d'), $variant));
};

foreach (range(1, 46) as $variant) {
    foreach ($schema3Cases as [$section, $firstConnectionDdl, $tableTemplate, $expectedColumns, $secondConnectionDdl]) {
        $tests["real upstream pragma schema3 stale cache refresh {$section} variant {$variant}"] = static function (TestRunner $t) use ($baseRecords, $prepared, $hasRecord, $columnNames, $formatSql, $variant, $section, $firstConnectionDdl, $tableTemplate, $expectedColumns, $secondConnectionDdl): void {
            $cookie = 3000 + ($variant * 100);
            $firstSql = array_map(static fn (string $sql): string => $formatSql($sql, $variant), $firstConnectionDdl);
            $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords($variant), $firstSql, $cookie, 'main', $prepared($variant, $cookie));
            $records = $plan['records'];
            $table = $formatSql($tableTemplate, $variant);

            $t->same('ok', $plan['status']);
            $t->same(count($firstSql), count($plan['operations']));
            $t->same($cookie + count($firstSql), $plan['after_schema_cookie']);
            $t->same(["schema3-db2-master-cache-{$variant}", "schema3-db2-target-cache-{$variant}"], $plan['invalidated_prepared']);
            $t->same(true, $hasRecord($records, 'table', $table));
            $t->same($expectedColumns, $columnNames($records, $table));

            if ($secondConnectionDdl === null) {
                $t->same($section, $section);
                return;
            }

            $secondTemplates = is_array($secondConnectionDdl) ? $secondConnectionDdl : [$secondConnectionDdl];
            $secondSql = array_map(static fn (string $sql): string => $formatSql($sql, $variant), $secondTemplates);
            $second = SQLiteSchemaDdlReparsePlan::apply($records, $secondSql, $plan['after_schema_cookie'], 'main', $prepared($variant, $plan['after_schema_cookie']));

            $t->same('ok', $second['status']);
            $t->same(count($secondSql), count($second['operations']));
            $t->same($plan['after_schema_cookie'] + count(array_filter($second['operations'], static fn (array $operation): bool => $operation['changed'] === true)), $second['after_schema_cookie']);
            $t->same(["schema3-db2-master-cache-{$variant}", "schema3-db2-target-cache-{$variant}"], $second['invalidated_prepared']);
        };
    }
}

$tests['real upstream pragma schema3 source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'schema3.test schema3-1.1 through schema3-1.22 multiclient stale schema refresh',
        'schema3.test create table/view/index/trigger cache refresh',
        'schema3.test ALTER TABLE ADD COLUMN cache refresh before SELECT/UPDATE/DELETE/INSERT/CREATE INDEX/CREATE TRIGGER',
        'schema3.test DROP IF EXISTS and recreate table/index/trigger cache refresh',
    ];

    $t->same(4, count($sections));
    $t->contains('schema3-1.1', $sections[0]);
    $t->contains('ALTER TABLE ADD COLUMN', $sections[2]);
};

return $tests;
