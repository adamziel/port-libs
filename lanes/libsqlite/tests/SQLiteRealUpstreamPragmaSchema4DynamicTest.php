<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$recordsFor = static function (int $variant): array {
    $log = 'app_schema4_log_' . $variant;
    $target = 'app_schema4_target_' . $variant;
    $shadowTable = 'app_schema4_shadow_' . $variant;
    $shadowView = 'app_schema4_view_' . $variant;
    $shadowIndex = 'app_schema4_index_' . $variant;
    $renameSource = 'app_schema4_rename_' . $variant;
    $renameIndex = 'app_schema4_rename_index_' . $variant;

    return [
        new SQLiteSchemaRecord('table', $log, $log, 2, "CREATE TABLE {$log}(event_name TEXT, key_name TEXT, key_value TEXT)", 1),
        new SQLiteSchemaRecord('table', $target, $target, 3, "CREATE TABLE {$target}(key_name TEXT, key_value TEXT)", 2),
        new SQLiteSchemaRecord('table', $shadowTable, $shadowTable, 4, "CREATE TABLE {$shadowTable}(a TEXT, b TEXT)", 3),
        new SQLiteSchemaRecord('view', $shadowView, $shadowView, 0, "CREATE VIEW {$shadowView} AS SELECT key_name, key_value FROM {$target}", 4),
        new SQLiteSchemaRecord('index', $shadowIndex, $target, 5, "CREATE INDEX {$shadowIndex} ON {$target}(key_name)", 5),
        new SQLiteSchemaRecord('trigger', $shadowTable, $target, 0, "CREATE TRIGGER {$shadowTable} AFTER INSERT ON {$target} BEGIN INSERT INTO {$log} VALUES('after insert', new.key_name, new.key_value); END", 6),
        new SQLiteSchemaRecord('trigger', $shadowView, $target, 0, "CREATE TRIGGER {$shadowView} AFTER UPDATE ON {$target} BEGIN INSERT INTO {$log} VALUES('after update', new.key_name, new.key_value); END", 7),
        new SQLiteSchemaRecord('trigger', $shadowIndex, $target, 0, "CREATE TRIGGER {$shadowIndex} AFTER DELETE ON {$target} BEGIN INSERT INTO {$log} VALUES('after delete', old.key_name, old.key_value); END", 8),
        new SQLiteSchemaRecord('table', $renameSource, $renameSource, 6, "CREATE TABLE {$renameSource}(key_name TEXT, key_value TEXT)", 9),
        new SQLiteSchemaRecord('index', $renameIndex, $renameSource, 7, "CREATE INDEX {$renameIndex} ON {$renameSource}(key_name, key_value)", 10),
        new SQLiteSchemaRecord('trigger', $renameSource, $target, 0, "CREATE TRIGGER {$renameSource} AFTER INSERT ON {$target} BEGIN INSERT INTO {$log} VALUES('after insert rename', new.key_name, new.key_value); END", 11),
        new SQLiteSchemaRecord('trigger', $renameIndex, $target, 0, "CREATE TRIGGER {$renameIndex} AFTER DELETE ON {$target} BEGIN INSERT INTO {$log} VALUES('after delete rename', old.key_name, old.key_value); END", 12),
    ];
};

$recordKeyed = static function (array $records): array {
    $keyed = [];
    foreach ($records as $record) {
        $keyed[$record->type . ':' . $record->name] = $record;
    }

    return $keyed;
};

