<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$record = static function (string $type, string $name, string $table, int $rootPage, ?string $sql, int $rowId): SQLiteSchemaRecord {
    return new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);
};

$baseTable = static function (string $name, int $variant, array $columns = ['a TEXT', 'b TEXT']): SQLiteSchemaRecord {
    return new SQLiteSchemaRecord(
        'table',
        $name,
        $name,
        10000 + $variant,
        'CREATE TABLE ' . $name . '(' . implode(', ', $columns) . ')',
        20000 + $variant,
    );
};

$columnNames = static function (SQLiteAttachedSchemaCatalog $catalog, string $table): array {
    return array_column($catalog->executeSchemaPragma("PRAGMA main.table_info({$table})")['rows'], 'name');
};

$indexNames = static function (SQLiteAttachedSchemaCatalog $catalog, string $table): array {
    return array_column($catalog->executeSchemaPragma("PRAGMA main.index_list({$table})")['rows'], 'name');
};

$recordNames = static function (SQLiteAttachedSchemaCatalog $catalog, string $type): array {
    return array_values(array_map(
        static fn (SQLiteSchemaRecord $schemaRecord): string => $schemaRecord->name,
        array_filter($catalog->schemaRecords('main'), static fn (SQLiteSchemaRecord $schemaRecord): bool => $schemaRecord->type === $type),
    ));
};

$applySchema3 = static function (
    array $setup,
    string $firstDdl,
    array $secondDdl,
    int $variant,
    array $watchTables,
    array $watchIndexes = [],
) use ($record): array {
    $catalog = new SQLiteAttachedSchemaCatalog($setup);
    $snapshot = $catalog->schemaCacheResolutionSnapshot($watchTables, $watchIndexes);
    $prepared = [
        [
            'id' => 'schema3-prepared-' . $variant,
            'schema_cookie' => 70000 + $variant,
            'sql' => 'SELECT * FROM sqlite_schema',
        ],
    ];

    $first = $catalog->applySchemaDdlCurrentSource('main', [$firstDdl], 70000 + $variant, $snapshot, $prepared);
    $second = null;
    if ($secondDdl !== []) {
        $second = $catalog->applySchemaDdlCurrentSource(
            'main',
            $secondDdl,
            (int) $first['ddl_plan']['after_schema_cookie'],
            null,
            [
                [
                    'id' => 'schema3-second-prepared-' . $variant,
                    'schema_cookie' => (int) $first['ddl_plan']['after_schema_cookie'],
                    'sql' => 'SELECT * FROM sqlite_schema',
                ],
            ],
        );
    }

    return [$catalog, $first, $second];
};

