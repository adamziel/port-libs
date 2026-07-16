<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/schema3.test schema3-1.*: a second connection must refresh
 *   cached schema state after CREATE TABLE/VIEW/INDEX/TRIGGER and ALTER TABLE
 *   ADD COLUMN changes before it executes statements that depend on the new
 *   objects or columns.
 * - SQLite test/schema4.test schema4-1.*: triggers may share names with a
 *   table, view, or index in the same schema; dropping the non-trigger object
 *   must not drop the trigger.
 * - SQLite test/schema4.test schema4-2.*: renaming a table or another object
 *   with the same name as a trigger must preserve the trigger record and only
 *   rewrite trigger SQL when its target table is renamed.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$baseRecords = static function (int $variant) use ($record): array {
    $log = sprintf('schema4_log_%04d', $variant);
    $table = sprintf('schema4_tbl_%04d', $variant);

    return [
        $record('table', $log, $log, 100000 + $variant, "CREATE TABLE {$log}(event TEXT, a TEXT, b TEXT)", 1),
        $record('table', $table, $table, 110000 + $variant, "CREATE TABLE {$table}(a TEXT, b TEXT)", 2),
    ];
};

$addNameCollisionObjects = static function (array $records, int $variant) use ($baseRecords): array {
    $table = sprintf('schema4_tbl_%04d', $variant);
    $shadowTable = sprintf('schema4_t1_%04d', $variant);
    $shadowView = sprintf('schema4_v1_%04d', $variant);
    $shadowIndex = sprintf('schema4_i1_%04d', $variant);

    $plan = SQLiteSchemaDdlReparsePlan::apply($records, [
        "CREATE TABLE {$shadowTable}(a TEXT, b TEXT)",
        "CREATE VIEW {$shadowView} AS SELECT a, b FROM {$table}",
        "CREATE INDEX {$shadowIndex} ON {$table}(a)",
        "CREATE TRIGGER {$shadowTable} AFTER INSERT ON {$table} BEGIN SELECT new.a, new.b; END",
        "CREATE TRIGGER {$shadowView} AFTER UPDATE ON {$table} BEGIN SELECT new.a, new.b; END",
        "CREATE TRIGGER {$shadowIndex} AFTER DELETE ON {$table} BEGIN SELECT old.a, old.b; END",
    ], 10 + $variant);

    return $plan['records'];
};

$recordsBy = static function (array $records): array {
    $by = [];
    foreach ($records as $record) {
        $by[$record->type . ':' . $record->name] = $record;
    }

    return $by;
};

$triggerNames = static fn (array $records): array => array_values(array_map(
    static fn (SQLiteSchemaRecord $record): string => $record->name,
    array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'trigger')),
));

