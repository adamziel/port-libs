<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteImportJsonSchemaSavepointPlan;

$currentRows = static fn (): array => [
    ['setting_id' => 1, 'key_name' => 'app_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'enabled_modules', 'key_value' => '[]', 'load_policy' => 'yes'],
    ['setting_id' => 70, 'key_name' => 'module_profile_old', 'key_value' => '{"color":"blue"}', 'load_policy' => 'no'],
];

$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
$plan = static fn (array $imports, array $options = []) => SQLiteImportJsonSchemaSavepointPlan::plan(
    $currentRows(),
    $imports,
    $options + ['database_path' => '/tmp/app-import-json-schema-savepoint.sqlite', 'page_size' => 1024],
);

$tests = [
    'applies schema defaults and generated ids before WAL savepoint import' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'defaults', 'json' => $jsonRows([
                ['name' => 'module_default_settings', 'value' => '{"enabled":true}'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('planned', $result['status']);
        $t->same(true, $result['schema_savepoint_import']);
        $t->same(['defaults'], $result['released_batches']);
        $t->same(['module_default_settings'], $result['batches'][0]['json']['key_names']);
        $t->same(71, $result['batches'][0]['schema_generated_ids'][0]['setting_id']);
    },
    'records current and next savepoint snapshots around generated import rows' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'snapshot', 'json' => $jsonRows([
                ['key_name' => 'snapshot_settings', 'key_value' => '{"snapshot":true}'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('snapshot', $result['batches'][0]['current_savepoint']['name']);
        $t->same(0, $result['batches'][0]['current_savepoint']['wal_frame']);
        $t->same('snapshot_next', $result['batches'][0]['next_savepoint']['name']);
        $t->same(1, $result['batches'][0]['next_savepoint']['wal_frame']);
        $t->same(true, in_array('snapshot_settings', $result['batches'][0]['next_savepoint']['key_names'], true));
    },
    'keeps open schema import rows visible but unreleased' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'open_schema', 'json' => $jsonRows([
                ['key_name' => 'open_schema_settings', 'key_value' => '{"open":true}'],
            ]), 'path' => '$.rows', 'release' => false],
        ]);

        $t->same('open', $result['batches'][0]['status']);
        $t->same(['app_url', 'enabled_modules', 'module_profile_old', 'open_schema_settings'], $result['final_key_names']);
        $t->same(['app_url', 'enabled_modules', 'module_profile_old'], $result['released_key_names']);
    },
    'rolls schema failures back without advancing current or next WAL frames' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'bad_schema', 'json' => $jsonRows([
                ['key_name' => 'component_recent', 'key_value' => 'not-json'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same(['bad_schema'], $result['schema_rejected_batches']);
        $t->same(0, $result['wal']['current_frame']);
        $t->same(0, $result['batches'][0]['next_savepoint']['wal_frame']);
        $t->same('json_text', $result['batches'][0]['json']['schema']['violations'][0]['rule']);
    },
    'preserves released batches when the next schema import rolls back' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'release_first', 'json' => $jsonRows([
                ['key_name' => 'module_one_settings', 'key_value' => '{"ok":true}'],
            ]), 'path' => '$.rows'],
            ['name' => 'reject_next', 'json' => $jsonRows([
                ['key_name' => 'module_bad', 'key_value' => '{bad'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same(['release_first'], $result['released_batches']);
        $t->same(['reject_next'], $result['rolled_back_batches']);
        $t->same(['app_url', 'enabled_modules', 'module_profile_old', 'module_one_settings'], $result['released_key_names']);
        $t->same(1, $result['wal']['current_frame']);
    },
    'reports replace-conflict schema imports separately from defaults' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'replace_app_url', 'json' => $jsonRows([
                ['setting_id' => 90, 'key_name' => 'app_url', 'key_value' => 'https://imported.test', 'load_policy' => 'yes'],
            ]), 'path' => '$.rows'],
        ], ['replace_conflicts' => true]);

        $t->same(1, $result['batches'][0]['deleted']);
        $t->same('app_url', $result['batches'][0]['schema_conflicts'][0]['key_name']);
        $t->same('delete_conflicting_current', $result['batches'][0]['schema_conflicts'][0]['action']);
        $t->same(true, in_array('app_url', $result['final_key_names'], true));
    },
    'rolls duplicate key-name conflicts back when replacement is disabled' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'conflict_no_replace', 'json' => $jsonRows([
                ['setting_id' => 90, 'key_name' => 'app_url', 'key_value' => 'https://duplicate.test', 'load_policy' => 'yes'],
            ]), 'path' => '$.rows'],
        ], ['replace_conflicts' => false]);

        $t->same(['conflict_no_replace'], $result['rolled_back_batches']);
        $t->same(0, $result['wal']['frame_count']);
        $t->same(['app_url', 'enabled_modules', 'module_profile_old'], $result['final_key_names']);
    },
    'accepts JSONB schema import sources' => static function (TestRunner $t) use ($currentRows): void {
        $blob = new SQLiteBlobValue(SQLiteJsonB::encode(['rows' => [
            ['key_name' => 'jsonb_schema_settings', 'key_value' => '{"mode":"jsonb"}'],
        ]]));
        $result = SQLiteImportJsonSchemaSavepointPlan::plan($currentRows(), [
            ['name' => 'jsonb_schema', 'json' => $blob, 'path' => '$.rows'],
        ]);

        $t->same(['jsonb_schema'], $result['released_batches']);
        $t->same(['jsonb_schema_settings'], $result['batches'][0]['json']['key_names']);
        $t->same(1, $result['wal']['frame_count']);
    },
    'accepts JSON subtype schema import sources' => static function (TestRunner $t) use ($currentRows): void {
        $subtype = new SQLiteJsonSubtypeValue('{"rows":[{"key_name":"subtype_schema_settings","key_value":"{\"mode\":\"subtype\"}"}]}');
        $result = SQLiteImportJsonSchemaSavepointPlan::plan($currentRows(), [
            ['name' => 'subtype_schema', 'json' => $subtype, 'path' => '$.rows'],
        ]);

        $t->same(['subtype_schema'], $result['released_batches']);
        $t->same(['subtype_schema_settings'], $result['batches'][0]['json']['key_names']);
        $t->same(1, $result['wal']['frame_count']);
    },
    'rejects malformed JSON sources as savepoint rollbacks' => static function (TestRunner $t) use ($plan): void {
        $result = $plan([
            ['name' => 'malformed_source', 'json' => '{"rows":[', 'path' => '$.rows'],
        ]);

        $t->same(['malformed_source'], $result['rolled_back_batches']);
        $t->same('rolled_back', $result['batches'][0]['status']);
        $t->same(0, $result['wal']['frame_count']);
    },
    'rejects unsafe savepoint names' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([
            ['name' => 'bad-name', 'json' => '{"rows":[]}'],
        ]));
    },
    'rejects empty import lists' => static function (TestRunner $t) use ($currentRows): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteImportJsonSchemaSavepointPlan::plan($currentRows(), []));
    },
];

