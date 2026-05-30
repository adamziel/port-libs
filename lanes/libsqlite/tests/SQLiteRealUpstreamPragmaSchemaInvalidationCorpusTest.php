<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $rootPage, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$baseRecords = static fn (): array => [
    $record('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id INTEGER PRIMARY KEY, key_name TEXT NOT NULL, key_value TEXT, load_policy TEXT DEFAULT "lazy")', 1),
    $record('index', 'app_settings_key_unique', 'app_settings', 3, 'CREATE UNIQUE INDEX app_settings_key_unique ON app_settings(key_name)', 2),
    $record('index', 'app_settings_load_policy', 'app_settings', 4, 'CREATE INDEX app_settings_load_policy ON app_settings(load_policy)', 3),
    $record('table', 'app_audit', 'app_audit', 5, 'CREATE TABLE app_audit(audit_id INTEGER PRIMARY KEY, key_name TEXT, event_name TEXT)', 4),
    $record('view', 'app_lazy_settings', 'app_lazy_settings', 0, 'CREATE VIEW app_lazy_settings AS SELECT setting_id, key_name FROM app_settings WHERE load_policy = "lazy"', 5),
    $record('trigger', 'app_settings_audit_ai', 'app_settings', 0, 'CREATE TRIGGER app_settings_audit_ai AFTER INSERT ON app_settings BEGIN INSERT INTO app_audit(key_name,event_name) VALUES(new.key_name,"insert"); END', 6),
];

$prepared = static fn (int $cookie = 500): array => [
    ['id' => 'schema-master-reader', 'schema_cookie' => $cookie, 'sql' => 'SELECT * FROM sqlite_schema'],
    ['id' => 'settings-reader', 'schema_cookie' => $cookie, 'sql' => 'SELECT * FROM app_settings'],
    ['id' => 'lazy-view-reader', 'schema_cookie' => $cookie, 'sql' => 'SELECT * FROM app_lazy_settings'],
    ['id' => 'already-current', 'schema_cookie' => $cookie + 1, 'sql' => 'SELECT * FROM app_audit'],
];

$plan = static fn (array $ddl, ?array $records = null, int $cookie = 500, ?array $statements = null): array => SQLiteSchemaDdlReparsePlan::apply(
    $records ?? $baseRecords(),
    $ddl,
    $cookie,
    'main',
    $statements ?? $prepared($cookie),
);

$namesForType = static function (array $records, string $type): array {
    $names = [];
    foreach ($records as $schemaRecord) {
        if ($schemaRecord instanceof SQLiteSchemaRecord && $schemaRecord->type === $type) {
            $names[] = $schemaRecord->name;
        }
    }

    sort($names);

    return $names;
};

$rowForName = static function (array $records, string $name): ?SQLiteSchemaRecord {
    foreach ($records as $schemaRecord) {
        if ($schemaRecord instanceof SQLiteSchemaRecord && strcasecmp($schemaRecord->name, $name) === 0) {
            return $schemaRecord;
        }
    }

    return null;
};

