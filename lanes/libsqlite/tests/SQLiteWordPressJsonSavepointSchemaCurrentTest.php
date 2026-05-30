<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonSavepointSchemaCurrentPlan;

$currentRows = static fn (): array => [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => '[]', 'autoload' => 'yes'],
    ['option_id' => 70, 'option_name' => 'theme_mods_old', 'option_value' => '{"color":"blue"}', 'autoload' => 'no'],
];

$schemaRows = static fn (): array => [
    ['type' => 'table', 'name' => 'wp_options', 'tbl_name' => 'wp_options', 'rootpage' => 2, 'sql' => 'CREATE TABLE wp_options (option_id integer primary key, option_name text unique, option_value text, autoload text)'],
    ['type' => 'index', 'name' => 'option_name', 'tbl_name' => 'wp_options', 'rootpage' => 3, 'sql' => 'CREATE UNIQUE INDEX option_name ON wp_options(option_name)'],
];

$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
$plan = static fn (array $batches, array $options = []) => SQLiteJsonSavepointSchemaCurrentPlan::plan(
    $currentRows(),
    $batches,
    $options + [
        'database_path' => '/tmp/wp-json-savepoint-schema-current.sqlite',
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
                    ['option_name' => 'plugin_created_settings', 'option_value' => '{"ok":true}', 'autoload' => 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'wp_import_batch',
                    'tbl_name' => 'wp_import_batch',
                    'rootpage' => 8,
                    'sql' => 'CREATE TABLE wp_import_batch (id integer primary key, payload text)',
                ]],
            ],
        ]);

        $t->same('planned', $result['status']);
        $t->same(true, $result['schema_current']);
        $t->same(42, $result['schema_version']);
        $t->same(302, $result['data_version']);
        $t->same(42, $result['batches'][0]['schema_cookie_frame']['schema_cookie']);
        $t->same(['option_name', 'wp_import_batch', 'wp_options'], $result['schema_names']);
    },
    'json schema current rolls back duplicate schema name at current frame' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            [
                'name' => 'duplicate_schema',
                'json' => $jsonRows([
                    ['option_name' => 'plugin_dup_settings', 'option_value' => '{"ok":true}', 'autoload' => 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'wp_options',
                    'tbl_name' => 'wp_options',
                    'rootpage' => 9,
                    'sql' => 'CREATE TABLE wp_options (id integer)',
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
                    ['option_name' => 'plugin_release_settings', 'option_value' => '{"ok":true}', 'autoload' => 'yes'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'wp_release_batch',
                    'tbl_name' => 'wp_release_batch',
                    'rootpage' => 10,
                    'sql' => 'CREATE TABLE wp_release_batch (id integer primary key)',
                ]],
            ],
            [
                'name' => 'reject_index',
                'json' => $jsonRows([
                    ['option_name' => 'plugin_reject_settings', 'option_value' => '{"ok":true}', 'autoload' => 'yes'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'index',
                    'name' => 'bad_index',
                    'tbl_name' => 'wp_options',
                    'rootpage' => 0,
                    'sql' => 'CREATE INDEX bad_index ON wp_options(option_name)',
                ]],
            ],
        ]);

        $t->same(['release_table'], $result['released_batches']);
        $t->same(['reject_index'], $result['rolled_back_batches']);
        $t->same(['option_name', 'wp_options', 'wp_release_batch'], $result['released_schema_names']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'plugin_release_settings'], $result['released_option_names']);
        $t->same(42, $result['schema_version']);
    },
    'json schema current keeps open schema visible but unreleased' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            [
                'name' => 'open_schema',
                'json' => $jsonRows([
                    ['option_name' => 'widget_open_settings', 'option_value' => '{"blocks":[]}', 'autoload' => 'yes'],
                ]),
                'path' => '$.rows',
                'release' => false,
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'wp_open_batch',
                    'tbl_name' => 'wp_open_batch',
                    'rootpage' => 11,
                    'sql' => 'CREATE TABLE wp_open_batch (id integer primary key)',
                ]],
            ],
        ]);

        $t->same('open', $result['batches'][0]['status']);
        $t->same(['option_name', 'wp_open_batch', 'wp_options'], $result['schema_names']);
        $t->same(['option_name', 'wp_options'], $result['released_schema_names']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old'], $result['released_option_names']);
    },
    'json schema current drop schema row increments cookie' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            [
                'name' => 'drop_index',
                'json' => $jsonRows([
                    ['option_name' => 'plugin_drop_settings', 'option_value' => '{"ok":true}', 'autoload' => 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [['action' => 'drop', 'name' => 'option_name']],
            ],
        ]);

        $t->same(['option_name'], $result['batches'][0]['schema']['dropped']);
        $t->same(['wp_options'], $result['schema_names']);
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
                    'name' => 'wp_bad_json',
                    'tbl_name' => 'wp_bad_json',
                    'rootpage' => 12,
                    'sql' => 'CREATE TABLE wp_bad_json (id integer primary key)',
                ]],
            ],
        ]);

        $t->same(['bad_json'], $result['rolled_back_batches']);
        $t->same(41, $result['schema_version']);
        $t->same(null, $result['batches'][0]['schema_cookie_frame']);
        $t->same(['option_name', 'wp_options'], $result['schema_names']);
    },
    'json schema current dependency marker is present' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'deps', 'json' => $jsonRows([
                ['option_name' => 'deps_settings', 'option_value' => '{"ok":true}', 'autoload' => 'no'],
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
                ['option_name' => 'bad_name_settings', 'option_value' => '{"ok":true}', 'autoload' => 'no'],
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
                    ['option_name' => 'generated_' . $index . '_settings', 'option_value' => '{"rank":' . $index . '}', 'autoload' => $index % 2 === 0 ? 'yes' : 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'wp_generated_' . $index,
                    'tbl_name' => 'wp_generated_' . $index,
                    'rootpage' => 20 + $index,
                    'sql' => 'CREATE TABLE wp_generated_' . $index . ' (id integer primary key, option_id integer)',
                ]],
            ],
        ]);

        $t->same(42, $result['batches'][0]['after_schema_version']);
        $t->same('wp_generated_' . $index, $result['batches'][0]['schema']['created'][0]);
        $t->same(2, $result['wal']['frame_count']);
    };
}