foreach (range(1, 18) as $batch) {
    $tests["generated schema import batch {$batch} advances schema-savepoint frame once"] = static function (TestRunner $t) use ($plan, $jsonRows, $batch): void {
        $result = $plan([
            ['name' => 'generated_' . $batch, 'json' => $jsonRows([
                ['name' => 'generated_' . $batch . '_settings', 'value' => '{"rank":' . $batch . '}'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('generated_' . $batch, $result['wal']['frames'][0]['savepoint']);
        $t->same(1, $result['wal']['current_frame']);
        $t->same('load_policy', $result['batches'][0]['schema_defaulted_fields'][1]['field']);
    };
}

foreach ([
    'missing setting value' => [['key_name' => 'missing_settings']],
    'empty setting name' => [['key_name' => '', 'key_value' => '{"ok":true}']],
    'unknown field' => [['key_name' => 'unknown_settings', 'key_value' => '{"ok":true}', 'extra' => true]],
    'bad load_policy' => [['key_name' => 'bad_load_policy_settings', 'key_value' => '{"ok":true}', 'load_policy' => 'maybe']],
    'bad component json text' => [['key_name' => 'component_text', 'key_value' => 'plain']],
    'bad module json text' => [['key_name' => 'module_current', 'key_value' => '{bad']],
] as $label => $rows) {
    $tests["schema savepoint rollback for {$label}"] = static function (TestRunner $t) use ($plan, $jsonRows, $label, $rows): void {
        $result = $plan([
            ['name' => 'reject_' . preg_replace('/[^a-z0-9]+/', '_', $label), 'json' => $jsonRows($rows), 'path' => '$.rows'],
        ]);

        $t->same('rolled_back', $result['batches'][0]['status']);
        $t->same(1, $result['batches'][0]['json']['schema']['rejected_rows']);
        $t->same(0, $result['batches'][0]['wal_current_frame']);
    };
}

foreach ([
    'allows migration metadata when configured' => [
        ['key_name' => 'migration_meta_settings', 'key_value' => '{"ok":true}', 'migration_source' => 'archive'],
        ['allowed' => ['setting_id', 'key_name', 'key_value', 'load_policy', 'migration_source']],
    ],
    'allows scalar component values when JSON patterns are narrowed' => [
        ['key_name' => 'component_plain_settings', 'key_value' => 'plain'],
        ['json_key_patterns' => ['/^module_/']],
    ],
    'uses configured load_policy default' => [
        ['key_name' => 'default_auto_settings', 'key_value' => '{"ok":true}'],
        ['defaults' => ['load_policy' => 'auto']],
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

foreach ([64 => 2, 65 => 3, 128 => 3, 129 => 4, 192 => 4, 193 => 5] as $settingId => $pageNumber) {
    $tests["explicit schema setting {$settingId} maps to WAL page {$pageNumber}"] = static function (TestRunner $t) use ($currentRows, $jsonRows, $settingId, $pageNumber): void {
        $rows = $currentRows();
        $result = SQLiteImportJsonSchemaSavepointPlan::plan($rows, [
            ['name' => 'explicit_' . $settingId, 'json' => $jsonRows([
                ['setting_id' => $settingId, 'key_name' => 'explicit_' . $settingId . '_settings', 'key_value' => '{"id":' . $settingId . '}'],
            ]), 'path' => '$.rows'],
        ], ['database_path' => '/tmp/app-schema-explicit-savepoint.sqlite']);

        $t->same([$pageNumber], $result['batches'][0]['dirty_pages']);
        $t->same($pageNumber, $result['wal']['frames'][0]['page_number']);
        $t->same($settingId, $result['batches'][0]['schema_generated_ids'][0]['setting_id']);
    };
}

foreach (['$', '$.rows', '$.payload.rows'] as $pathIndex => $path) {
    $tests["schema import savepoint extracts rows from {$path}"] = static function (TestRunner $t) use ($plan, $path, $pathIndex): void {
        $row = ['key_name' => 'path_' . $pathIndex . '_settings', 'key_value' => '{"path":' . $pathIndex . '}'];
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

$tests['dependency marker names schema savepoint import'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $result = $plan([
        ['name' => 'deps', 'json' => $jsonRows([
            ['key_name' => 'deps_settings', 'key_value' => '{"ok":true}'],
        ]), 'path' => '$.rows'],
    ]);

    $t->same(true, in_array('sqlite-application-import-json-schema-savepoint', $result['dependencies'], true));
    $t->same(true, $result['wal']['schema_savepoint_import']);
};

return $tests;
