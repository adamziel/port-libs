<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$baseRecords = static function (int $variant): array {
    $settings = 'app_settings_' . $variant;
    $audit = 'app_audit_' . $variant;
    $view = 'active_settings_' . $variant;
    $index = 'app_settings_' . $variant . '_key_idx';
    $trigger = 'app_settings_' . $variant . '_ai';

    return [
        new SQLiteSchemaRecord(
            'table',
            $settings,
            $settings,
            2,
            "CREATE TABLE {$settings}(setting_id INTEGER PRIMARY KEY, key_name TEXT NOT NULL, key_value TEXT, load_policy TEXT DEFAULT 'eager')",
            1,
        ),
        new SQLiteSchemaRecord('index', $index, $settings, 3, "CREATE INDEX {$index} ON {$settings}(key_name, load_policy)", 2),
        new SQLiteSchemaRecord('table', $audit, $audit, 4, "CREATE TABLE {$audit}(audit_id INTEGER PRIMARY KEY, key_name TEXT, action_name TEXT)", 3),
        new SQLiteSchemaRecord('view', $view, $view, 0, "CREATE VIEW {$view} AS SELECT key_name, key_value FROM {$settings} WHERE load_policy = 'eager'", 4),
        new SQLiteSchemaRecord('trigger', $trigger, $settings, 0, "CREATE TRIGGER {$trigger} AFTER INSERT ON {$settings} BEGIN INSERT INTO {$audit}(key_name, action_name) VALUES (new.key_name, 'insert'); END", 5),
    ];
};

$prepared = static fn (int $variant, int $cookie, bool $expiredByRollback = false): array => [
    ['id' => 'schema-test-sqlite-schema-scan-' . $variant, 'schema_cookie' => $cookie, 'sql' => 'SELECT * FROM sqlite_schema', 'expired_by_rollback' => $expiredByRollback],
    ['id' => 'schema-test-target-scan-' . $variant, 'schema_cookie' => $cookie, 'sql' => 'SELECT key_name FROM app_settings_' . $variant, 'expired_by_rollback' => $expiredByRollback],
];

$recordNames = static fn (array $records): array => array_map(
    static fn (SQLiteSchemaRecord $record): string => $record->type . ':' . $record->name,
    $records,
);

foreach (range(1, 250) as $variant) {
    $settings = 'app_settings_' . $variant;
    $audit = 'app_audit_' . $variant;
    $view = 'active_settings_' . $variant;
    $index = 'app_settings_' . $variant . '_key_idx';
    $trigger = 'app_settings_' . $variant . '_ai';
    $replacementTable = 'schema_create_' . $variant;
    $replacementView = 'schema_view_' . $variant;
    $replacementIndex = 'schema_index_' . $variant;
    $replacementTrigger = 'schema_trigger_' . $variant;

    $tests["real upstream schema.test dynamic create invalidates prepared schema scan {$variant}"] = static function (TestRunner $t) use ($baseRecords, $prepared, $recordNames, $variant, $replacementTable): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords($variant),
            ["CREATE TABLE {$replacementTable}(a INTEGER, b TEXT, c TEXT DEFAULT 'schema')"],
            40 + $variant,
            'main',
            $prepared($variant, 40 + $variant),
        );
        $names = $recordNames($plan['records']);

        $t->same('ok', $plan['status']);
        $t->same('create_table', $plan['operations'][0]['kind']);
        $t->same($replacementTable, $plan['operations'][0]['name']);
        $t->same(41 + $variant, $plan['after_schema_cookie']);
        $t->same(['schema-test-sqlite-schema-scan-' . $variant, 'schema-test-target-scan-' . $variant], $plan['invalidated_prepared']);
        $t->same(true, in_array('table:' . $replacementTable, $names, true));
    };

    $tests["real upstream schema.test dynamic drop table removes dependents {$variant}"] = static function (TestRunner $t) use ($baseRecords, $prepared, $recordNames, $variant, $settings, $index, $trigger, $view): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords($variant),
            ["DROP TABLE {$settings}"],
            80 + $variant,
            'main',
            $prepared($variant, 80 + $variant),
        );
        $operation = $plan['operations'][0];
        $names = $recordNames($plan['records']);

        $t->same('drop_table', $operation['kind']);
        $t->same($settings, $operation['name']);
        $t->same(['table:' . $settings, 'index:' . $index, 'trigger:' . $trigger], $operation['removed_records']);
        $t->same(false, in_array('table:' . $settings, $names, true));
        $t->same(true, in_array('view:' . $view, $names, true));
        $t->same(['schema-test-sqlite-schema-scan-' . $variant, 'schema-test-target-scan-' . $variant], $plan['invalidated_prepared']);
    };

    $tests["real upstream schema.test dynamic view trigger index invalidation {$variant}"] = static function (TestRunner $t) use ($baseRecords, $prepared, $variant, $settings, $audit, $replacementView, $replacementIndex, $replacementTrigger): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords($variant),
            [
                "CREATE VIEW {$replacementView} AS SELECT key_name FROM {$settings}",
                "CREATE INDEX {$replacementIndex} ON {$settings}(load_policy, key_name)",
                "CREATE TRIGGER {$replacementTrigger} AFTER UPDATE ON {$settings} BEGIN INSERT INTO {$audit}(key_name, action_name) VALUES (new.key_name, 'update'); END",
            ],
            120 + $variant,
            'main',
            $prepared($variant, 120 + $variant),
        );

        $t->same(['create_view', 'create_index', 'create_trigger'], array_column($plan['operations'], 'kind'));
        $t->same(123 + $variant, $plan['after_schema_cookie']);
        $t->same($replacementView, $plan['operations'][0]['name']);
        $t->same($replacementIndex, $plan['operations'][1]['name']);
        $t->same($replacementTrigger, $plan['operations'][2]['name']);
        $t->same(['schema-test-sqlite-schema-scan-' . $variant, 'schema-test-target-scan-' . $variant], $plan['invalidated_prepared']);
    };

    $tests["real upstream schema.test dynamic rollback expires same-cookie statement {$variant}"] = static function (TestRunner $t) use ($baseRecords, $prepared, $variant, $replacementTable): void {
        $state = new SQLitePragmaSchemaDataVersion(['main' => ['schema_version' => 200 + $variant, 'data_version' => 1, 'change_counter' => 1]]);
        $state->beginTransaction();
        $state->recordSchemaChange('main', 1, 'schema.test create table inside rolled back transaction');
        $during = $state->execute('PRAGMA schema_version')['value'];
        $state->rollbackTransaction();
        $afterRollback = $state->execute('PRAGMA schema_version')['value'];
        $reparse = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords($variant),
            ["CREATE TABLE {$replacementTable}(a INTEGER, b TEXT)"],
            $afterRollback,
            'main',
            $prepared($variant, $during, true),
        );

        $t->same(201 + $variant, $during);
        $t->same(200 + $variant, $afterRollback);
        $t->same(201 + $variant, $reparse['after_schema_cookie']);
        $t->same(['schema-test-sqlite-schema-scan-' . $variant, 'schema-test-target-scan-' . $variant], $reparse['invalidated_prepared']);
        $t->same($replacementTable, $reparse['operations'][0]['name']);
        $t->same(4, count((new SQLitePragmaSchemaCatalog($reparse['records']))->execute('PRAGMA table_list')['rows']));
    };
}

return $tests;