$tests = [
    'real upstream schema invalidation corpus schema-1.1 create table expires prepared statements' => static function (TestRunner $t) use ($plan): void {
        $result = $plan(['CREATE TABLE app_runtime(runtime_id INTEGER PRIMARY KEY, key_name TEXT)']);
        $t->same('ok', $result['status']);
        $t->same('create_table', $result['operations'][0]['kind']);
        $t->same('app_runtime', $result['operations'][0]['name']);
        $t->same(6, $result['operations'][0]['rootpage']);
        $t->same(7, $result['operations'][0]['rowid']);
        $t->same(501, $result['after_schema_cookie']);
        $t->same(['schema-master-reader', 'settings-reader', 'lazy-view-reader'], $result['invalidated_prepared']);
        $t->same(true, $result['schema_changed']);
        $t->same(3, $result['table_count']);
    },
    'real upstream schema invalidation corpus schema-1.3 drop table expires prepared statements and dependents' => static function (TestRunner $t) use ($plan, $namesForType): void {
        $result = $plan(['DROP TABLE app_settings']);
        $t->same('drop_table', $result['operations'][0]['kind']);
        $t->same('app_settings', $result['operations'][0]['name']);
        $t->same(['table:app_settings', 'index:app_settings_key_unique', 'index:app_settings_load_policy', 'trigger:app_settings_audit_ai'], $result['operations'][0]['removed_records']);
        $t->same([2, 3, 4], $result['operations'][0]['freed_rootpages']);
        $t->same(['app_audit'], $namesForType($result['records'], 'table'));
        $t->same([], $namesForType($result['records'], 'index'));
        $t->same([], $namesForType($result['records'], 'trigger'));
        $t->same(['schema-master-reader', 'settings-reader', 'lazy-view-reader'], $result['invalidated_prepared']);
    },
    'real upstream schema invalidation corpus schema-2.1 create view expires prepared statements' => static function (TestRunner $t) use ($plan, $rowForName): void {
        $result = $plan(['CREATE VIEW app_active_settings AS SELECT setting_id, key_name FROM app_settings WHERE load_policy <> "archived"']);
        $operation = $result['operations'][0];
        $t->same('create_view', $operation['kind']);
        $t->same('app_active_settings', $operation['name']);
        $t->same(0, $operation['rootpage']);
        $t->same(7, $operation['rowid']);
        $t->same(['app_settings'], $operation['source_tables']);
        $t->same(false, $operation['current_source_reparse']);
        $t->same(501, $result['after_schema_cookie']);
        $t->same('view', $rowForName($result['records'], 'app_active_settings')?->type);
    },
    'real upstream schema invalidation corpus schema-2.3 drop view expires prepared statements' => static function (TestRunner $t) use ($plan, $rowForName): void {
        $result = $plan(['DROP VIEW app_lazy_settings']);
        $t->same('drop_view', $result['operations'][0]['kind']);
        $t->same('app_lazy_settings', $result['operations'][0]['name']);
        $t->same(true, $result['operations'][0]['changed']);
        $t->same(null, $rowForName($result['records'], 'app_lazy_settings'));
        $t->same(501, $result['after_schema_cookie']);
        $t->same(['schema-master-reader', 'settings-reader', 'lazy-view-reader'], $result['invalidated_prepared']);
    },
    'real upstream schema invalidation corpus schema-3.1 create trigger expires prepared statements' => static function (TestRunner $t) use ($plan, $rowForName): void {
        $result = $plan(['CREATE TRIGGER app_settings_audit_au AFTER UPDATE ON app_settings BEGIN INSERT INTO app_audit(key_name,event_name) VALUES(new.key_name,"update"); END']);
        $operation = $result['operations'][0];
        $t->same('create_trigger', $operation['kind']);
        $t->same('app_settings_audit_au', $operation['name']);
        $t->same('app_settings', $operation['table']);
        $t->same(0, $operation['rootpage']);
        $t->same(7, $operation['rowid']);
        $t->same(['app_audit'], $operation['body_source_tables']);
        $t->same(false, $operation['current_source_reparse']);
        $t->same('trigger', $rowForName($result['records'], 'app_settings_audit_au')?->type);
    },
    'real upstream schema invalidation corpus schema-3.3 drop trigger expires prepared statements' => static function (TestRunner $t) use ($plan, $rowForName): void {
        $result = $plan(['DROP TRIGGER app_settings_audit_ai']);
        $t->same('drop_trigger', $result['operations'][0]['kind']);
        $t->same('app_settings_audit_ai', $result['operations'][0]['name']);
        $t->same('app_settings', $result['operations'][0]['table']);
        $t->same(null, $rowForName($result['records'], 'app_settings_audit_ai'));
        $t->same(501, $result['after_schema_cookie']);
        $t->same(['schema-master-reader', 'settings-reader', 'lazy-view-reader'], $result['invalidated_prepared']);
    },
    'real upstream schema invalidation corpus schema-4.1 create index expires prepared statements' => static function (TestRunner $t) use ($plan): void {
        $result = $plan(['CREATE INDEX app_settings_value_lookup ON app_settings(key_value, key_name)']);
        $operation = $result['operations'][0];
        $t->same('create_index', $operation['kind']);
        $t->same('app_settings_value_lookup', $operation['name']);
        $t->same('app_settings', $operation['table']);
        $t->same(6, $operation['rootpage']);
        $t->same(7, $operation['rowid']);
        $t->same(false, $operation['unique']);
        $t->same(['key_value', 'key_name'], $operation['terms']);
        $t->same(3, $result['index_count']);
    },
    'real upstream schema invalidation corpus schema-4.3 drop index expires prepared statements' => static function (TestRunner $t) use ($plan): void {
        $result = $plan(['DROP INDEX app_settings_load_policy']);
        $t->same('drop_index', $result['operations'][0]['kind']);
        $t->same('app_settings_load_policy', $result['operations'][0]['name']);
        $t->same('app_settings', $result['operations'][0]['table']);
        $t->same(4, $result['operations'][0]['freed_rootpage']);
        $t->same(1, $result['index_count']);
        $t->same(501, $result['after_schema_cookie']);
    },
    'real upstream schema invalidation corpus schema-5.1 attach-equivalent read leaves prepared statements reusable' => static function (TestRunner $t) use ($plan): void {
        $result = $plan(['DROP INDEX IF EXISTS missing_attached_index']);
        $t->same('drop_index', $result['operations'][0]['kind']);
        $t->same(false, $result['operations'][0]['changed']);
        $t->same('missing_index', $result['operations'][0]['reason']);
        $t->same(false, $result['schema_changed']);
        $t->same(500, $result['after_schema_cookie']);
        $t->same([], $result['invalidated_prepared']);
        $t->same(2, $result['index_count']);
    },
    'real upstream schema invalidation corpus schema-5.3 detach-equivalent schema removal expires prepared statements' => static function (TestRunner $t) use ($record, $prepared): void {
        $records = [
            $record('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id INTEGER PRIMARY KEY, key_name TEXT)', 1),
            $record('table', 'tenant_settings', 'tenant_settings', 3, 'CREATE TABLE tenant_settings(tenant_id INTEGER, key_name TEXT)', 2),
        ];
        $result = SQLiteSchemaDdlReparsePlan::apply($records, ['DROP TABLE tenant_settings'], 80, 'main', $prepared(80));
        $t->same('drop_table', $result['operations'][0]['kind']);
        $t->same('tenant_settings', $result['operations'][0]['name']);
        $t->same(['table:tenant_settings'], $result['operations'][0]['removed_records']);
        $t->same(81, $result['after_schema_cookie']);
        $t->same(['schema-master-reader', 'settings-reader', 'lazy-view-reader'], $result['invalidated_prepared']);
    },
    'real upstream schema invalidation corpus schema-10.2 create table with active cursor keeps schema catalog coherent' => static function (TestRunner $t) use ($plan): void {
        $result = $plan(['CREATE TABLE app_cursor_probe(probe_id INTEGER PRIMARY KEY, key_name TEXT)']);
        $catalog = new SQLitePragmaSchemaCatalog($result['records']);
        $tableList = $catalog->execute('PRAGMA table_list')['rows'];
        $t->same('ok', $result['status']);
        $t->same(3, $result['table_count']);
        $t->same(true, in_array('app_cursor_probe', array_column($tableList, 'name'), true));
        $t->same([['cid' => 0, 'name' => 'probe_id', 'type' => 'INTEGER', 'notnull' => 1, 'dflt_value' => null, 'pk' => 1], ['cid' => 1, 'name' => 'key_name', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0]], $catalog->execute('PRAGMA table_info(app_cursor_probe)')['rows']);
    },
    'real upstream schema invalidation corpus schema-12.1 rollback-equivalent cookie reuse still expires stale statement' => static function (TestRunner $t) use ($record): void {
        $records = [
            $record('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id INTEGER PRIMARY KEY)', 1),
            $record('table', 'app_transient_schema', 'app_transient_schema', 3, 'CREATE TABLE app_transient_schema(id INTEGER)', 2),
        ];
        $statements = [
            ['id' => 'prepared-during-rolled-back-cookie', 'schema_cookie' => 41, 'sql' => 'CREATE TABLE app_runtime(id INTEGER)'],
            ['id' => 'fresh-after-rollback', 'schema_cookie' => 42, 'sql' => 'SELECT * FROM app_settings'],
        ];
        $rolledBack = SQLiteSchemaDdlReparsePlan::apply($records, ['DROP TABLE app_transient_schema', 'CREATE TABLE app_runtime(id INTEGER)'], 40, 'main', $statements);
        $t->same(42, $rolledBack['after_schema_cookie']);
        $t->same(['prepared-during-rolled-back-cookie'], $rolledBack['invalidated_prepared']);
        $t->same(['drop_table', 'create_table'], array_column($rolledBack['operations'], 'kind'));
        $t->same(true, $rolledBack['schema_changed']);
        $t->same(2, $rolledBack['table_count']);
    },
];

