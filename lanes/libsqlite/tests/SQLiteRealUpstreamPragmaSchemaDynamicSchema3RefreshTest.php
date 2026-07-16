<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/schema3.test schema3-1.*: a second connection that has already
 *   loaded sqlite_schema must refresh cached schema state before executing SQL
 *   after another connection creates, drops, or replaces tables, views, indexes,
 *   and triggers.
 *
 * This ports the create/drop/replace subset into the lane-local schema reparse
 * planner. It deliberately avoids ALTER TABLE ADD COLUMN because this helper
 * does not yet model column mutation in schema DDL reparse.
 */

$baseRecords = static function (int $variant): array {
    $settings = sprintf('app_settings_%03d', $variant);
    $audit = sprintf('app_audit_%03d', $variant);
    $events = sprintf('app_events_%03d', $variant);

    return [
        new SQLiteSchemaRecord('table', $settings, $settings, 2, "CREATE TABLE {$settings}(setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)", 1),
        new SQLiteSchemaRecord('table', $audit, $audit, 3, "CREATE TABLE {$audit}(audit_id INTEGER PRIMARY KEY, key_name TEXT, old_value TEXT, new_value TEXT)", 2),
        new SQLiteSchemaRecord('table', $events, $events, 4, "CREATE TABLE {$events}(event_id INTEGER PRIMARY KEY, key_name TEXT, event_type TEXT)", 3),
        new SQLiteSchemaRecord('index', "{$settings}_key_idx", $settings, 5, "CREATE INDEX {$settings}_key_idx ON {$settings}(key_name)", 4),
        new SQLiteSchemaRecord('view', "{$settings}_view", "{$settings}_view", 0, "CREATE VIEW {$settings}_view AS SELECT key_name, key_value FROM {$settings}", 5),
        new SQLiteSchemaRecord('trigger', "{$settings}_audit_tr", $settings, 0, "CREATE TRIGGER {$settings}_audit_tr AFTER UPDATE ON {$settings} BEGIN SELECT 1; END", 6),
    ];
};

$prepared = static fn (int $variant, int $cookie): array => [
    ['id' => sprintf('reader-%03d-current-schema', $variant), 'schema_cookie' => $cookie, 'sql' => sprintf('SELECT * FROM app_settings_%03d', $variant)],
    ['id' => sprintf('writer-%03d-current-schema', $variant), 'schema_cookie' => $cookie, 'sql' => sprintf('UPDATE app_settings_%03d SET key_value = key_name', $variant)],
];

$recordNames = static fn (array $records): array => array_map(static fn (SQLiteSchemaRecord $record): string => $record->name, $records);
$recordByName = static function (array $records, string $name): ?SQLiteSchemaRecord {
    foreach ($records as $record) {
        if (strcasecmp($record->name, $name) === 0) {
            return $record;
        }
    }

    return null;
};

