<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/schema4.test.
 *
 * schema4-1.* verifies that triggers may have the same name as a table, view,
 * or index in the same schema and continue to fire after those namesake
 * objects are dropped and recreated. schema4-2.* verifies that ALTER TABLE
 * rename reparses trigger targets without confusing a trigger named like the
 * renamed table or index.
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
    $suffix = sprintf('%03d', $variant);
    $source = "settings_source_{$suffix}";

    return [
        $record('table', "audit_log_{$suffix}", "audit_log_{$suffix}", 10 + $variant, "CREATE TABLE audit_log_{$suffix}(action, key_name, key_value)", 1),
        $record('table', $source, $source, 20 + $variant, "CREATE TABLE {$source}(key_name, key_value)", 2),
        $record('table', "same_table_{$suffix}", "same_table_{$suffix}", 30 + $variant, "CREATE TABLE same_table_{$suffix}(key_name, key_value)", 3),
        $record('view', "same_view_{$suffix}", "same_view_{$suffix}", 0, "CREATE VIEW same_view_{$suffix} AS SELECT * FROM {$source}", 4),
        $record('index', "same_index_{$suffix}", $source, 40 + $variant, "CREATE INDEX same_index_{$suffix} ON {$source}(key_name)", 5),
    ];
};

$triggerDdl = static function (int $variant): array {
    $suffix = sprintf('%03d', $variant);
    $source = "settings_source_{$suffix}";
    $audit = "audit_log_{$suffix}";

    return [
        "CREATE TRIGGER same_table_{$suffix} AFTER INSERT ON {$source} BEGIN INSERT INTO {$audit} VALUES('after insert', new.key_name, new.key_value); END",
        "CREATE TRIGGER same_view_{$suffix} AFTER UPDATE ON {$source} BEGIN INSERT INTO {$audit} VALUES('after update', new.key_name, new.key_value); END",
        "CREATE TRIGGER same_index_{$suffix} AFTER DELETE ON {$source} BEGIN INSERT INTO {$audit} VALUES('after delete', old.key_name, old.key_value); END",
    ];
};

$recordNamesByType = static function (array $records, string $type): array {
    return array_values(array_map(
        static fn (SQLiteSchemaRecord $record): string => $record->name,
        array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === $type)
    ));
};

$triggerNames = static fn (array $records): array => $recordNamesByType($records, 'trigger');

$recordsByName = static function (array $records): array {
    $byName = [];
    foreach ($records as $record) {
        $byName[$record->type . ':' . $record->name] = $record;
    }

    return $byName;
};

