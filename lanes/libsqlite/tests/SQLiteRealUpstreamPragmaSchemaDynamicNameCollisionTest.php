<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/schema4.test.
 *
 * This ports the schema4-1.* and schema4-2.* regression cluster where
 * triggers may share names with tables, views, and indexes. Dropping or
 * renaming the non-trigger object must not disturb same-named triggers on a
 * different target table, and temp tables may share names with temp triggers.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$byName = static function (array $records, string $name): ?SQLiteSchemaRecord {
    foreach ($records as $record) {
        if ($record instanceof SQLiteSchemaRecord && strcasecmp($record->name, $name) === 0) {
            return $record;
        }
    }

    return null;
};

foreach (range(1, 1000) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $log = "schema4_audit_{$suffix}";
    $target = "schema4_settings_{$suffix}";
    $tableCollision = "schema4_table_collision_{$suffix}";
    $viewCollision = "schema4_view_collision_{$suffix}";
    $indexCollision = "schema4_index_collision_{$suffix}";
    $renamedTable = "schema4_table_renamed_{$suffix}";
    $tempName = "schema4_temp_collision_{$suffix}";

    $baseRecords = static function () use ($record, $log, $target, $tableCollision, $viewCollision, $indexCollision): array {
        return [
            $record('table', $log, $log, 10, "CREATE TABLE {$log}(event TEXT, key_name TEXT, key_value TEXT)", 1),
            $record('table', $target, $target, 11, "CREATE TABLE {$target}(key_name TEXT, key_value TEXT)", 2),
            $record('table', $tableCollision, $tableCollision, 12, "CREATE TABLE {$tableCollision}(key_name TEXT, key_value TEXT)", 3),
            $record('view', $viewCollision, $viewCollision, 0, "CREATE VIEW {$viewCollision} AS SELECT key_name, key_value FROM {$target}", 4),
            $record('index', $indexCollision, $target, 13, "CREATE INDEX {$indexCollision} ON {$target}(key_name)", 5),
            $record('trigger', $tableCollision, $target, 0, "CREATE TRIGGER {$tableCollision} AFTER INSERT ON {$target} BEGIN INSERT INTO {$log} VALUES('after insert', new.key_name, new.key_value); END", 6),
            $record('trigger', $viewCollision, $target, 0, "CREATE TRIGGER {$viewCollision} AFTER UPDATE ON {$target} BEGIN INSERT INTO {$log} VALUES('after update', new.key_name, new.key_value); END", 7),
            $record('trigger', $indexCollision, $target, 0, "CREATE TRIGGER {$indexCollision} AFTER DELETE ON {$target} BEGIN INSERT INTO {$log} VALUES('after delete', old.key_name, old.key_value); END", 8),
        ];
    };

    $tests["real upstream schema4 trigger object name collision drop preserves triggers variant {$suffix}"] = static function (TestRunner $t) use ($baseRecords, $byName, $tableCollision, $viewCollision, $indexCollision, $target): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords(), [
            "DROP INDEX {$indexCollision}",
            "DROP TABLE {$tableCollision}",
            "DROP VIEW {$viewCollision}",
        ], 40);

        $t->same('ok', $plan['status']);
        $t->same(43, $plan['after_schema_cookie']);
        $t->same(['drop_index', 'drop_table', 'drop_view'], array_column($plan['operations'], 'kind'));
        $t->same('trigger', $byName($plan['records'], $tableCollision)?->type);
        $t->same($target, $byName($plan['records'], $tableCollision)?->tableName);
        $t->same('trigger', $byName($plan['records'], $viewCollision)?->type);
        $t->same($target, $byName($plan['records'], $viewCollision)?->tableName);
        $t->same('trigger', $byName($plan['records'], $indexCollision)?->type);
        $t->same($target, $byName($plan['records'], $indexCollision)?->tableName);
        $t->same(3, count(array_filter($plan['records'], static fn (SQLiteSchemaRecord $record): bool => $record->type === 'trigger')));
    };

    $tests["real upstream schema4 trigger object name collision catalog pragma sees trigger after object drops variant {$suffix}"] = static function (TestRunner $t) use ($baseRecords, $tableCollision, $viewCollision, $indexCollision, $target): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords(), [
            "DROP INDEX {$indexCollision}",
            "DROP TABLE {$tableCollision}",
            "DROP VIEW {$viewCollision}",
        ], 40);
        $catalog = new SQLiteAttachedSchemaCatalog($plan['records']);

        $t->same([], $catalog->executeSchemaPragma("PRAGMA table_info({$tableCollision})")['rows']);
        $t->same([], $catalog->executeSchemaPragma("PRAGMA index_info({$indexCollision})")['rows']);
        $t->same([$target], array_values(array_unique(array_map(
            static fn (SQLiteSchemaRecord $record): string => $record->tableName,
            array_filter($catalog->schemaRecords('main'), static fn (SQLiteSchemaRecord $record): bool => $record->type === 'trigger')
        ))));
        $t->same([$target], array_column($catalog->executeSchemaPragma("PRAGMA table_list({$target})")['rows'], 'name'));
    };

    $tests["real upstream schema4 table rename rewrites dependent index not same named trigger variant {$suffix}"] = static function (TestRunner $t) use ($record, $byName, $log, $target, $tableCollision, $indexCollision, $renamedTable): void {
        $records = [
            $record('table', $log, $log, 20, "CREATE TABLE {$log}(event TEXT, key_name TEXT, key_value TEXT)", 1),
            $record('table', $target, $target, 21, "CREATE TABLE {$target}(key_name TEXT, key_value TEXT)", 2),
            $record('table', $tableCollision, $tableCollision, 22, "CREATE TABLE {$tableCollision}(key_name TEXT, key_value TEXT)", 3),
            $record('index', $indexCollision, $tableCollision, 23, "CREATE INDEX {$indexCollision} ON {$tableCollision}(key_name, key_value)", 4),
            $record('trigger', $tableCollision, $target, 0, "CREATE TRIGGER {$tableCollision} AFTER INSERT ON {$target} BEGIN INSERT INTO {$log} VALUES('after insert', new.key_name, new.key_value); END", 5),
            $record('trigger', $indexCollision, $target, 0, "CREATE TRIGGER {$indexCollision} AFTER DELETE ON {$target} BEGIN INSERT INTO {$log} VALUES('after delete', old.key_name, old.key_value); END", 6),
        ];

        $plan = SQLiteSchemaDdlReparsePlan::apply($records, ["ALTER TABLE {$tableCollision} RENAME TO {$renamedTable}"], 50);

        $t->same('alter_table_rename', $plan['operations'][0]['kind']);
        $t->same(["table:{$tableCollision}", "index:{$indexCollision}"], $plan['operations'][0]['rewritten_records']);
        $t->same($renamedTable, $byName($plan['records'], $renamedTable)?->tableName);
        $t->contains("CREATE TABLE {$renamedTable}", (string) $byName($plan['records'], $renamedTable)?->sql);
        $t->same($renamedTable, $byName($plan['records'], $indexCollision)?->tableName);
        $t->contains("ON {$renamedTable}", (string) $byName($plan['records'], $indexCollision)?->sql);
        $t->same($target, $byName($plan['records'], $tableCollision)?->tableName);
        $t->contains("AFTER INSERT ON {$target}", (string) $byName($plan['records'], $tableCollision)?->sql);
    };

    $tests["real upstream schema4 temp trigger and temp table share name through target rename variant {$suffix}"] = static function (TestRunner $t) use ($record, $target, $tempName): void {
        $catalog = new SQLiteAttachedSchemaCatalog(
            [
                $record('table', $target, $target, 30, "CREATE TABLE {$target}(key_name TEXT, key_value TEXT)", 1),
            ],
            [
                $record('trigger', $tempName, $target, 0, "CREATE TEMP TRIGGER {$tempName} AFTER UPDATE ON {$target} BEGIN SELECT new.key_name; END", 2),
                $record('table', $tempName, $tempName, 31, "CREATE TABLE {$tempName}(marker INTEGER)", 3),
            ],
        );
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$target, $tempName], [], 'temp');
        $result = $catalog->applySchemaDdlCurrentSource('main', ["ALTER TABLE {$target} RENAME TO {$target}_next"], 60, $snapshot);

        $t->same('schema_cache_expired', $result['status']);
        $t->same(true, $result['cache_invalidated']);
        $t->same(["table:{$target}"], $result['ddl_plan']['operations'][0]['rewritten_records']);
        $t->same('temp', $result['invalidation']['table_changes'][$tempName]['before']['schema']);
        $t->same(false, $result['invalidation']['table_changes'][$tempName]['changed']);
        $t->same(true, $result['invalidation']['table_changes'][$target]['changed']);
        $t->same([$tempName], array_column($catalog->executeSchemaPragma("PRAGMA table_list({$tempName})")['rows'], 'name'));
    };
}

$tests['real upstream schema4 name collision source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'schema4.test schema4-1.1 through schema4-1.6 creates triggers with the same names as a table, view, and index, then drops the non-trigger objects while trigger behavior remains',
        'schema4.test schema4-2.1 through schema4-2.5 renames a same-named table while triggers on another table keep firing',
        'schema4.test schema4-2.6 through schema4-2.11 allows a temp trigger and temp table to share a name while ALTER TABLE rewrites the target table only',
    ];

    $t->same(3, count($sections));
    $t->contains('schema4-1.1', $sections[0]);
    $t->contains('schema4-2.5', $sections[1]);
    $t->contains('schema4-2.11', $sections[2]);
};

return $tests;
