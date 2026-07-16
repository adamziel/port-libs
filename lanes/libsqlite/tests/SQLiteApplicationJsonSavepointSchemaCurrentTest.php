<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonSavepointSchemaCurrentPlan;

$currentRows = static fn (): array => [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'active_plugins', 'key_value' => '[]', 'load_policy' => 'yes'],
    ['setting_id' => 70, 'key_name' => 'theme_mods_old', 'key_value' => '{"color":"blue"}', 'load_policy' => 'no'],
];

$schemaRows = static fn (): array => [
    ['type' => 'table', 'name' => 'app_settings', 'tbl_name' => 'app_settings', 'rootpage' => 2, 'sql' => 'CREATE TABLE app_settings (setting_id integer primary key, key_name text unique, key_value text, load_policy text)'],
    ['type' => 'index', 'name' => 'key_name', 'tbl_name' => 'app_settings', 'rootpage' => 3, 'sql' => 'CREATE UNIQUE INDEX key_name ON app_settings(key_name)'],
];

$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
$plan = static fn (array $batches, array $options = []) => SQLiteJsonSavepointSchemaCurrentPlan::plan(
    $currentRows(),
    $batches,
    $options + [
        'database_path' => '/tmp/app-json-savepoint-schema-current.sqlite',
        'page_size' => 1024,
        'schema_version' => 41,
        'data_version' => 300,
        'schema' => ['rows' => $schemaRows()],
    ],
);

$tests = [
    'json schema current creates schema row and advances schema cookie' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            [
                'name' => 'create_import_table',
                'json' => $jsonRows([
                    ['key_name' => 'plugin_created_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'app_import_batch',
                    'tbl_name' => 'app_import_batch',
                    'rootpage' => 8,
                    'sql' => 'CREATE TABLE app_import_batch (id integer primary key, payload text)',
                ]],
            ],
        ]);

        $t->same('planned', $result['status']);
        $t->same(true, $result['schema_current']);
        $t->same(42, $result['schema_version']);
        $t->same(302, $result['data_version']);
        $t->same(42, $result['batches'][0]['schema_cookie_frame']['schema_cookie']);
        $t->same(['app_import_batch', 'app_settings', 'key_name'], $result['schema_names']);
    },
    'json schema current rolls back duplicate schema name at current frame' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            [
                'name' => 'duplicate_schema',
                'json' => $jsonRows([
                    ['key_name' => 'plugin_dup_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'app_settings',
                    'tbl_name' => 'app_settings',
                    'rootpage' => 9,
                    'sql' => 'CREATE TABLE app_settings (id integer)',
                ]],
            ],
        ]);

        $t->same(['duplicate_schema'], $result['rolled_back_batches']);
        $t->same(['duplicate_schema'], $result['schema_rejected_batches']);
        $t->same(41, $result['schema_version']);
        $t->same(300, $result['data_version']);
        $t->same(0, $result['wal']['current_frame']);
        $t->same('duplicate_schema_name', $result['batches'][0]['schema']['violations'][0]['rule']);
    },
    'json schema current preserves released schema before later rollback' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            [
                'name' => 'release_table',
                'json' => $jsonRows([
                    ['key_name' => 'plugin_release_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'yes'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'app_release_batch',
                    'tbl_name' => 'app_release_batch',
                    'rootpage' => 10,
                    'sql' => 'CREATE TABLE app_release_batch (id integer primary key)',
                ]],
            ],
            [
                'name' => 'reject_index',
                'json' => $jsonRows([
                    ['key_name' => 'plugin_reject_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'yes'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'index',
                    'name' => 'bad_index',
                    'tbl_name' => 'app_settings',
                    'rootpage' => 0,
                    'sql' => 'CREATE INDEX bad_index ON app_settings(key_name)',
                ]],
            ],
        ]);

        $t->same(['release_table'], $result['released_batches']);
        $t->same(['reject_index'], $result['rolled_back_batches']);
        $t->same(['app_release_batch', 'app_settings', 'key_name'], $result['released_schema_names']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'plugin_release_settings'], $result['released_key_names']);
        $t->same(42, $result['schema_version']);
    },
    'json schema current keeps open schema visible but unreleased' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            [
                'name' => 'open_schema',
                'json' => $jsonRows([
                    ['key_name' => 'widget_open_settings', 'key_value' => '{"blocks":[]}', 'load_policy' => 'yes'],
                ]),
                'path' => '$.rows',
                'release' => false,
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'app_open_batch',
                    'tbl_name' => 'app_open_batch',
                    'rootpage' => 11,
                    'sql' => 'CREATE TABLE app_open_batch (id integer primary key)',
                ]],
            ],
        ]);

        $t->same('open', $result['batches'][0]['status']);
        $t->same(['app_open_batch', 'app_settings', 'key_name'], $result['schema_names']);
        $t->same(['app_settings', 'key_name'], $result['released_schema_names']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old'], $result['released_key_names']);
    },
    'json schema current drop schema row increments cookie' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            [
                'name' => 'drop_index',
                'json' => $jsonRows([
                    ['key_name' => 'plugin_drop_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [['action' => 'drop', 'name' => 'key_name']],
            ],
        ]);

        $t->same(['key_name'], $result['batches'][0]['schema']['dropped']);
        $t->same(['app_settings'], $result['schema_names']);
        $t->same(42, $result['schema_version']);
        $t->same(302, $result['data_version']);
    },
    'json schema current rejects malformed JSON without schema cookie write' => static function (TestRunner $t) use ($plan): void {
        $result = $plan([
            [
                'name' => 'bad_json',
                'json' => '{"rows":[',
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'app_bad_json',
                    'tbl_name' => 'app_bad_json',
                    'rootpage' => 12,
                    'sql' => 'CREATE TABLE app_bad_json (id integer primary key)',
                ]],
            ],
        ]);

        $t->same(['bad_json'], $result['rolled_back_batches']);
        $t->same(41, $result['schema_version']);
        $t->same(null, $result['batches'][0]['schema_cookie_frame']);
        $t->same(['app_settings', 'key_name'], $result['schema_names']);
    },
    'json schema current dependency marker is present' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'deps', 'json' => $jsonRows([
                ['key_name' => 'deps_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'no'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same(true, in_array('sqlite-application-json-savepoint-schema-current', $result['dependencies'], true));
    },
    'json schema current rejects empty batch list' => static function (TestRunner $t) use ($currentRows, $schemaRows): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonSavepointSchemaCurrentPlan::plan($currentRows(), [], [
            'schema' => ['rows' => $schemaRows()],
        ]));
    },
    'json schema current rejects unsafe savepoint names' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([
            ['name' => 'bad-name', 'json' => $jsonRows([
                ['key_name' => 'bad_name_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'no'],
            ]), 'path' => '$.rows'],
        ]));
    },
];

