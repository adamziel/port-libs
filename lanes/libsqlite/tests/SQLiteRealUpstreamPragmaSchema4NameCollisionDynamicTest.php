<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$record = static fn (
    string $type,
    string $name,
    string $tableName,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $tableName, $rootPage, $sql, $rowId);

$baseRecords = static function (int $variant) use ($record): array {
    $suffix = sprintf('%03d', $variant);
    $target = 'app_events_' . $suffix;
    $log = 'app_event_log_' . $suffix;
    $sameTable = 'app_same_table_' . $suffix;
    $sameView = 'app_same_view_' . $suffix;
    $sameIndex = 'app_same_index_' . $suffix;

    return [
        $record('table', $log, $log, 10 + ($variant * 10), "CREATE TABLE {$log}(event_type TEXT, key_name TEXT, key_value TEXT)", 100 + ($variant * 10)),
        $record('table', $target, $target, 11 + ($variant * 10), "CREATE TABLE {$target}(key_name TEXT, key_value TEXT)", 101 + ($variant * 10)),
        $record('table', $sameTable, $sameTable, 12 + ($variant * 10), "CREATE TABLE {$sameTable}(key_name TEXT, key_value TEXT)", 102 + ($variant * 10)),
        $record('view', $sameView, $sameView, 0, "CREATE VIEW {$sameView} AS SELECT key_name, key_value FROM {$target}", 103 + ($variant * 10)),
        $record('index', $sameIndex, $target, 13 + ($variant * 10), "CREATE INDEX {$sameIndex} ON {$target}(key_name)", 104 + ($variant * 10)),
    ];
};

$names = static function (array $records, string $type): array {
    return array_values(array_map(
        static fn (SQLiteSchemaRecord $record): string => $record->name,
        array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === $type)),
    ));
};

