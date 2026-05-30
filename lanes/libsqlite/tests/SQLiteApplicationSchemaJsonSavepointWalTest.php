<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSchemaJsonSavepointWalPlan;

$currentRows = static fn (): array => [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'active_plugins', 'key_value' => '[]', 'load_policy' => 'yes'],
    ['setting_id' => 70, 'key_name' => 'theme_mods_old', 'key_value' => '{"color":"blue"}', 'load_policy' => 'no'],
];

$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
$plan = static fn (array $imports, array $options = []) => SQLiteSchemaJsonSavepointWalPlan::plan(
    $currentRows(),
    $imports,
    $options + ['database_path' => '/tmp/app-schema-json-savepoint.sqlite', 'page_size' => 1024],
);

$tests = [
    'releases schema-valid Application JSON rows into the current WAL frame' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'theme_schema', 'json' => $jsonRows([
                ['key_name' => 'theme_mods_modern', 'key_value' => '{"palette":["blue"]}', 'load_policy' => 'no'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('planned', $result['status']);
        $t->same(true, $result['schema_json_savepoint_wal']);
        $t->same(['theme_schema'], $result['released_batches']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'theme_mods_modern'], $result['final_key_names']);
        $t->same(1, $result['wal']['current_frame']);
    },
    'rolls schema-invalid JSON rows back without advancing WAL frames' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'bad_schema', 'json' => $jsonRows([
                ['key_name' => 'theme_mods_bad', 'key_value' => 'not json', 'load_policy' => 'no'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same(['bad_schema'], $result['rolled_back_batches']);
        $t->same(['bad_schema'], $result['schema_rejected_batches']);
        $t->same(0, $result['wal']['current_frame']);
        $t->same('json_text', $result['batches'][0]['json']['schema']['violations'][0]['rule']);
    },
    'preserves released rows when a later schema batch rolls back' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'release_first', 'json' => $jsonRows([
                ['key_name' => 'plugin_settings', 'key_value' => '{"enabled":true}', 'load_policy' => 'yes'],
            ]), 'path' => '$.rows'],
            ['name' => 'reject_second', 'json' => $jsonRows([
                ['key_name' => 'widget_recent', 'key_value' => 'broken', 'load_policy' => 'yes'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same(['release_first'], $result['released_batches']);
        $t->same(['reject_second'], $result['rolled_back_batches']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'plugin_settings'], $result['released_key_names']);
        $t->same(1, $result['wal']['current_frame']);
    },
    'keeps open schema-valid rows visible but unreleased' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'open_schema', 'json' => $jsonRows([
                ['key_name' => 'widget_sidebar', 'key_value' => '{"blocks":[]}', 'load_policy' => 'yes'],
            ]), 'path' => '$.rows', 'release' => false],
        ]);

        $t->same('open', $result['batches'][0]['status']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'widget_sidebar'], $result['final_key_names']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old'], $result['released_key_names']);
    },
    'accepts JSONB schema import payloads' => static function (TestRunner $t) use ($currentRows): void {
        $blob = new SQLiteBlobValue(SQLiteJsonB::encode(['rows' => [
            ['key_name' => 'jsonb_settings', 'key_value' => '{"mode":"fast"}', 'load_policy' => 'no'],
        ]]));
        $result = SQLiteSchemaJsonSavepointWalPlan::plan($currentRows(), [
            ['name' => 'jsonb_schema', 'json' => $blob, 'path' => '$.rows'],
        ], ['database_path' => '/tmp/app-schema-jsonb.sqlite']);

        $t->same(['jsonb_schema'], $result['released_batches']);
        $t->same(['jsonb_settings'], $result['batches'][0]['json']['key_names']);
        $t->same(1, $result['wal']['frame_count']);
    },
    'accepts JSON subtype schema import payloads' => static function (TestRunner $t) use ($currentRows): void {
        $subtype = new SQLiteJsonSubtypeValue('{"rows":[{"key_name":"subtype_settings","key_value":"{\"mode\":\"json\"}","load_policy":"no"}]}');
        $result = SQLiteSchemaJsonSavepointWalPlan::plan($currentRows(), [
            ['name' => 'subtype_schema', 'json' => $subtype, 'path' => '$.rows'],
        ], ['database_path' => '/tmp/app-schema-subtype.sqlite']);

        $t->same(['subtype_schema'], $result['released_batches']);
        $t->same(['subtype_settings'], $result['batches'][0]['json']['key_names']);
        $t->same(1, $result['wal']['frame_count']);
    },
    'reports malformed JSON as a savepoint rollback' => static function (TestRunner $t) use ($plan): void {
        $result = $plan([
            ['name' => 'malformed_schema', 'json' => '{"rows":[', 'path' => '$.rows'],
        ]);

        $t->same(['malformed_schema'], $result['rolled_back_batches']);
        $t->same(0, $result['wal']['current_frame']);
        $t->same('rolled_back', $result['batches'][0]['status']);
    },
    'rejects unsafe savepoint names' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([
            ['name' => 'bad-name', 'json' => '{"rows":[]}'],
        ]));
    },
    'rejects empty import lists' => static function (TestRunner $t) use ($currentRows): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaJsonSavepointWalPlan::plan($currentRows(), []));
    },
];