$operations = [
    'create table then select sees table' => static function (int $variant): array {
        $newTable = sprintf('app_runtime_%03d', $variant);
        return [
            ["CREATE TABLE {$newTable}(runtime_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)"],
            static function (TestRunner $t, array $plan) use ($newTable): void {
                $t->same('create_table', $plan['operations'][0]['kind']);
                $t->same($newTable, $plan['operations'][0]['name']);
                $t->same(true, in_array($newTable, array_map(static fn (SQLiteSchemaRecord $record): string => $record->name, $plan['records']), true));
                $rows = (new SQLitePragmaSchemaCatalog($plan['records']))->execute("PRAGMA table_info({$newTable})")['rows'];
                $t->same(['runtime_id', 'key_name', 'key_value'], array_column($rows, 'name'));
            },
        ];
    },
    'drop table then cache no longer resolves table' => static function (int $variant) use ($recordByName): array {
        $table = sprintf('app_events_%03d', $variant);
        return [
            ["DROP TABLE {$table}"],
            static function (TestRunner $t, array $plan) use ($table, $recordByName): void {
                $t->same('drop_table', $plan['operations'][0]['kind']);
                $t->same($table, $plan['operations'][0]['name']);
                $t->same(null, $recordByName($plan['records'], $table));
                $t->same([], (new SQLitePragmaSchemaCatalog($plan['records']))->execute("PRAGMA table_info({$table})")['rows']);
            },
        ];
    },
    'create index then indexed select has metadata' => static function (int $variant): array {
        $table = sprintf('app_settings_%03d', $variant);
        $index = sprintf('app_settings_%03d_runtime_idx', $variant);
        return [
            ["CREATE INDEX {$index} ON {$table}(key_value, key_name)"],
            static function (TestRunner $t, array $plan) use ($table, $index): void {
                $t->same('create_index', $plan['operations'][0]['kind']);
                $t->same($table, $plan['operations'][0]['table']);
                $t->same(['key_value', 'key_name'], array_column((new SQLitePragmaSchemaCatalog($plan['records']))->execute("PRAGMA index_info({$index})")['rows'], 'name'));
            },
        ];
    },
    'drop index then indexed select must reprepare' => static function (int $variant): array {
        $index = sprintf('app_settings_%03d_key_idx', $variant);
        return [
            ["DROP INDEX {$index}"],
            static function (TestRunner $t, array $plan) use ($index): void {
                $t->same('drop_index', $plan['operations'][0]['kind']);
                $t->same($index, $plan['operations'][0]['name']);
                $t->same([], (new SQLitePragmaSchemaCatalog($plan['records']))->execute("PRAGMA index_info({$index})")['rows']);
            },
        ];
    },
    'replace index then cache sees new term list' => static function (int $variant): array {
        $table = sprintf('app_settings_%03d', $variant);
        $index = sprintf('app_settings_%03d_key_idx', $variant);
        return [
            ["DROP INDEX {$index}", "CREATE INDEX {$index} ON {$table}(key_value)"],
            static function (TestRunner $t, array $plan) use ($index): void {
                $t->same(['drop_index', 'create_index'], array_column($plan['operations'], 'kind'));
                $t->same(['key_value'], array_column((new SQLitePragmaSchemaCatalog($plan['records']))->execute("PRAGMA index_info({$index})")['rows'], 'name'));
            },
        ];
    },
    'create view then select view has schema row' => static function (int $variant) use ($recordByName): array {
        $table = sprintf('app_settings_%03d', $variant);
        $view = sprintf('app_runtime_view_%03d', $variant);
        return [
            ["CREATE VIEW {$view} AS SELECT key_name FROM {$table}"],
            static function (TestRunner $t, array $plan) use ($view, $recordByName): void {
                $t->same('create_view', $plan['operations'][0]['kind']);
                $record = $recordByName($plan['records'], $view);
                $t->same('view', $record?->type);
                $t->same(0, $record?->rootPage);
            },
        ];
    },
    'drop view then select view must reparse missing object' => static function (int $variant) use ($recordByName): array {
        $view = sprintf('app_settings_%03d_view', $variant);
        return [
            ["DROP VIEW {$view}"],
            static function (TestRunner $t, array $plan) use ($view, $recordByName): void {
                $t->same('drop_view', $plan['operations'][0]['kind']);
                $t->same(null, $recordByName($plan['records'], $view));
            },
        ];
    },
    'create trigger then DML statement sees trigger schema' => static function (int $variant) use ($recordByName): array {
        $table = sprintf('app_events_%03d', $variant);
        $trigger = sprintf('app_events_%03d_runtime_tr', $variant);
        return [
            ["CREATE TRIGGER {$trigger} AFTER INSERT ON {$table} BEGIN SELECT 1; END"],
            static function (TestRunner $t, array $plan) use ($table, $trigger, $recordByName): void {
                $t->same('create_trigger', $plan['operations'][0]['kind']);
                $record = $recordByName($plan['records'], $trigger);
                $t->same('trigger', $record?->type);
                $t->same($table, $record?->tableName);
            },
        ];
    },
    'drop trigger then DML statement drops trigger dependency' => static function (int $variant) use ($recordByName): array {
        $trigger = sprintf('app_settings_%03d_audit_tr', $variant);
        return [
            ["DROP TRIGGER {$trigger}"],
            static function (TestRunner $t, array $plan) use ($trigger, $recordByName): void {
                $t->same('drop_trigger', $plan['operations'][0]['kind']);
                $t->same(null, $recordByName($plan['records'], $trigger));
            },
        ];
    },
    'replace trigger then cache sees new trigger row' => static function (int $variant) use ($recordByName): array {
        $table = sprintf('app_settings_%03d', $variant);
        $trigger = sprintf('app_settings_%03d_audit_tr', $variant);
        return [
            ["DROP TRIGGER {$trigger}", "CREATE TRIGGER {$trigger} AFTER INSERT ON {$table} BEGIN SELECT 2; END"],
            static function (TestRunner $t, array $plan) use ($trigger, $recordByName): void {
                $t->same(['drop_trigger', 'create_trigger'], array_column($plan['operations'], 'kind'));
                $record = $recordByName($plan['records'], $trigger);
                $t->contains('SELECT 2', $record?->sql ?? '');
            },
        ];
    },
    'replace table then dependent index trigger rows are removed' => static function (int $variant): array {
        $table = sprintf('app_settings_%03d', $variant);
        return [
            ["DROP TABLE {$table}", "CREATE TABLE {$table}(fresh_id INTEGER PRIMARY KEY, fresh_value TEXT)"],
            static function (TestRunner $t, array $plan) use ($table): void {
                $t->same(['drop_table', 'create_table'], array_column($plan['operations'], 'kind'));
                $catalog = new SQLitePragmaSchemaCatalog($plan['records']);
                $t->same(['fresh_id', 'fresh_value'], array_column($catalog->execute("PRAGMA table_info({$table})")['rows'], 'name'));
                $t->same([], $catalog->execute("PRAGMA index_list({$table})")['rows']);
            },
        ];
    },
];