foreach (range(1, 20) as $index) {
    $tests["json schema current generated create batch {$index}"] = static function (TestRunner $t) use ($plan, $jsonRows, $index): void {
        $result = $plan([
            [
                'name' => 'create_' . $index,
                'json' => $jsonRows([
                    ['key_name' => 'generated_' . $index . '_settings', 'key_value' => '{"rank":' . $index . '}', 'load_policy' => $index % 2 === 0 ? 'yes' : 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'app_generated_' . $index,
                    'tbl_name' => 'app_generated_' . $index,
                    'rootpage' => 20 + $index,
                    'sql' => 'CREATE TABLE app_generated_' . $index . ' (id integer primary key, setting_id integer)',
                ]],
            ],
        ]);

        $t->same(42, $result['batches'][0]['after_schema_version']);
        $t->same('app_generated_' . $index, $result['batches'][0]['schema']['created'][0]);
        $t->same(2, $result['wal']['frame_count']);
    };
}

foreach ([
    'missing drop target' => [['action' => 'drop', 'name' => 'missing_table'], 'drop_existing'],
    'bad action' => [['action' => 'rename', 'name' => 'app_rename'], 'action'],
    'zero table rootpage' => [['type' => 'table', 'name' => 'app_zero', 'tbl_name' => 'app_zero', 'rootpage' => 0, 'sql' => 'CREATE TABLE app_zero (id integer)'], 'rootpage_positive'],
    'zero index rootpage' => [['type' => 'index', 'name' => 'app_zero_idx', 'tbl_name' => 'app_settings', 'rootpage' => 0, 'sql' => 'CREATE INDEX app_zero_idx ON app_settings(key_name)'], 'rootpage_positive'],
    'invalid create sql' => [['type' => 'view', 'name' => 'app_bad_view', 'tbl_name' => 'app_bad_view', 'rootpage' => 0, 'sql' => 'SELECT 1'], 'schema_row'],
    'invalid row type' => [['type' => 'virtual', 'name' => 'app_virtual', 'tbl_name' => 'app_virtual', 'rootpage' => 15, 'sql' => 'CREATE VIRTUAL TABLE app_virtual USING fts5(body)'], 'schema_row'],
] as $label => [$change, $rule]) {
    $tests["json schema current rolls back {$label}"] = static function (TestRunner $t) use ($plan, $jsonRows, $label, $change, $rule): void {
        $result = $plan([
            [
                'name' => 'reject_' . preg_replace('/[^a-z0-9]+/', '_', $label),
                'json' => $jsonRows([
                    ['key_name' => 'reject_' . preg_replace('/[^a-z0-9]+/', '_', $label) . '_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [$change],
            ],
        ]);

        $t->same('rolled_back', $result['batches'][0]['status']);
        $t->same($rule, $result['batches'][0]['schema']['violations'][0]['rule']);
        $t->same(41, $result['batches'][0]['after_schema_version']);
        $t->same(0, $result['wal']['frame_count']);
    };
}

foreach ([0, 1, 7, 99] as $dataVersion) {
    $tests["json schema current preserves rolled back data version {$dataVersion}"] = static function (TestRunner $t) use ($currentRows, $schemaRows, $jsonRows, $dataVersion): void {
        $result = SQLiteJsonSavepointSchemaCurrentPlan::plan($currentRows(), [
            [
                'name' => 'reject_data_' . $dataVersion,
                'json' => $jsonRows([
                    ['key_name' => 'reject_data_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [['action' => 'drop', 'name' => 'missing_' . $dataVersion]],
            ],
        ], [
            'schema_version' => 5,
            'data_version' => $dataVersion,
            'schema' => ['rows' => $schemaRows()],
        ]);

        $t->same($dataVersion, $result['data_version']);
        $t->same($dataVersion, $result['batches'][0]['after_data_version']);
        $t->same(5, $result['schema_version']);
    };
}

foreach ([['trigger', 0], ['view', 0], ['table', 44], ['index', 45]] as [$type, $rootpage]) {
    $tests["json schema current accepts {$type} schema change"] = static function (TestRunner $t) use ($plan, $jsonRows, $type, $rootpage): void {
        $name = 'app_' . $type . '_change';
        $sql = match ($type) {
            'trigger' => 'CREATE TRIGGER app_trigger_change AFTER INSERT ON app_settings BEGIN SELECT 1; END',
            'view' => 'CREATE VIEW app_view_change AS SELECT key_name FROM app_settings',
            'index' => 'CREATE INDEX app_index_change ON app_settings(load_policy)',
            default => 'CREATE TABLE app_table_change (id integer primary key)',
        };
        $result = $plan([
            [
                'name' => $type . '_change',
                'json' => $jsonRows([
                    ['key_name' => $type . '_change_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => $type,
                    'name' => $name,
                    'tbl_name' => $type === 'index' || $type === 'trigger' ? 'app_settings' : $name,
                    'rootpage' => $rootpage,
                    'sql' => $sql,
                ]],
            ],
        ]);

        $t->same([$name], $result['batches'][0]['schema']['created']);
        $t->same(42, $result['schema_version']);
        $t->same(true, in_array($name, $result['schema_names'], true));
    };
}

foreach ([2 => 2, 70 => 3, 129 => 4, 193 => 5, 257 => 6] as $optionId => $expectedRowPage) {
    $tests["json schema current update option {$optionId} keeps schema frame after row page"] = static function (TestRunner $t) use ($currentRows, $schemaRows, $jsonRows, $optionId, $expectedRowPage): void {
        $rows = $currentRows();
        $rows[] = ['setting_id' => $optionId, 'key_name' => 'option_' . $optionId . '_settings', 'key_value' => '{"old":true}', 'load_policy' => 'no'];
        $result = SQLiteJsonSavepointSchemaCurrentPlan::plan($rows, [
            [
                'name' => 'update_' . $optionId,
                'json' => $jsonRows([
                    ['setting_id' => $optionId, 'key_name' => 'option_' . $optionId . '_settings', 'key_value' => '{"new":true}', 'load_policy' => 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'app_update_' . $optionId,
                    'tbl_name' => 'app_update_' . $optionId,
                    'rootpage' => 60 + $expectedRowPage,
                    'sql' => 'CREATE TABLE app_update_' . $optionId . ' (id integer primary key)',
                ]],
            ],
        ], [
            'schema_version' => 9,
            'data_version' => 20,
            'schema' => ['rows' => $schemaRows()],
        ]);

        $t->same(1, $result['batches'][0]['wal_frames'][0]['frame_index']);
        $t->same($expectedRowPage, $result['batches'][0]['wal_frames'][0]['page_number']);
        $t->same(1, $result['batches'][0]['wal_frames'][1]['page_number']);
        $t->same(10, $result['schema_version']);
    };
}

return $tests;