foreach (range(1, 20) as $batch) {
    $tests["schema-valid generated settings batch {$batch} advances WAL once"] = static function (TestRunner $t) use ($plan, $jsonRows, $batch): void {
        $result = $plan([
            ['name' => 'settings_' . $batch, 'json' => $jsonRows([
                ['key_name' => 'plugin_' . $batch . '_settings', 'key_value' => '{"rank":' . $batch . '}', 'load_policy' => $batch % 2 === 0 ? 'yes' : 'no'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('settings_' . $batch, $result['wal']['frames'][0]['savepoint']);
        $t->same(1, $result['batches'][0]['json']['schema']['accepted_rows']);
        $t->same(1, $result['wal']['current_frame']);
    };
}

foreach ([
    'missing option value' => [['key_name' => 'plugin_missing_settings']],
    'missing option name' => [['key_value' => '{"ok":true}']],
    'empty option name' => [['key_name' => '', 'key_value' => '{"ok":true}']],
    'unknown field' => [['key_name' => 'plugin_extra_settings', 'key_value' => '{"ok":true}', 'extra' => 1]],
    'bad load_policy' => [['key_name' => 'plugin_load_policy_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'maybe']],
    'bad JSON option text' => [['key_name' => 'widget_text', 'key_value' => 'not-json', 'load_policy' => 'yes']],
    'bad theme mods JSON text' => [['key_name' => 'theme_mods_modern', 'key_value' => '{bad', 'load_policy' => 'no']],
] as $label => $rows) {
    $tests["schema rejection rolls back {$label} at current WAL frame"] = static function (TestRunner $t) use ($plan, $jsonRows, $label, $rows): void {
        $result = $plan([
            ['name' => 'reject_' . preg_replace('/[^a-z0-9]+/', '_', $label), 'json' => $jsonRows($rows), 'path' => '$.rows'],
        ]);

        $t->same('rolled_back', $result['batches'][0]['status']);
        $t->same(0, $result['wal']['frame_count']);
        $t->same(1, $result['batches'][0]['json']['schema']['rejected_rows']);
    };
}

foreach ([
    'allows migration metadata when configured' => [
        ['key_name' => 'plugin_meta_settings', 'key_value' => '{"ok":true}', 'migration_source' => 'wxr'],
        ['allowed' => ['key_name', 'key_value', 'load_policy', 'migration_source']],
    ],
    'allows scalar values when JSON pattern is narrowed' => [
        ['key_name' => 'plugin_scalar_settings', 'key_value' => 'plain'],
        ['json_key_patterns' => ['/^theme_mods_/']],
    ],
    'allows narrowed load_policy enum' => [
        ['key_name' => 'plugin_auto_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'auto'],
        ['load_policy' => ['yes', 'no', 'auto']],
    ],
] as $label => [$row, $schema]) {
    $tests[$label] = static function (TestRunner $t) use ($plan, $jsonRows, $row, $schema): void {
        $result = $plan([
            ['name' => 'custom_schema', 'json' => $jsonRows([$row]), 'path' => '$.rows', 'schema' => $schema],
        ]);

        $t->same(['custom_schema'], $result['released_batches']);
        $t->same(1, $result['batches'][0]['json']['schema']['accepted_rows']);
        $t->same(1, $result['wal']['frame_count']);
    };
}

foreach ([64 => 2, 65 => 3, 128 => 3, 129 => 4, 192 => 4, 193 => 5, 256 => 5] as $optionId => $pageNumber) {
    $tests["schema update for option {$optionId} maps to WAL page {$pageNumber}"] = static function (TestRunner $t) use ($currentRows, $jsonRows, $optionId, $pageNumber): void {
        $rows = $currentRows();
        $rows[] = ['setting_id' => $optionId, 'key_name' => 'plugin_' . $optionId . '_settings', 'key_value' => '{"old":true}', 'load_policy' => 'no'];
        $result = SQLiteSchemaJsonSavepointWalPlan::plan($rows, [
            ['name' => 'update_' . $optionId, 'json' => $jsonRows([
                ['setting_id' => $optionId, 'key_name' => 'plugin_' . $optionId . '_settings', 'key_value' => '{"new":true}', 'load_policy' => 'no'],
            ]), 'path' => '$.rows'],
        ], ['database_path' => '/tmp/app-schema-update.sqlite']);

        $t->same([$pageNumber], $result['batches'][0]['dirty_pages']);
        $t->same($pageNumber, $result['wal']['frames'][0]['page_number']);
        $t->same(1, $result['batches'][0]['json']['schema']['accepted_rows']);
    };
}

foreach (['$', '$.rows', '$.payload.rows'] as $pathIndex => $path) {
    $tests["schema import extracts rows from {$path}"] = static function (TestRunner $t) use ($plan, $path, $pathIndex): void {
        $row = ['key_name' => 'path_' . $pathIndex . '_settings', 'key_value' => '{"path":' . $pathIndex . '}', 'load_policy' => 'no'];
        $json = match ($path) {
            '$' => json_encode([$row], JSON_THROW_ON_ERROR),
            '$.rows' => json_encode(['rows' => [$row]], JSON_THROW_ON_ERROR),
            default => json_encode(['payload' => ['rows' => [$row]]], JSON_THROW_ON_ERROR),
        };
        $result = $plan([
            ['name' => 'path_' . $pathIndex, 'json' => $json, 'path' => $path],
        ]);

        $t->same(['path_' . $pathIndex . '_settings'], $result['batches'][0]['json']['key_names']);
        $t->same(1, $result['batches'][0]['json']['schema']['accepted_rows']);
    };
}

foreach (['delete', 'truncate', 'persist', 'wal'] as $mode) {
    $tests["schema import preserves {$mode} journal option"] = static function (TestRunner $t) use ($plan, $jsonRows, $mode): void {
        $result = $plan([
            ['name' => 'mode_' . $mode, 'json' => $jsonRows([
                ['key_name' => 'mode_' . $mode . '_settings', 'key_value' => '{"mode":"' . $mode . '"}', 'load_policy' => 'no'],
            ]), 'path' => '$.rows'],
        ], ['journal_mode' => $mode, 'sync_mode' => 'normal']);

        $t->same(1, $result['wal']['current_frame']);
        $t->same('released', $result['batches'][0]['status']);
    };
}

$tests['schema dependencies use canonical names'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $result = $plan([
        ['name' => 'deps', 'json' => $jsonRows([
            ['key_name' => 'deps_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'no'],
        ]), 'path' => '$.rows'],
    ]);

    $t->same(true, in_array('sqlite-application-schema-json-savepoint-wal', $result['dependencies'], true));
};

return $tests;
