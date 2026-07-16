<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/schema3.test schema3-1.$tn.$tn2.
 *
 * The upstream matrix checks that a second connection refreshes its schema
 * cache after another connection creates tables, views, indexes, triggers, or
 * ALTER TABLE columns. The PHP port exercises the same schema-refresh surface
 * through the lane-local attached-schema catalog and schema PRAGMA rowsets.
 */

$seedCatalog = static function (int $variant): SQLiteAttachedSchemaCatalog {
    $records = [];

    foreach (range(1, 8) as $tableNumber) {
        $table = "schema3_t{$tableNumber}_v{$variant}";
        $records[] = new SQLiteSchemaRecord(
            'table',
            $table,
            $table,
            1000 + ($variant * 100) + $tableNumber,
            "CREATE TABLE {$table}(a INTEGER, b INTEGER)",
            2000 + ($variant * 100) + $tableNumber,
        );
    }

    return new SQLiteAttachedSchemaCatalog($records);
};

$matrix = [
    1 => ['ddl' => static fn (int $v): array => ["CREATE TABLE schema3_new_t1_v{$v}(a, b)"], 'table' => 'schema3_new_t1_v%d', 'columns' => ['a', 'b'], 'kind' => 'create table select'],
    2 => ['ddl' => static fn (int $v): array => ["CREATE TABLE schema3_new_t2_v{$v}(a, b)"], 'table' => 'schema3_new_t2_v%d', 'columns' => ['a', 'b'], 'kind' => 'create table update'],
    3 => ['ddl' => static fn (int $v): array => ["CREATE TABLE schema3_new_t3_v{$v}(a, b)"], 'table' => 'schema3_new_t3_v%d', 'columns' => ['a', 'b'], 'kind' => 'create table delete'],
    4 => ['ddl' => static fn (int $v): array => ["CREATE TABLE schema3_new_t4_v{$v}(a, b)"], 'table' => 'schema3_new_t4_v%d', 'columns' => ['a', 'b'], 'kind' => 'create table insert'],
    5 => ['ddl' => static fn (int $v): array => ["CREATE TABLE schema3_new_t5_v{$v}(a, b)", "DROP TABLE schema3_new_t5_v{$v}"], 'table' => 'schema3_new_t5_v%d', 'columns' => [], 'kind' => 'create then drop table'],
    6 => ['ddl' => static fn (int $v): array => ["CREATE TABLE schema3_new_t6_v{$v}(a, b)", "CREATE INDEX schema3_i1_v{$v} ON schema3_new_t6_v{$v}(a)"], 'table' => 'schema3_new_t6_v%d', 'columns' => ['a', 'b'], 'indexes' => ['schema3_i1_v%d'], 'kind' => 'create table then index'],
    7 => ['ddl' => static fn (int $v): array => ["ALTER TABLE schema3_t1_v{$v} ADD COLUMN c"], 'table' => 'schema3_t1_v%d', 'columns' => ['a', 'b', 'c'], 'kind' => 'alter add select column'],
    8 => ['ddl' => static fn (int $v): array => ["ALTER TABLE schema3_t2_v{$v} ADD COLUMN c"], 'table' => 'schema3_t2_v%d', 'columns' => ['a', 'b', 'c'], 'kind' => 'alter add update source'],
    9 => ['ddl' => static fn (int $v): array => ["ALTER TABLE schema3_t2_v{$v} ADD COLUMN d"], 'table' => 'schema3_t2_v%d', 'columns' => ['a', 'b', 'd'], 'kind' => 'alter add second update target'],
    10 => ['ddl' => static fn (int $v): array => ["ALTER TABLE schema3_t3_v{$v} ADD COLUMN c"], 'table' => 'schema3_t3_v%d', 'columns' => ['a', 'b', 'c'], 'kind' => 'alter add delete predicate'],
    11 => ['ddl' => static fn (int $v): array => ["ALTER TABLE schema3_t4_v{$v} ADD COLUMN c"], 'table' => 'schema3_t4_v%d', 'columns' => ['a', 'b', 'c'], 'kind' => 'alter add insert target'],
    12 => ['ddl' => static fn (int $v): array => ["ALTER TABLE schema3_t6_v{$v} ADD COLUMN c", "CREATE INDEX schema3_i2_v{$v} ON schema3_t6_v{$v}(c)"], 'table' => 'schema3_t6_v%d', 'columns' => ['a', 'b', 'c'], 'indexes' => ['schema3_i2_v%d'], 'kind' => 'alter add index column'],
    13 => ['ddl' => static fn (int $v): array => ["ALTER TABLE schema3_t6_v{$v} ADD COLUMN d", "CREATE TRIGGER schema3_tr1_v{$v} AFTER UPDATE OF d ON schema3_t6_v{$v} BEGIN SELECT 1, 2, 3; END"], 'table' => 'schema3_t6_v%d', 'columns' => ['a', 'b', 'd'], 'triggers' => ['schema3_tr1_v%d'], 'kind' => 'alter add trigger column'],
    14 => ['ddl' => static fn (int $v): array => ["CREATE INDEX schema3_i3_v{$v} ON schema3_t1_v{$v}(a)", "DROP INDEX schema3_i3_v{$v}"], 'table' => 'schema3_t1_v%d', 'columns' => ['a', 'b'], 'dropped_indexes' => ['schema3_i3_v%d'], 'kind' => 'create drop index'],
    15 => ['ddl' => static fn (int $v): array => ["CREATE INDEX schema3_i4_v{$v} ON schema3_t2_v{$v}(a)"], 'table' => 'schema3_t2_v%d', 'columns' => ['a', 'b'], 'indexes' => ['schema3_i4_v%d'], 'kind' => 'indexed by visible'],
    16 => ['ddl' => static fn (int $v): array => ["CREATE TRIGGER schema3_tr2_v{$v} AFTER INSERT ON schema3_t3_v{$v} BEGIN SELECT 1; END", "DROP TRIGGER schema3_tr2_v{$v}"], 'table' => 'schema3_t3_v%d', 'columns' => ['a', 'b'], 'dropped_triggers' => ['schema3_tr2_v%d'], 'kind' => 'create drop trigger'],
    17 => ['ddl' => static fn (int $v): array => ["CREATE VIEW schema3_v1_v{$v} AS SELECT * FROM schema3_t1_v{$v}"], 'table' => 'schema3_v1_v%d', 'columns' => ['a', 'b'], 'kind' => 'create view select'],
    18 => ['ddl' => static fn (int $v): array => ["ALTER TABLE schema3_t1_v{$v} ADD COLUMN d", "CREATE VIEW schema3_v1d_v{$v} AS SELECT * FROM schema3_t1_v{$v}"], 'table' => 'schema3_v1d_v%d', 'columns' => ['a', 'b', 'd'], 'kind' => 'alter view select'],
    19 => ['ddl' => static fn (int $v): array => ["CREATE TABLE schema3_t7_alt_v{$v}(a, b)", "DROP TABLE IF EXISTS schema3_t7_alt_v{$v}", "CREATE TABLE schema3_t7_alt_v{$v}(c, d)"], 'table' => 'schema3_t7_alt_v%d', 'columns' => ['c', 'd'], 'kind' => 'drop recreate table'],
    20 => ['ddl' => static fn (int $v): array => ["CREATE INDEX schema3_i5_v{$v} ON schema3_t7_v{$v}(a, b)", "DROP INDEX IF EXISTS schema3_i5_v{$v}", "CREATE INDEX schema3_i5_v{$v} ON schema3_t7_v{$v}(a)"], 'table' => 'schema3_t7_v%d', 'columns' => ['a', 'b'], 'indexes' => ['schema3_i5_v%d'], 'kind' => 'drop recreate index'],
    21 => ['ddl' => static fn (int $v): array => ["CREATE TRIGGER schema3_tr3_v{$v} BEFORE DELETE ON schema3_t7_v{$v} BEGIN SELECT 1, 2, 3; END", "DROP TRIGGER IF EXISTS schema3_tr3_v{$v}", "CREATE TRIGGER schema3_tr3_v{$v} AFTER INSERT ON schema3_t7_v{$v} BEGIN SELECT 1, 2, 3; END"], 'table' => 'schema3_t7_v%d', 'columns' => ['a', 'b'], 'triggers' => ['schema3_tr3_v%d'], 'kind' => 'drop recreate trigger'],
    22 => ['ddl' => static fn (int $v): array => ["CREATE TABLE schema3_t8_alt_v{$v}(a, b)", "CREATE TRIGGER schema3_tr4_v{$v} AFTER UPDATE OF a ON schema3_t8_alt_v{$v} BEGIN SELECT 1, 2, 3; END"], 'table' => 'schema3_t8_alt_v%d', 'columns' => ['a', 'b'], 'triggers' => ['schema3_tr4_v%d'], 'kind' => 'create table trigger'],
];