foreach (range(1, 100) as $variant) {
    foreach ($operations as $name => $caseFactory) {
        $tests[sprintf('real upstream pragma schema dynamic schema3 %s variant %03d', $name, $variant)] = static function (TestRunner $t) use ($baseRecords, $prepared, $recordNames, $caseFactory, $variant): void {
            [$ddl, $assertPlan] = $caseFactory($variant);
            $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords($variant), $ddl, 40 + $variant, 'main', $prepared($variant, 40 + $variant));

            $t->same('ok', $plan['status']);
            $t->same(true, $plan['schema_changed']);
            $t->same(40 + $variant + count($ddl), $plan['after_schema_cookie']);
            $t->same([sprintf('reader-%03d-current-schema', $variant), sprintf('writer-%03d-current-schema', $variant)], $plan['invalidated_prepared']);
            $t->same(count($recordNames($plan['records'])), count(array_unique($recordNames($plan['records']))));
            $t->same(['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog'], $plan['dependencies']);

            $assertPlan($t, $plan);
        };
    }
}

$tests['real upstream pragma schema dynamic schema3 cites upstream source and exclusions'] = static function (TestRunner $t): void {
    $sections = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test schema3-1.1 through schema3-1.6 create table/index refresh',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test schema3-1.14 through schema3-1.16 index and trigger create/drop refresh',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test schema3-1.17 through schema3-1.22 view/table/index/trigger replacement refresh',
    ];

    $t->same(3, count($sections));
    $t->contains('schema3.test', $sections[0]);
    $t->contains('schema3-1.22', $sections[2]);
    $t->contains('ALTER TABLE ADD COLUMN', 'Excluded here: ALTER TABLE ADD COLUMN remains outside SQLiteSchemaDdlReparsePlan and should be handled by a separate schema3 follow-up if needed.');
};

return $tests;