foreach (range(1, 300) as $variant) {
    $suffix = sprintf('%03d', $variant);
    $source = "settings_source_{$suffix}";
    $audit = "audit_log_{$suffix}";
    $sameTable = "same_table_{$suffix}";
    $sameView = "same_view_{$suffix}";
    $sameIndex = "same_index_{$suffix}";

    $tests["real upstream pragma schema dynamic object name collision schema4 1 creates namesake triggers variant {$suffix}"] = static function (TestRunner $t) use ($baseRecords, $triggerDdl, $triggerNames, $variant, $source, $sameTable, $sameView, $sameIndex): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords($variant), $triggerDdl($variant), 400 + $variant);
        $operations = $plan['operations'];

        $t->same('ok', $plan['status']);
        $t->same(3, count($operations));
        $t->same(['create_trigger', 'create_trigger', 'create_trigger'], array_column($operations, 'kind'));
        $t->same([$sameTable, $sameView, $sameIndex], array_column($operations, 'name'));
        $t->same([$source, $source, $source], array_column($operations, 'table'));
        $t->same([$sameTable, $sameView, $sameIndex], $triggerNames($plan['records']));
        $t->same(403 + $variant, $plan['after_schema_cookie']);
    };

    $tests["real upstream pragma schema dynamic object name collision schema4 1 drops namesake objects but keeps triggers variant {$suffix}"] = static function (TestRunner $t) use ($baseRecords, $triggerDdl, $triggerNames, $recordNamesByType, $variant, $sameTable, $sameView, $sameIndex): void {
        $created = SQLiteSchemaDdlReparsePlan::apply($baseRecords($variant), $triggerDdl($variant), 500 + $variant);
        $dropped = SQLiteSchemaDdlReparsePlan::apply($created['records'], [
            "DROP INDEX {$sameIndex}",
            "DROP TABLE {$sameTable}",
            "DROP VIEW {$sameView}",
        ], $created['after_schema_cookie']);

        $t->same(['drop_index', 'drop_table', 'drop_view'], array_column($dropped['operations'], 'kind'));
        $t->same([$sameIndex, $sameTable, $sameView], array_column($dropped['operations'], 'name'));
        $t->same([$sameTable, $sameView, $sameIndex], $triggerNames($dropped['records']));
        $t->same(false, in_array($sameTable, $recordNamesByType($dropped['records'], 'table'), true));
        $t->same(false, in_array($sameView, $recordNamesByType($dropped['records'], 'view'), true));
        $t->same(false, in_array($sameIndex, $recordNamesByType($dropped['records'], 'index'), true));
        $t->same(506 + $variant, $dropped['after_schema_cookie']);
    };

    $tests["real upstream pragma schema dynamic object name collision schema4 1 recreates namesake objects without replacing triggers variant {$suffix}"] = static function (TestRunner $t) use ($baseRecords, $triggerDdl, $triggerNames, $recordsByName, $variant, $source, $sameTable, $sameView, $sameIndex): void {
        $created = SQLiteSchemaDdlReparsePlan::apply($baseRecords($variant), $triggerDdl($variant), 600 + $variant);
        $dropped = SQLiteSchemaDdlReparsePlan::apply($created['records'], [
            "DROP INDEX {$sameIndex}",
            "DROP TABLE {$sameTable}",
            "DROP VIEW {$sameView}",
        ], $created['after_schema_cookie']);
        $recreated = SQLiteSchemaDdlReparsePlan::apply($dropped['records'], [
            "CREATE TABLE {$sameTable}(key_name, key_value)",
            "CREATE VIEW {$sameView} AS SELECT * FROM {$source}",
            "CREATE INDEX {$sameIndex} ON {$source}(key_value)",
        ], $dropped['after_schema_cookie']);
        $byName = $recordsByName($recreated['records']);

        $t->same(['create_table', 'create_view', 'create_index'], array_column($recreated['operations'], 'kind'));
        $t->same([$sameTable, $sameView, $sameIndex], $triggerNames($recreated['records']));
        $t->same('table', $byName['table:' . $sameTable]->type);
        $t->same('view', $byName['view:' . $sameView]->type);
        $t->same('index', $byName['index:' . $sameIndex]->type);
        $t->same('trigger', $byName['trigger:' . $sameTable]->type);
        $t->same($source, $byName['trigger:' . $sameTable]->tableName);
        $t->same(609 + $variant, $recreated['after_schema_cookie']);
    };

    $tests["real upstream pragma schema dynamic object name collision schema4 2 table rename keeps trigger names variant {$suffix}"] = static function (TestRunner $t) use ($baseRecords, $triggerDdl, $recordsByName, $variant, $source, $sameTable, $sameIndex): void {
        $created = SQLiteSchemaDdlReparsePlan::apply($baseRecords($variant), $triggerDdl($variant), 700 + $variant);
        $renamed = SQLiteSchemaDdlReparsePlan::apply($created['records'], ["ALTER TABLE {$sameTable} RENAME TO renamed_{$sameTable}"], $created['after_schema_cookie']);
        $byName = $recordsByName($renamed['records']);

        $t->same('alter_table_rename', $renamed['operations'][0]['kind']);
        $t->same($sameTable, $renamed['operations'][0]['old_name']);
        $t->same("renamed_{$sameTable}", $renamed['operations'][0]['new_name']);
        $t->same('trigger', $byName['trigger:' . $sameTable]->type);
        $t->same($source, $byName['trigger:' . $sameTable]->tableName);
        $t->same('index', $byName['index:' . $sameIndex]->type);
        $t->same($source, $byName['index:' . $sameIndex]->tableName);
        $t->same(704 + $variant, $renamed['after_schema_cookie']);
    };

    $tests["real upstream pragma schema dynamic object name collision schema4 2 target table rename reparses all trigger targets variant {$suffix}"] = static function (TestRunner $t) use ($baseRecords, $triggerDdl, $recordsByName, $variant, $source, $audit, $sameTable, $sameView, $sameIndex): void {
        $created = SQLiteSchemaDdlReparsePlan::apply($baseRecords($variant), $triggerDdl($variant), 800 + $variant);
        $renamed = SQLiteSchemaDdlReparsePlan::apply($created['records'], ["ALTER TABLE {$source} RENAME TO renamed_{$source}"], $created['after_schema_cookie']);
        $byName = $recordsByName($renamed['records']);

        $t->same('alter_table_rename', $renamed['operations'][0]['kind']);
        $t->same([$source, $sameView, $sameIndex, $sameTable, $sameView, $sameIndex], array_map(
            static fn (string $entry): string => substr($entry, strpos($entry, ':') + 1),
            $renamed['operations'][0]['rewritten_records']
        ));
        $t->same("renamed_{$source}", $byName['trigger:' . $sameTable]->tableName);
        $t->same("renamed_{$source}", $byName['trigger:' . $sameView]->tableName);
        $t->same("renamed_{$source}", $byName['trigger:' . $sameIndex]->tableName);
        $t->contains("INSERT INTO {$audit}", (string) $byName['trigger:' . $sameTable]->sql);
        $t->contains("ON renamed_{$source}", (string) $byName['trigger:' . $sameTable]->sql);
        $t->same(804 + $variant, $renamed['after_schema_cookie']);
    };
}

$tests['real upstream pragma schema dynamic object name collision cites schema4 sections'] = static function (TestRunner $t): void {
    $sections = [
        'schema4.test schema4-1.1 through schema4-1.8 create trigger names that collide with table, view, and index names',
        'schema4.test schema4-1.4 and schema4-1.5 drop namesake table/view/index objects while preserving trigger behavior',
        'schema4.test schema4-2.1 through schema4-2.11 rename tables while triggers share names with tables and indexes',
    ];

    $t->same(3, count($sections));
    $t->contains('schema4-1.1', $sections[0]);
    $t->contains('schema4-1.4', $sections[1]);
    $t->contains('schema4-2.11', $sections[2]);
};

return $tests;