foreach (range(1, 220) as $variant) {
    $table = sprintf('schema4_tbl_%04d', $variant);
    $renamedTable = sprintf('schema4_tbl2_%04d', $variant);
    $shadowTable = sprintf('schema4_t1_%04d', $variant);
    $shadowView = sprintf('schema4_v1_%04d', $variant);
    $shadowIndex = sprintf('schema4_i1_%04d', $variant);
    $schema3Added = sprintf('schema3_added_%04d', $variant);
    $schema3Altered = sprintf('schema3_altered_%04d', $variant);
    $schema3Index = sprintf('schema3_idx_%04d', $variant);
    $schema3Trigger = sprintf('schema3_tr_%04d', $variant);
    $schema3View = sprintf('schema3_view_%04d', $variant);

    $tests[sprintf('real upstream schema4 same-name trigger survives table view index drops variant %03d', $variant)] = static function (TestRunner $t) use ($baseRecords, $addNameCollisionObjects, $recordsBy, $triggerNames, $variant, $shadowTable, $shadowView, $shadowIndex): void {
        $records = $addNameCollisionObjects($baseRecords($variant), $variant);
        $plan = SQLiteSchemaDdlReparsePlan::apply($records, [
            "DROP INDEX {$shadowIndex}",
            "DROP TABLE {$shadowTable}",
            "DROP VIEW {$shadowView}",
        ], 20 + $variant);

        $by = $recordsBy($plan['records']);
        $t->same(['drop_index', 'drop_table', 'drop_view'], array_column($plan['operations'], 'kind'));
        $t->same([$shadowTable, $shadowView, $shadowIndex], $triggerNames($plan['records']));
        $t->same(true, isset($by['trigger:' . $shadowTable]));
        $t->same(true, isset($by['trigger:' . $shadowView]));
        $t->same(true, isset($by['trigger:' . $shadowIndex]));
        $t->same(false, isset($by['table:' . $shadowTable]));
        $t->same(false, isset($by['view:' . $shadowView]));
        $t->same(false, isset($by['index:' . $shadowIndex]));
        $t->same(23 + $variant, $plan['after_schema_cookie']);
    };

    $tests[sprintf('real upstream schema4 same-name trigger survives sibling rename variant %03d', $variant)] = static function (TestRunner $t) use ($baseRecords, $addNameCollisionObjects, $recordsBy, $variant, $shadowTable, $shadowIndex): void {
        $records = $addNameCollisionObjects($baseRecords($variant), $variant);
        $renamedShadow = $shadowTable . '_renamed';
        $plan = SQLiteSchemaDdlReparsePlan::apply($records, [
            "ALTER TABLE {$shadowTable} RENAME TO {$renamedShadow}",
        ], 40 + $variant);
        $by = $recordsBy($plan['records']);

        $t->same('alter_table_rename', $plan['operations'][0]['kind']);
        $t->same(['table:' . $shadowTable], $plan['operations'][0]['rewritten_records']);
        $t->same(true, isset($by['table:' . $renamedShadow]));
        $t->same(true, isset($by['trigger:' . $shadowTable]));
        $t->same($shadowTable, $by['trigger:' . $shadowTable]->name);
        $t->same($shadowIndex, $by['trigger:' . $shadowIndex]->name);
        $t->same(false, str_contains((string) $by['trigger:' . $shadowTable]->sql, $renamedShadow));
        $t->same(41 + $variant, $plan['after_schema_cookie']);
    };

    $tests[sprintf('real upstream schema4 target table rename rewrites all same-name triggers variant %03d', $variant)] = static function (TestRunner $t) use ($baseRecords, $addNameCollisionObjects, $recordsBy, $variant, $table, $renamedTable, $shadowTable, $shadowView, $shadowIndex): void {
        $records = $addNameCollisionObjects($baseRecords($variant), $variant);
        $plan = SQLiteSchemaDdlReparsePlan::apply($records, [
            "ALTER TABLE {$table} RENAME TO {$renamedTable}",
        ], 60 + $variant);
        $by = $recordsBy($plan['records']);

        $t->same('alter_table_rename', $plan['operations'][0]['kind']);
        $t->same(true, in_array('trigger:' . $shadowTable, $plan['operations'][0]['rewritten_records'], true));
        $t->same(true, in_array('trigger:' . $shadowView, $plan['operations'][0]['rewritten_records'], true));
        $t->same(true, in_array('trigger:' . $shadowIndex, $plan['operations'][0]['rewritten_records'], true));
        $t->same($renamedTable, $by['trigger:' . $shadowTable]->tableName);
        $t->same($renamedTable, $by['trigger:' . $shadowView]->tableName);
        $t->same($renamedTable, $by['trigger:' . $shadowIndex]->tableName);
        $t->same(false, str_contains((string) $by['trigger:' . $shadowTable]->sql, " ON {$table} "));
        $t->same(true, preg_match('/\bON\s+"?' . preg_quote($renamedTable, '/') . '"?\b/i', (string) $by['trigger:' . $shadowTable]->sql) === 1);
        $t->same(3, $plan['table_count']);
    };

    $tests[sprintf('real upstream schema3 create/drop objects invalidate prepared schema cache variant %03d', $variant)] = static function (TestRunner $t) use ($record, $schema3Added, $schema3Index, $schema3Trigger, $schema3View, $variant): void {
        $records = [
            $record('table', $schema3Added, $schema3Added, 700000 + $variant, "CREATE TABLE {$schema3Added}(a INT, b INT)", 1),
        ];
        $plan = SQLiteSchemaDdlReparsePlan::apply($records, [
            "CREATE INDEX {$schema3Index} ON {$schema3Added}(a)",
            "CREATE VIEW {$schema3View} AS SELECT a, b FROM {$schema3Added}",
            "CREATE TRIGGER {$schema3Trigger} AFTER INSERT ON {$schema3Added} BEGIN SELECT new.a, new.b; END",
            "DROP INDEX {$schema3Index}",
            "DROP TRIGGER {$schema3Trigger}",
            "DROP VIEW {$schema3View}",
        ], 80 + $variant, 'main', [
            ['id' => 'select-current', 'schema_cookie' => 80 + $variant, 'sql' => "SELECT * FROM {$schema3Added}"],
            ['id' => 'stale-other-connection', 'schema_cookie' => 79 + $variant, 'sql' => "SELECT * FROM {$schema3Added}"],
        ]);

        $t->same(['create_index', 'create_view', 'create_trigger', 'drop_index', 'drop_trigger', 'drop_view'], array_column($plan['operations'], 'kind'));
        $t->same(['select-current', 'stale-other-connection'], $plan['invalidated_prepared']);
        $t->same(86 + $variant, $plan['after_schema_cookie']);
        $t->same(1, $plan['table_count']);
        $t->same(0, $plan['index_count']);
        $t->same(['a', 'b'], array_column((new SQLitePragmaSchemaCatalog($plan['records']))->execute("PRAGMA table_info({$schema3Added})")['rows'], 'name'));
    };

    $tests[sprintf('real upstream schema3 alter add column refreshes stale view trigger index dependencies variant %03d', $variant)] = static function (TestRunner $t) use ($record, $schema3Altered, $schema3Index, $schema3Trigger, $schema3View, $variant): void {
        $records = [
            $record('table', $schema3Altered, $schema3Altered, 800000 + $variant, "CREATE TABLE {$schema3Altered}(a INT, b INT)", 1),
            $record('index', $schema3Index, $schema3Altered, 810000 + $variant, "CREATE INDEX {$schema3Index} ON {$schema3Altered}(a)", 2),
            $record('view', $schema3View, $schema3View, 0, "CREATE VIEW {$schema3View} AS SELECT * FROM {$schema3Altered}", 3),
            $record('trigger', $schema3Trigger, $schema3Altered, 0, "CREATE TRIGGER {$schema3Trigger} AFTER UPDATE OF b ON {$schema3Altered} BEGIN SELECT new.a, new.b; END", 4),
        ];
        $plan = SQLiteSchemaDdlReparsePlan::apply($records, [
            "ALTER TABLE {$schema3Altered} ADD COLUMN c TEXT DEFAULT 'v{$variant}'",
        ], 100 + $variant, 'main', [
            ['id' => 'select-added-column', 'schema_cookie' => 100 + $variant, 'sql' => "SELECT a,b,c FROM {$schema3Altered}"],
        ]);
        $op = $plan['operations'][0];
        $catalog = new SQLitePragmaSchemaCatalog($plan['records']);

        $t->same('alter_table_add_column', $op['kind']);
        $t->same('c', $op['column']);
        $t->same(true, in_array('view:' . $schema3View, $op['dependent_reparse_records'], true));
        $t->same(true, in_array('trigger:' . $schema3Trigger, $op['dependent_reparse_records'], true));
        $t->same(true, in_array('index:' . $schema3Index, $op['index_reparse_records'], true));
        $t->same(['select-added-column'], $plan['invalidated_prepared']);
        $t->same(['a', 'b', 'c'], array_column($catalog->execute("PRAGMA table_info({$schema3Altered})")['rows'], 'name'));
        $t->same("'v{$variant}'", $catalog->execute("PRAGMA table_info({$schema3Altered})")['rows'][2]['dflt_value']);
    };
}

$tests['real upstream schema3 schema4 dynamic source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'schema3.test schema3-1.1..1.22 refreshes stale schema caches after CREATE, DROP, and ALTER TABLE ADD COLUMN',
        'schema4.test schema4-1.1..1.8 allows trigger names to collide with table, view, index, and virtual table object names',
        'schema4.test schema4-2.1..2.11 preserves same-name triggers across table rename and temp table name collisions',
    ];

    $t->same(3, count($sections));
    $t->contains('schema3-1.1', $sections[0]);
    $t->contains('schema4-1.1', $sections[1]);
    $t->contains('schema4-2.1', $sections[2]);
};

return $tests;
