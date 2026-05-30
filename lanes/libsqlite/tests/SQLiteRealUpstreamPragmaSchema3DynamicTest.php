<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$baseRecords = static function (int $variant): array {
    $settings = 'app_settings_' . $variant;
    $audit = 'app_audit_' . $variant;
    $view = 'app_settings_view_' . $variant;
    $index = 'app_settings_' . $variant . '_key_idx';
    $trigger = 'app_settings_' . $variant . '_au';

    return [
        new SQLiteSchemaRecord(
            'table',
            $settings,
            $settings,
            2,
            "CREATE TABLE {$settings}(setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)",
            1,
        ),
        new SQLiteSchemaRecord(
            'table',
            $audit,
            $audit,
            3,
            "CREATE TABLE {$audit}(audit_id INTEGER PRIMARY KEY, key_name TEXT, action_name TEXT)",
            2,
        ),
        new SQLiteSchemaRecord(
            'index',
            $index,
            $settings,
            4,
            "CREATE INDEX {$index} ON {$settings}(key_name)",
            3,
        ),
        new SQLiteSchemaRecord(
            'view',
            $view,
            $view,
            0,
            "CREATE VIEW {$view} AS SELECT setting_id, key_name, key_value FROM {$settings}",
            4,
        ),
        new SQLiteSchemaRecord(
            'trigger',
            $trigger,
            $settings,
            0,
            "CREATE TRIGGER {$trigger} AFTER UPDATE ON {$settings} BEGIN INSERT INTO {$audit}(key_name, action_name) VALUES (new.key_name, 'update'); END",
            5,
        ),
    ];
};

$prepared = static fn (int $variant, int $cookie): array => [
    ['id' => 'schema3-cache-scan-' . $variant, 'schema_cookie' => $cookie, 'sql' => 'SELECT * FROM sqlite_schema'],
    ['id' => 'schema3-target-scan-' . $variant, 'schema_cookie' => $cookie, 'sql' => 'SELECT key_name FROM app_settings_' . $variant],
];

$recordNames = static fn (array $records): array => array_map(
    static fn (SQLiteSchemaRecord $record): string => $record->type . ':' . $record->name,
    $records,
);

foreach (range(1, 250) as $variant) {
    $settings = 'app_settings_' . $variant;
    $audit = 'app_audit_' . $variant;
    $view = 'app_settings_view_' . $variant;
    $index = 'app_settings_' . $variant . '_key_idx';
    $created = 'schema3_created_' . $variant;
    $createdIndex = 'schema3_created_' . $variant . '_idx';
    $createdTrigger = 'schema3_created_' . $variant . '_ai';
    $createdView = 'schema3_view_' . $variant;
    $replacementIndex = 'schema3_replaced_idx_' . $variant;
    $replacementTrigger = 'schema3_replaced_trigger_' . $variant;

    $tests["real upstream schema3 dynamic new table cache refresh {$variant}"] = static function (TestRunner $t) use ($baseRecords, $prepared, $recordNames, $variant, $created, $createdIndex, $createdTrigger): void {
        $cookie = 3000 + $variant;
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords($variant),
            [
                "CREATE TABLE {$created}(a INTEGER, b TEXT)",
                "CREATE INDEX {$createdIndex} ON {$created}(a)",
                "CREATE TRIGGER {$createdTrigger} AFTER INSERT ON {$created} BEGIN SELECT new.a; END",
            ],
            $cookie,
            'main',
            $prepared($variant, $cookie),
        );
        $names = $recordNames($plan['records']);

        $t->same(['create_table', 'create_index', 'create_trigger'], array_column($plan['operations'], 'kind'));
        $t->same($cookie + 3, $plan['after_schema_cookie']);
        $t->same(true, in_array('table:' . $created, $names, true));
        $t->same(true, in_array('index:' . $createdIndex, $names, true));
        $t->same(true, in_array('trigger:' . $createdTrigger, $names, true));
        $t->same(['schema3-cache-scan-' . $variant, 'schema3-target-scan-' . $variant], $plan['invalidated_prepared']);
    };

    $tests["real upstream schema3 dynamic alter add column cache refresh {$variant}"] = static function (TestRunner $t) use ($baseRecords, $prepared, $variant, $settings, $view, $index): void {
        $cookie = 4000 + $variant;
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords($variant),
            ["ALTER TABLE {$settings} ADD COLUMN load_policy TEXT DEFAULT 'lazy'"],
            $cookie,
            'main',
            $prepared($variant, $cookie),
        );
        $catalog = new SQLitePragmaSchemaCatalog($plan['records']);
        $info = $catalog->execute("PRAGMA table_info({$settings})")['rows'];

        $t->same('alter_table_add_column', $plan['operations'][0]['kind']);
        $t->same('load_policy', $plan['operations'][0]['column']);
        $t->same(4, $plan['operations'][0]['column_count']);
        $t->same($cookie + 1, $plan['after_schema_cookie']);
        $t->same(true, in_array('index:' . $index, $plan['operations'][0]['dependent_reparse_records'], true));
        $t->same('load_policy', $info[3]['name']);
    };

    $tests["real upstream schema3 dynamic replacement DDL cache refresh {$variant}"] = static function (TestRunner $t) use ($baseRecords, $prepared, $recordNames, $variant, $settings, $audit, $index, $replacementIndex, $replacementTrigger): void {
        $cookie = 5000 + $variant;
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords($variant),
            [
                "DROP INDEX {$index}",
                "CREATE INDEX {$replacementIndex} ON {$settings}(key_value, key_name)",
                "DROP TRIGGER app_settings_{$variant}_au",
                "CREATE TRIGGER {$replacementTrigger} AFTER INSERT ON {$settings} BEGIN INSERT INTO {$audit}(key_name, action_name) VALUES (new.key_name, 'insert'); END",
            ],
            $cookie,
            'main',
            $prepared($variant, $cookie),
        );
        $names = $recordNames($plan['records']);

        $t->same(['drop_index', 'create_index', 'drop_trigger', 'create_trigger'], array_column($plan['operations'], 'kind'));
        $t->same($cookie + 4, $plan['after_schema_cookie']);
        $t->same(false, in_array('index:' . $index, $names, true));
        $t->same(true, in_array('index:' . $replacementIndex, $names, true));
        $t->same(true, in_array('trigger:' . $replacementTrigger, $names, true));
        $t->same(['schema3-cache-scan-' . $variant, 'schema3-target-scan-' . $variant], $plan['invalidated_prepared']);
    };

    $tests["real upstream schema3 dynamic view drop recreate cache refresh {$variant}"] = static function (TestRunner $t) use ($baseRecords, $prepared, $recordNames, $variant, $settings, $view, $createdView): void {
        $cookie = 6000 + $variant;
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords($variant),
            [
                "DROP VIEW {$view}",
                "CREATE VIEW {$createdView} AS SELECT setting_id, key_name FROM {$settings}",
            ],
            $cookie,
            'main',
            $prepared($variant, $cookie),
        );
        $names = $recordNames($plan['records']);

        $t->same(['drop_view', 'create_view'], array_column($plan['operations'], 'kind'));
        $t->same($cookie + 2, $plan['after_schema_cookie']);
        $t->same(false, in_array('view:' . $view, $names, true));
        $t->same(true, in_array('view:' . $createdView, $names, true));
        $t->same([$settings], $plan['operations'][1]['source_tables']);
        $t->same(['schema3-cache-scan-' . $variant, 'schema3-target-scan-' . $variant], $plan['invalidated_prepared']);
    };
}

return $tests;