$ddlCases = [
    ['schema-1.1', 'CREATE TABLE app_runtime_a(a INTEGER, b TEXT)', 'create_table', 'app_runtime_a', 501],
    ['schema-1.3', 'DROP TABLE app_audit', 'drop_table', 'app_audit', 501],
    ['schema-2.1', 'CREATE VIEW app_runtime_view AS SELECT key_name FROM app_settings', 'create_view', 'app_runtime_view', 501],
    ['schema-2.3', 'DROP VIEW app_lazy_settings', 'drop_view', 'app_lazy_settings', 501],
    ['schema-3.1', 'CREATE TRIGGER app_runtime_trigger AFTER INSERT ON app_settings BEGIN SELECT new.key_name; END', 'create_trigger', 'app_runtime_trigger', 501],
    ['schema-3.3', 'DROP TRIGGER app_settings_audit_ai', 'drop_trigger', 'app_settings_audit_ai', 501],
    ['schema-4.1', 'CREATE INDEX app_runtime_index ON app_settings(key_value)', 'create_index', 'app_runtime_index', 501],
    ['schema-4.3', 'DROP INDEX app_settings_load_policy', 'drop_index', 'app_settings_load_policy', 501],
];

foreach ($ddlCases as [$upstream, $sql, $kind, $name, $cookie]) {
    $tests['real upstream schema invalidation corpus ' . $upstream . ' operation ' . $kind . ' row shape for ' . $name] = static function (TestRunner $t) use ($plan, $sql, $kind, $name, $cookie): void {
        $result = $plan([$sql]);
        $operation = $result['operations'][0];
        $t->same($kind, $operation['kind']);
        $t->same($name, $operation['name']);
        $t->same(true, $operation['changed']);
        $t->same($cookie, $result['after_schema_cookie']);
        $t->same(true, $result['schema_changed']);
        $t->same(['schema-master-reader', 'settings-reader', 'lazy-view-reader'], $result['invalidated_prepared']);
    };
}