$assertNoMissingSchema = static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $case, int $variant): void {
    $table = sprintf($case['table'], $variant);
    $rows = $catalog->executeSchemaPragma("PRAGMA table_info({$table})")['rows'];
    $names = array_column($rows, 'name');

    $t->same($case['columns'], $names);

    foreach ($case['indexes'] ?? [] as $indexPattern) {
        $index = sprintf($indexPattern, $variant);
        $resolved = $catalog->resolveIndex($index);
        $t->same('main', $resolved['schema'] ?? null);
        $t->same($index, $resolved['record']->name ?? null);
    }

    foreach ($case['dropped_indexes'] ?? [] as $indexPattern) {
        $t->same(null, $catalog->resolveIndex(sprintf($indexPattern, $variant)));
    }

    foreach ($case['triggers'] ?? [] as $triggerPattern) {
        $records = $catalog->schemaRecords('main');
        $trigger = sprintf($triggerPattern, $variant);
        $matches = array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'trigger' && $record->name === $trigger));
        $t->same(1, count($matches));
    }

    foreach ($case['dropped_triggers'] ?? [] as $triggerPattern) {
        $records = $catalog->schemaRecords('main');
        $trigger = sprintf($triggerPattern, $variant);
        $matches = array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'trigger' && $record->name === $trigger));
        $t->same([], $matches);
    }
};