foreach ([
    'missing drop target' => [['action' => 'drop', 'name' => 'missing_table'], 'drop_existing'],
    'bad action' => [['action' => 'rename', 'name' => 'wp_rename'], 'action'],
    'zero table rootpage' => [['type' => 'table', 'name' => 'wp_zero', 'tbl_name' => 'wp_zero', 'rootpage' => 0, 'sql' => 'CREATE TABLE wp_zero (id integer)'], 'rootpage_positive'],
    'zero index rootpage' => [['type' => 'index', 'name' => 'wp_zero_idx', 'tbl_name' => 'wp_options', 'rootpage' => 0, 'sql' => 'CREATE INDEX wp_zero_idx ON wp_options(option_name)'], 'rootpage_positive'],
    'invalid create sql' => [['type' => 'view', 'name' => 'wp_bad_view', 'tbl_name' => 'wp_bad_view', 'rootpage' => 0, 'sql' => 'SELECT 1'], 'schema_row'],
    'invalid row type' => [['type' => 'virtual', 'name' => 'wp_virtual', 'tbl_name' => 'wp_virtual', 'rootpage' => 15, 'sql' => 'CREATE VIRTUAL TABLE wp_virtual USING fts5(body)'], 'schema_row'],
] as $label => [$change, $rule]) {
    $tests["json schema current rolls back {$label}"] = static function (TestRunner $t) use ($plan, $jsonRows, $label, $change, $rule): void {
        $result = $plan([
            [
                'name' => 'reject_' . preg_replace('/[^a-z0-9]+/', '_', $label),
                'json' => $jsonRows([
                    ['option_name' => 'reject_' . preg_replace('/[^a-z0-9]+/', '_', $label) . '_settings', 'option_value' => '{"ok":true}', 'autoload' => 'no'],
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
                    ['option_name' => 'reject_data_settings', 'option_value' => '{"ok":true}', 'autoload' => 'no'],
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
        $name = 'wp_' . $type . '_change';
        $sql = match ($type) {
            'trigger' => 'CREATE TRIGGER wp_trigger_change AFTER INSERT ON wp_options BEGIN SELECT 1; END',
            'view' => 'CREATE VIEW wp_view_change AS SELECT option_name FROM wp_options',
            'index' => 'CREATE INDEX wp_index_change ON wp_options(autoload)',
            default => 'CREATE TABLE wp_table_change (id integer primary key)',
        };
        $result = $plan([
            [
                'name' => $type . '_change',
                'json' => $jsonRows([
                    ['option_name' => $type . '_change_settings', 'option_value' => '{"ok":true}', 'autoload' => 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => $type,
                    'name' => $name,
                    'tbl_name' => $type === 'index' || $type === 'trigger' ? 'wp_options' : $name,
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
        $rows[] = ['option_id' => $optionId, 'option_name' => 'option_' . $optionId . '_settings', 'option_value' => '{"old":true}', 'autoload' => 'no'];
        $result = SQLiteJsonSavepointSchemaCurrentPlan::plan($rows, [
            [
                'name' => 'update_' . $optionId,
                'json' => $jsonRows([
                    ['option_id' => $optionId, 'option_name' => 'option_' . $optionId . '_settings', 'option_value' => '{"new":true}', 'autoload' => 'no'],
                ]),
                'path' => '$.rows',
                'schema_changes' => [[
                    'type' => 'table',
                    'name' => 'wp_update_' . $optionId,
                    'tbl_name' => 'wp_update_' . $optionId,
                    'rootpage' => 60 + $expectedRowPage,
                    'sql' => 'CREATE TABLE wp_update_' . $optionId . ' (id integer primary key)',
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