foreach (range(1, 250) as $variant) {
    $target = 'app_schema4_target_' . $variant;
    $shadowTable = 'app_schema4_shadow_' . $variant;
    $shadowView = 'app_schema4_view_' . $variant;
    $shadowIndex = 'app_schema4_index_' . $variant;
    $renameSource = 'app_schema4_rename_' . $variant;
    $renameIndex = 'app_schema4_rename_index_' . $variant;
    $renameDest = 'app_schema4_renamed_' . $variant;
    $targetDest = 'app_schema4_target_renamed_' . $variant;

    $tests["real upstream schema4 1 same-name drops preserve table-named trigger {$variant}"] = static function (TestRunner $t) use ($recordsFor, $recordKeyed, $variant, $shadowTable, $target): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $recordsFor($variant),
            ["DROP TABLE {$shadowTable}"],
            7000 + $variant,
        );
        $records = $recordKeyed($plan['records']);

        $t->same('drop_table', $plan['operations'][0]['kind']);
        $t->same(true, in_array('table:' . $shadowTable, $plan['operations'][0]['removed_records'], true));
        $t->same(false, array_key_exists('table:' . $shadowTable, $records));
        $t->same(true, array_key_exists('trigger:' . $shadowTable, $records));
        $t->same($target, $records['trigger:' . $shadowTable]->tableName);
        $t->same(7001 + $variant, $plan['after_schema_cookie']);
    };

    $tests["real upstream schema4 1 same-name drops preserve view and index named triggers {$variant}"] = static function (TestRunner $t) use ($recordsFor, $recordKeyed, $variant, $shadowView, $shadowIndex, $target): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $recordsFor($variant),
            ["DROP VIEW {$shadowView}", "DROP INDEX {$shadowIndex}"],
            8000 + $variant,
        );
        $records = $recordKeyed($plan['records']);

        $t->same(['drop_view', 'drop_index'], array_column($plan['operations'], 'kind'));
        $t->same(false, array_key_exists('view:' . $shadowView, $records));
        $t->same(false, array_key_exists('index:' . $shadowIndex, $records));
        $t->same(true, array_key_exists('trigger:' . $shadowView, $records));
        $t->same(true, array_key_exists('trigger:' . $shadowIndex, $records));
        $t->same([$target, $target], [$records['trigger:' . $shadowView]->tableName, $records['trigger:' . $shadowIndex]->tableName]);
        $t->same(8002 + $variant, $plan['after_schema_cookie']);
    };

    $tests["real upstream schema4 2 rename source table leaves same-name triggers on target {$variant}"] = static function (TestRunner $t) use ($recordsFor, $recordKeyed, $variant, $renameSource, $renameDest, $renameIndex, $target): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $recordsFor($variant),
            ["ALTER TABLE {$renameSource} RENAME TO {$renameDest}"],
            9000 + $variant,
        );
        $records = $recordKeyed($plan['records']);

        $t->same('alter_table_rename', $plan['operations'][0]['kind']);
        $t->same(['table:' . $renameSource, 'index:' . $renameIndex], $plan['operations'][0]['rewritten_records']);
        $t->same(false, array_key_exists('table:' . $renameSource, $records));
        $t->same(true, array_key_exists('table:' . $renameDest, $records));
        $t->same($renameDest, $records['index:' . $renameIndex]->tableName);
        $t->same($target, $records['trigger:' . $renameSource]->tableName);
        $t->same($target, $records['trigger:' . $renameIndex]->tableName);
    };

    $tests["real upstream schema4 2 rename trigger target rewrites trigger SQL not same-name temp table {$variant}"] = static function (TestRunner $t) use ($recordsFor, $recordKeyed, $variant, $target, $targetDest, $shadowTable, $shadowView, $shadowIndex): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $recordsFor($variant),
            ["ALTER TABLE {$target} RENAME TO {$targetDest}"],
            10000 + $variant,
        );
        $records = $recordKeyed($plan['records']);
        $rewritten = $plan['operations'][0]['rewritten_records'];

        $t->same('alter_table_rename', $plan['operations'][0]['kind']);
        $t->same(true, in_array('table:' . $target, $rewritten, true));
        $t->same(true, in_array('trigger:' . $shadowTable, $rewritten, true));
        $t->same(true, in_array('trigger:' . $shadowView, $rewritten, true));
        $t->same(true, in_array('trigger:' . $shadowIndex, $rewritten, true));
        $t->same($targetDest, $records['trigger:' . $shadowTable]->tableName);
        $t->same(true, str_contains((string) $records['trigger:' . $shadowTable]->sql, " ON {$targetDest} "));
        $t->same(true, array_key_exists('table:' . $shadowTable, $records));
    };
}

return $tests;