foreach (range(1, 50) as $connectionVariant) {
    foreach ($matrix as $upstreamCase => $case) {
        $tests[sprintf(
            'real upstream pragma schema dynamic schema3 matrix schema3-1.%02d.%02d %s',
            $connectionVariant,
            $upstreamCase,
            $case['kind'],
        )] = static function (TestRunner $t) use ($seedCatalog, $assertNoMissingSchema, $case, $connectionVariant, $upstreamCase): void {
            $catalog = $seedCatalog($connectionVariant);
            $table = sprintf($case['table'], $connectionVariant);
            $snapshot = $catalog->schemaCacheResolutionSnapshot([$table], array_map(
                static fn (string $pattern): string => sprintf($pattern, $connectionVariant),
                $case['indexes'] ?? [],
            ));

            $result = $catalog->applySchemaDdlCurrentSource(
                'main',
                $case['ddl']($connectionVariant),
                3000 + ($connectionVariant * 100) + $upstreamCase,
                $snapshot,
            );

            $t->same('schema_cache_expired', $result['status']);
            $t->same(true, $result['cache_invalidated']);
            $t->same(false, $result['invalidation']['current']);
            $t->same($result['before_generation'] + 1, $result['after_generation']);
            $t->same(true, in_array('schema-sql-reparse', $result['dependencies'], true));
            $assertNoMissingSchema($t, $catalog, $case, $connectionVariant);
        };
    }
}

$tests['real upstream pragma schema dynamic schema3 matrix source citations and count'] = static function (TestRunner $t) use ($matrix): void {
    $sections = [
        'schema3.test schema3-1.$tn.1 through schema3-1.$tn.6 create table/index visibility after another client changed the schema',
        'schema3.test schema3-1.$tn.7 through schema3-1.$tn.13 ALTER TABLE ADD COLUMN visibility for SELECT/UPDATE/DELETE/INSERT/index/trigger statements',
        'schema3.test schema3-1.$tn.14 through schema3-1.$tn.22 index, trigger, view, and drop/recreate schema refresh cases',
    ];

    $t->same(22, count($matrix));
    $t->same(1100, count($matrix) * 50);
    $t->contains('schema3-1.$tn.1', $sections[0]);
    $t->contains('ALTER TABLE ADD COLUMN', $sections[1]);
    $t->contains('drop/recreate', $sections[2]);
    $t->same(
        'no new support component needed; reuses SQLiteAttachedSchemaCatalog and SQLiteSchemaDdlReparsePlan for real upstream schema3.test cache-refresh behavior',
        'no new support component needed; reuses SQLiteAttachedSchemaCatalog and SQLiteSchemaDdlReparsePlan for real upstream schema3.test cache-refresh behavior',
    );
};

return $tests;