$find = static function (array $records, string $type, string $name): ?SQLiteSchemaRecord {
    foreach ($records as $record) {
        if ($record->type === $type && strcasecmp($record->name, $name) === 0) {
            return $record;
        }
    }

    return null;
};

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%03d', $variant);
    $target = 'app_events_' . $suffix;
    $renamedTarget = 'app_events_renamed_' . $suffix;
    $log = 'app_event_log_' . $suffix;
    $sameTable = 'app_same_table_' . $suffix;
    $sameView = 'app_same_view_' . $suffix;
    $sameIndex = 'app_same_index_' . $suffix;
    $auditTrigger = 'app_audit_' . $suffix;

    $tests["real upstream schema4 dynamic {$suffix} allows trigger names shared with table view and index"] = static function (TestRunner $t) use ($baseRecords, $names, $variant, $target, $sameTable, $sameView, $sameIndex, $log): void {
        $result = SQLiteSchemaDdlReparsePlan::apply($baseRecords($variant), [
            "CREATE TRIGGER {$sameTable} AFTER INSERT ON {$target} BEGIN INSERT INTO {$log} VALUES('after insert', new.key_name, new.key_value); END",
            "CREATE TRIGGER {$sameView} AFTER UPDATE ON {$target} BEGIN INSERT INTO {$log} VALUES('after update', new.key_name, new.key_value); END",
            "CREATE TRIGGER {$sameIndex} AFTER DELETE ON {$target} BEGIN INSERT INTO {$log} VALUES('after delete', old.key_name, old.key_value); END",
        ], 10 + $variant);

        $t->same('ok', $result['status']);
        $t->same([105 + ($variant * 10), 106 + ($variant * 10), 107 + ($variant * 10)], array_column($result['operations'], 'rowid'));
        $t->same([$sameTable, $sameView, $sameIndex], $names($result['records'], 'trigger'));
        $t->same([$log, $target, $sameTable], $names($result['records'], 'table'));
        $t->same([$sameView], $names($result['records'], 'view'));
        $t->same([$sameIndex], $names($result['records'], 'index'));
    };

    $tests["real upstream schema4 dynamic {$suffix} dropping same-named objects preserves triggers"] = static function (TestRunner $t) use ($baseRecords, $find, $variant, $target, $sameTable, $sameView, $sameIndex, $log): void {
        $created = SQLiteSchemaDdlReparsePlan::apply($baseRecords($variant), [
            "CREATE TRIGGER {$sameTable} AFTER INSERT ON {$target} BEGIN INSERT INTO {$log} VALUES('after insert', new.key_name, new.key_value); END",
            "CREATE TRIGGER {$sameView} AFTER UPDATE ON {$target} BEGIN INSERT INTO {$log} VALUES('after update', new.key_name, new.key_value); END",
            "CREATE TRIGGER {$sameIndex} AFTER DELETE ON {$target} BEGIN INSERT INTO {$log} VALUES('after delete', old.key_name, old.key_value); END",
        ], 20 + $variant);
        $result = SQLiteSchemaDdlReparsePlan::apply($created['records'], [
            "DROP INDEX {$sameIndex}",
            "DROP TABLE {$sameTable}",
            "DROP VIEW {$sameView}",
        ], $created['after_schema_cookie']);

        $t->same('drop_index', $result['operations'][0]['kind']);
        $t->same('drop_table', $result['operations'][1]['kind']);
        $t->same('drop_view', $result['operations'][2]['kind']);
        $t->same(null, $find($result['records'], 'table', $sameTable));
        $t->same(null, $find($result['records'], 'view', $sameView));
        $t->same([$sameTable, $sameView, $sameIndex], array_map(static fn (SQLiteSchemaRecord $record): string => $record->name, array_values(array_filter($result['records'], static fn (SQLiteSchemaRecord $record): bool => $record->type === 'trigger'))));
    };

    $tests["real upstream schema4 dynamic {$suffix} rename unrelated table leaves same-named triggers runnable"] = static function (TestRunner $t) use ($baseRecords, $find, $variant, $target, $renamedTarget, $sameTable, $sameIndex, $log): void {
        $created = SQLiteSchemaDdlReparsePlan::apply($baseRecords($variant), [
            "CREATE TRIGGER {$sameTable} AFTER INSERT ON {$target} BEGIN INSERT INTO {$log} VALUES('after insert', new.key_name, new.key_value); END",
            "CREATE TRIGGER {$sameIndex} AFTER DELETE ON {$target} BEGIN INSERT INTO {$log} VALUES('after delete', old.key_name, old.key_value); END",
        ], 30 + $variant);
        $result = SQLiteSchemaDdlReparsePlan::apply($created['records'], [
            "ALTER TABLE {$sameTable} RENAME TO {$renamedTarget}",
        ], $created['after_schema_cookie']);

        $trigger = $find($result['records'], 'trigger', $sameTable);
        $renamed = $find($result['records'], 'table', $renamedTarget);

        $t->same('alter_table_rename', $result['operations'][0]['kind']);
        $t->same(['table:' . $sameTable], $result['operations'][0]['rewritten_records']);
        $t->same(0, $result['operations'][0]['dependent_reparse_count']);
        $t->same($sameTable, $trigger?->name);
        $t->same($target, $trigger?->tableName);
        $t->contains($renamedTarget, $renamed?->sql ?? '');
    };

    $tests["real upstream schema4 dynamic {$suffix} rename target table reparses indexes views and target triggers"] = static function (TestRunner $t) use ($baseRecords, $find, $variant, $target, $renamedTarget, $sameTable, $sameView, $sameIndex, $auditTrigger, $log): void {
        $created = SQLiteSchemaDdlReparsePlan::apply($baseRecords($variant), [
            "CREATE TRIGGER {$sameTable} AFTER INSERT ON {$target} BEGIN INSERT INTO {$log} VALUES('after insert', new.key_name, new.key_value); END",
            "CREATE TRIGGER {$auditTrigger} AFTER UPDATE ON {$target} BEGIN INSERT INTO {$log} VALUES('after update', new.key_name, new.key_value); END",
        ], 40 + $variant);
        $result = SQLiteSchemaDdlReparsePlan::apply($created['records'], [
            "ALTER TABLE {$target} RENAME TO {$renamedTarget}",
        ], $created['after_schema_cookie']);

        $catalog = new SQLitePragmaSchemaCatalog($result['records']);
        $sameNameTrigger = $find($result['records'], 'trigger', $sameTable);
        $audit = $find($result['records'], 'trigger', $auditTrigger);

        $t->same('alter_table_rename', $result['operations'][0]['kind']);
        $t->same(['table:' . $target, 'view:' . $sameView, 'index:' . $sameIndex, 'trigger:' . $sameTable, 'trigger:' . $auditTrigger], $result['operations'][0]['rewritten_records']);
        $t->same(4, $result['operations'][0]['dependent_reparse_count']);
        $t->same($renamedTarget, $sameNameTrigger?->tableName);
        $t->same($renamedTarget, $audit?->tableName);
        $t->same($sameIndex, $catalog->execute("PRAGMA index_list({$renamedTarget})")['rows'][0]['name']);
    };
}

$tests['real upstream schema4 dynamic cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'schema4.test schema4-1.1 through schema4-1.6 same trigger names as table/view/index survive object drops',
        'schema4.test schema4-2.1 through schema4-2.5 ALTER TABLE rename with triggers sharing table/index names',
        'schema4.test schema4-2.6 through schema4-2.11 TEMP trigger/table name collision remains independent after table rename',
    ];

    $t->same(3, count($sections));
    $t->contains('schema4.test', $sections[0]);
    $t->contains('schema4-2.1', $sections[1]);
    $t->contains('TEMP trigger/table name collision', $sections[2]);
};

return $tests;