foreach (range(1, 46) as $variant) {
    $prefix = 'schema3_dyn_' . $variant . '_';

    $schema3Cases = [
        [
            'upstream' => 'schema3-1.*.1',
            'label' => 'create table select sees table',
            'setup' => [],
            'first' => "CREATE TABLE {$prefix}t1(a, b)",
            'second' => [],
            'watchTables' => [$prefix . 't1'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first) use ($columnNames, $prefix): void {
                $t->same('schema_cache_expired', $first['status']);
                $t->same('create_table', $first['ddl_plan']['operations'][0]['kind']);
                $t->same(['a', 'b'], $columnNames($catalog, $prefix . 't1'));
                $t->same([$prefix . 't1'], $first['invalidation']['changed_tables']);
            },
        ],
        [
            'upstream' => 'schema3-1.*.2',
            'label' => 'create table update sees table',
            'setup' => [],
            'first' => "CREATE TABLE {$prefix}t2(a, b)",
            'second' => [],
            'watchTables' => [$prefix . 't2'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first) use ($columnNames, $prefix): void {
                $t->same([$prefix . 't2'], $first['invalidation']['changed_tables']);
                $t->same(['a', 'b'], $columnNames($catalog, $prefix . 't2'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.3',
            'label' => 'create table delete sees table',
            'setup' => [],
            'first' => "CREATE TABLE {$prefix}t3(a, b)",
            'second' => [],
            'watchTables' => [$prefix . 't3'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first) use ($columnNames, $prefix): void {
                $t->same('schema_cache_expired', $first['status']);
                $t->same(['a', 'b'], $columnNames($catalog, $prefix . 't3'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.4',
            'label' => 'create table insert sees table',
            'setup' => [],
            'first' => "CREATE TABLE {$prefix}t4(a, b)",
            'second' => [],
            'watchTables' => [$prefix . 't4'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first) use ($columnNames, $prefix): void {
                $t->same([$prefix . 't4'], $first['invalidation']['changed_tables']);
                $t->same(['a', 'b'], $columnNames($catalog, $prefix . 't4'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.5',
            'label' => 'create then drop table refreshes cache',
            'setup' => [],
            'first' => "CREATE TABLE {$prefix}t5(a, b)",
            'second' => ["DROP TABLE {$prefix}t5"],
            'watchTables' => [$prefix . 't5'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first, ?array $second) use ($columnNames, $prefix): void {
                $t->same([$prefix . 't5'], $first['invalidation']['changed_tables']);
                $t->same('drop_table', $second['ddl_plan']['operations'][0]['kind']);
                $t->same([], $columnNames($catalog, $prefix . 't5'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.6',
            'label' => 'create table then create index sees table',
            'setup' => [],
            'first' => "CREATE TABLE {$prefix}t6(a, b)",
            'second' => ["CREATE INDEX {$prefix}i1 ON {$prefix}t6(a)"],
            'watchTables' => [$prefix . 't6'],
            'watchIndexes' => [$prefix . 'i1'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first, ?array $second) use ($indexNames, $prefix): void {
                $t->same('create_table', $first['ddl_plan']['operations'][0]['kind']);
                $t->same('create_index', $second['ddl_plan']['operations'][0]['kind']);
                $t->same([$prefix . 'i1'], $indexNames($catalog, $prefix . 't6'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.7',
            'label' => 'alter add column select sees column',
            'setup' => [$baseTable($prefix . 't1', $variant)],
            'first' => "ALTER TABLE {$prefix}t1 ADD COLUMN c",
            'second' => [],
            'watchTables' => [$prefix . 't1'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first) use ($columnNames, $prefix): void {
                $t->same('alter_table_add_column', $first['ddl_plan']['operations'][0]['kind']);
                $t->same(['a', 'b', 'c'], $columnNames($catalog, $prefix . 't1'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.8',
            'label' => 'alter add column update sees column',
            'setup' => [$baseTable($prefix . 't2', $variant)],
            'first' => "ALTER TABLE {$prefix}t2 ADD COLUMN c",
            'second' => [],
            'watchTables' => [$prefix . 't2'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog) use ($columnNames, $prefix): void {
                $t->same(['a', 'b', 'c'], $columnNames($catalog, $prefix . 't2'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.9',
            'label' => 'second alter update sees new column',
            'setup' => [$baseTable($prefix . 't2', $variant, ['a TEXT', 'b TEXT', 'c TEXT'])],
            'first' => "ALTER TABLE {$prefix}t2 ADD COLUMN d",
            'second' => [],
            'watchTables' => [$prefix . 't2'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog) use ($columnNames, $prefix): void {
                $t->same(['a', 'b', 'c', 'd'], $columnNames($catalog, $prefix . 't2'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.10',
            'label' => 'alter add column delete predicate sees column',
            'setup' => [$baseTable($prefix . 't3', $variant)],
            'first' => "ALTER TABLE {$prefix}t3 ADD COLUMN c",
            'second' => [],
            'watchTables' => [$prefix . 't3'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog) use ($columnNames, $prefix): void {
                $t->same('c', $columnNames($catalog, $prefix . 't3')[2]);
            },
        ],
        [
            'upstream' => 'schema3-1.*.11',
            'label' => 'alter add column insert sees column',
            'setup' => [$baseTable($prefix . 't4', $variant)],
            'first' => "ALTER TABLE {$prefix}t4 ADD COLUMN c",
            'second' => [],
            'watchTables' => [$prefix . 't4'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog) use ($columnNames, $prefix): void {
                $t->same(['a', 'b', 'c'], $columnNames($catalog, $prefix . 't4'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.12',
            'label' => 'alter add column then create index sees column',
            'setup' => [$baseTable($prefix . 't6', $variant)],
            'first' => "ALTER TABLE {$prefix}t6 ADD COLUMN c",
            'second' => ["CREATE INDEX {$prefix}i2 ON {$prefix}t6(c)"],
            'watchTables' => [$prefix . 't6'],
            'watchIndexes' => [$prefix . 'i2'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first, ?array $second) use ($columnNames, $indexNames, $prefix): void {
                $t->same('c', $columnNames($catalog, $prefix . 't6')[2]);
                $t->same('create_index', $second['ddl_plan']['operations'][0]['kind']);
                $t->same([$prefix . 'i2'], $indexNames($catalog, $prefix . 't6'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.13',
            'label' => 'alter add column then create trigger sees column',
            'setup' => [$baseTable($prefix . 't6', $variant, ['a TEXT', 'b TEXT', 'c TEXT'])],
            'first' => "ALTER TABLE {$prefix}t6 ADD COLUMN d",
            'second' => ["CREATE TRIGGER {$prefix}tr1 AFTER UPDATE OF d ON {$prefix}t6 BEGIN SELECT 1, 2, 3; END"],
            'watchTables' => [$prefix . 't6'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first, ?array $second) use ($recordNames, $prefix): void {
                $t->same('d', $first['ddl_plan']['operations'][0]['column']);
                $t->same('create_trigger', $second['ddl_plan']['operations'][0]['kind']);
                $t->same(true, in_array($prefix . 'tr1', $recordNames($catalog, 'trigger'), true));
            },
        ],
        [
            'upstream' => 'schema3-1.*.14',
            'label' => 'create index then drop index refreshes cache',
            'setup' => [$baseTable($prefix . 't1', $variant)],
            'first' => "CREATE INDEX {$prefix}i3 ON {$prefix}t1(a)",
            'second' => ["DROP INDEX {$prefix}i3"],
            'watchTables' => [$prefix . 't1'],
            'watchIndexes' => [$prefix . 'i3'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first, ?array $second) use ($indexNames, $prefix): void {
                $t->same([$prefix . 'i3'], $first['invalidation']['changed_indexes']);
                $t->same('drop_index', $second['ddl_plan']['operations'][0]['kind']);
                $t->same([], $indexNames($catalog, $prefix . 't1'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.15',
            'label' => 'create index indexed by sees index',
            'setup' => [$baseTable($prefix . 't2', $variant)],
            'first' => "CREATE INDEX {$prefix}i4 ON {$prefix}t2(a)",
            'second' => [],
            'watchTables' => [$prefix . 't2'],
            'watchIndexes' => [$prefix . 'i4'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first) use ($indexNames, $prefix): void {
                $t->same([$prefix . 'i4'], $first['invalidation']['changed_indexes']);
                $t->same([$prefix . 'i4'], $indexNames($catalog, $prefix . 't2'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.16',
            'label' => 'create trigger then drop trigger refreshes cache',
            'setup' => [$baseTable($prefix . 't3', $variant)],
            'first' => "CREATE TRIGGER {$prefix}tr2 AFTER INSERT ON {$prefix}t3 BEGIN SELECT 1; END",
            'second' => ["DROP TRIGGER {$prefix}tr2"],
            'watchTables' => [$prefix . 't3'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first, ?array $second) use ($recordNames, $prefix): void {
                $t->same('create_trigger', $first['ddl_plan']['operations'][0]['kind']);
                $t->same('drop_trigger', $second['ddl_plan']['operations'][0]['kind']);
                $t->same(false, in_array($prefix . 'tr2', $recordNames($catalog, 'trigger'), true));
            },
        ],
        [
            'upstream' => 'schema3-1.*.17',
            'label' => 'create view select sees view',
            'setup' => [$baseTable($prefix . 't1', $variant, ['a TEXT', 'b TEXT', 'c TEXT'])],
            'first' => "CREATE VIEW {$prefix}v1 AS SELECT * FROM {$prefix}t1",
            'second' => [],
            'watchTables' => [$prefix . 'v1'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog) use ($recordNames, $prefix): void {
                $t->same(true, in_array($prefix . 'v1', $recordNames($catalog, 'view'), true));
            },
        ],
        [
            'upstream' => 'schema3-1.*.18',
            'label' => 'alter source table refreshes star view',
            'setup' => [
                $baseTable($prefix . 't1', $variant, ['a TEXT', 'b TEXT', 'c TEXT']),
                $record('view', $prefix . 'v1', $prefix . 'v1', 0, "CREATE VIEW {$prefix}v1 AS SELECT * FROM {$prefix}t1", 30000 + $variant),
            ],
            'first' => "ALTER TABLE {$prefix}t1 ADD COLUMN d",
            'second' => [],
            'watchTables' => [$prefix . 't1', $prefix . 'v1'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first) use ($columnNames, $prefix): void {
                $t->same('d', $columnNames($catalog, $prefix . 't1')[3]);
                $t->same(['view:' . $prefix . 'v1'], $first['ddl_plan']['operations'][0]['star_expansion_records']);
            },
        ],
        [
            'upstream' => 'schema3-1.*.19',
            'label' => 'drop create table refreshes replacement root',
            'setup' => [],
            'first' => "CREATE TABLE {$prefix}t7(a, b)",
            'second' => ["DROP TABLE {$prefix}t7", "CREATE TABLE {$prefix}t7(c, d)"],
            'watchTables' => [$prefix . 't7'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first, ?array $second) use ($columnNames, $prefix): void {
                $t->same(['create_table'], array_column($first['ddl_plan']['operations'], 'kind'));
                $t->same(['drop_table', 'create_table'], array_column($second['ddl_plan']['operations'], 'kind'));
                $t->same(['c', 'd'], $columnNames($catalog, $prefix . 't7'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.20',
            'label' => 'drop create index refreshes replacement root',
            'setup' => [$baseTable($prefix . 't7', $variant, ['c TEXT', 'd TEXT'])],
            'first' => "CREATE INDEX {$prefix}i5 ON {$prefix}t7(c, d)",
            'second' => ["DROP INDEX {$prefix}i5", "CREATE INDEX {$prefix}i5 ON {$prefix}t7(c)"],
            'watchTables' => [$prefix . 't7'],
            'watchIndexes' => [$prefix . 'i5'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first, ?array $second) use ($indexNames, $prefix): void {
                $t->same([$prefix . 'i5'], $first['invalidation']['changed_indexes']);
                $t->same(['drop_index', 'create_index'], array_column($second['ddl_plan']['operations'], 'kind'));
                $t->same([$prefix . 'i5'], $indexNames($catalog, $prefix . 't7'));
            },
        ],
        [
            'upstream' => 'schema3-1.*.21',
            'label' => 'drop create trigger refreshes replacement body',
            'setup' => [$baseTable($prefix . 't7', $variant, ['c TEXT', 'd TEXT'])],
            'first' => "CREATE TRIGGER {$prefix}tr3 BEFORE DELETE ON {$prefix}t7 BEGIN SELECT 1, 2, 3; END",
            'second' => ["DROP TRIGGER {$prefix}tr3", "CREATE TRIGGER {$prefix}tr3 AFTER INSERT ON {$prefix}t7 BEGIN SELECT 1, 2, 3; END"],
            'watchTables' => [$prefix . 't7'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first, ?array $second) use ($prefix): void {
                $triggers = array_values(array_filter($catalog->schemaRecords('main'), static fn (SQLiteSchemaRecord $schemaRecord): bool => $schemaRecord->type === 'trigger' && $schemaRecord->name === $prefix . 'tr3'));
                $t->same('create_trigger', $first['ddl_plan']['operations'][0]['kind']);
                $t->same(['drop_trigger', 'create_trigger'], array_column($second['ddl_plan']['operations'], 'kind'));
                $t->contains('AFTER INSERT', (string) $triggers[0]->sql);
            },
        ],
        [
            'upstream' => 'schema3-1.*.22',
            'label' => 'create table then trigger with column list sees table',
            'setup' => [],
            'first' => "CREATE TABLE {$prefix}t8(a, b)",
            'second' => ["CREATE TRIGGER {$prefix}tr4 AFTER UPDATE OF a ON {$prefix}t8 BEGIN SELECT 1, 2, 3; END"],
            'watchTables' => [$prefix . 't8'],
            'assert' => static function (TestRunner $t, SQLiteAttachedSchemaCatalog $catalog, array $first, ?array $second) use ($recordNames, $prefix): void {
                $t->same('create_table', $first['ddl_plan']['operations'][0]['kind']);
                $t->same('create_trigger', $second['ddl_plan']['operations'][0]['kind']);
                $t->same(true, in_array($prefix . 'tr4', $recordNames($catalog, 'trigger'), true));
            },
        ],
    ];

    foreach ($schema3Cases as $case) {
        $watchIndexes = $case['watchIndexes'] ?? [];
        $tests['real upstream schema3 multiclient ' . $case['upstream'] . ' ' . $case['label'] . ' variant ' . $variant] = static function (TestRunner $t) use ($applySchema3, $case, $variant, $watchIndexes): void {
            [$catalog, $first, $second] = $applySchema3(
                $case['setup'],
                $case['first'],
                $case['second'],
                $variant,
                $case['watchTables'],
                $watchIndexes,
            );

            $t->same('schema_cache_expired', $first['status']);
            $t->same(false, $first['invalidation']['current']);
            $t->same(['schema3-prepared-' . $variant], $first['ddl_plan']['invalidated_prepared']);
            $case['assert']($t, $catalog, $first, $second);
        };
    }
}

return $tests;