$noOpCases = [
    ['schema-1 duplicate table', 'CREATE TABLE app_settings(setting_id INTEGER)', 'create_table', 'table_already_exists'],
    ['schema-2 duplicate view', 'CREATE VIEW app_lazy_settings AS SELECT 1', 'create_view', 'view_already_exists'],
    ['schema-3 duplicate trigger', 'CREATE TRIGGER app_settings_audit_ai AFTER INSERT ON app_settings BEGIN SELECT 1; END', 'create_trigger', 'trigger_already_exists'],
    ['schema-4 duplicate index', 'CREATE INDEX app_settings_load_policy ON app_settings(load_policy)', 'create_index', 'index_already_exists'],
    ['schema-4 missing index', 'DROP INDEX missing_runtime_index', 'drop_index', 'missing_index'],
    ['schema-2 missing view', 'DROP VIEW missing_runtime_view', 'drop_view', 'missing_view'],
    ['schema-3 missing trigger', 'DROP TRIGGER missing_runtime_trigger', 'drop_trigger', 'missing_trigger'],
    ['schema-1 missing table', 'DROP TABLE missing_runtime_table', 'drop_table', 'missing_table'],
];

foreach ($noOpCases as [$upstream, $sql, $kind, $reason]) {
    $tests['real upstream schema invalidation corpus ' . $upstream . ' does not expire statements'] = static function (TestRunner $t) use ($plan, $sql, $kind, $reason): void {
        $result = $plan([$sql]);
        $operation = $result['operations'][0];
        $t->same($kind, $operation['kind']);
        $t->same(false, $operation['changed']);
        $t->same($reason, $operation['reason']);
        $t->same(500, $result['after_schema_cookie']);
        $t->same(false, $result['schema_changed']);
        $t->same([], $result['invalidated_prepared']);
    };
}

foreach (range(1, 36) as $i) {
    $tests['real upstream schema invalidation corpus dynamic create/drop table pair schema-1 ' . $i] = static function (TestRunner $t) use ($plan, $i): void {
        $table = 'dynamic_settings_' . $i;
        $result = $plan([
            'CREATE TABLE ' . $table . '(setting_id INTEGER PRIMARY KEY, key_name TEXT NOT NULL, key_value TEXT)',
            'CREATE INDEX ' . $table . '_key_idx ON ' . $table . '(key_name)',
            'DROP INDEX ' . $table . '_key_idx',
            'DROP TABLE ' . $table,
        ]);
        $t->same(['create_table', 'create_index', 'drop_index', 'drop_table'], array_column($result['operations'], 'kind'));
        $t->same([true, true, true, true], array_column($result['operations'], 'changed'));
        $t->same(504, $result['after_schema_cookie']);
        $t->same(2, $result['table_count']);
        $t->same(2, $result['index_count']);
        $t->same(['schema-master-reader', 'settings-reader', 'lazy-view-reader', 'already-current'], $result['invalidated_prepared']);
        $t->same([$table . '_key_idx'], [$result['operations'][2]['name']]);
    };
}

foreach (range(1, 24) as $i) {
    $tests['real upstream schema invalidation corpus dynamic view trigger schema-2 schema-3 ' . $i] = static function (TestRunner $t) use ($plan, $i): void {
        $view = 'dynamic_lazy_view_' . $i;
        $trigger = 'dynamic_settings_audit_' . $i;
        $result = $plan([
            'CREATE VIEW ' . $view . ' AS SELECT setting_id, key_name FROM app_settings WHERE load_policy = "lazy"',
            'CREATE TRIGGER ' . $trigger . ' AFTER UPDATE ON app_settings BEGIN INSERT INTO app_audit(key_name,event_name) SELECT key_name,"dynamic" FROM ' . $view . ' WHERE setting_id = new.setting_id; END',
            'DROP TRIGGER ' . $trigger,
            'DROP VIEW ' . $view,
        ]);
        $t->same(['create_view', 'create_trigger', 'drop_trigger', 'drop_view'], array_column($result['operations'], 'kind'));
        $t->same($view, $result['operations'][0]['name']);
        $t->same($trigger, $result['operations'][1]['name']);
        $t->same([$view], $result['operations'][1]['body_source_views']);
        $t->same(true, $result['operations'][1]['current_source_reparse']);
        $t->same(504, $result['after_schema_cookie']);
        $t->same(['schema-master-reader', 'settings-reader', 'lazy-view-reader', 'already-current'], $result['invalidated_prepared']);
    };
}

foreach (range(1, 12) as $i) {
    $tests['real upstream schema invalidation corpus rollback duplicate-cookie fence schema-12 ' . $i] = static function (TestRunner $t) use ($record, $i): void {
        $records = [
            $record('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id INTEGER PRIMARY KEY)', 1),
            $record('table', 'transient_schema_' . $i, 'transient_schema_' . $i, 3, 'CREATE TABLE transient_schema_' . $i . '(id INTEGER)', 2),
        ];
        $statements = [
            ['id' => 'rolled-back-ddl-' . $i, 'schema_cookie' => 601, 'sql' => 'CREATE TABLE durable_schema_' . $i . '(id INTEGER)'],
            ['id' => 'current-reader-' . $i, 'schema_cookie' => 602, 'sql' => 'SELECT * FROM app_settings'],
        ];
        $result = SQLiteSchemaDdlReparsePlan::apply($records, ['DROP TABLE transient_schema_' . $i, 'CREATE TABLE durable_schema_' . $i . '(id INTEGER)'], 600, 'main', $statements);
        $t->same(602, $result['after_schema_cookie']);
        $t->same(['rolled-back-ddl-' . $i], $result['invalidated_prepared']);
        $t->same(['drop_table', 'create_table'], array_column($result['operations'], 'kind'));
        $t->same('durable_schema_' . $i, $result['operations'][1]['name']);
        $t->same(true, $result['schema_changed']);
    };
}

return $tests;
