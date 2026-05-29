<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteWordPressImportJsonSchemaSavepointPlan;

$currentRows = static fn (): array => [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => '[]', 'autoload' => 'yes'],
    ['option_id' => 70, 'option_name' => 'theme_mods_old', 'option_value' => '{"color":"blue"}', 'autoload' => 'no'],
];

$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
$plan = static fn (array $imports, array $options = []) => SQLiteWordPressImportJsonSchemaSavepointPlan::plan(
    $currentRows(),
    $imports,
    $options + ['database_path' => '/tmp/wp-import-json-schema-savepoint.sqlite', 'page_size' => 1024],
);

$tests = [
    'applies schema defaults and generated ids before WAL savepoint import' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'defaults', 'json' => $jsonRows([
                ['name' => 'plugin_default_settings', 'value' => '{"enabled":true}'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('planned', $result['status']);
        $t->same(true, $result['schema_savepoint_import']);
        $t->same(['defaults'], $result['released_batches']);
        $t->same(['plugin_default_settings'], $result['batches'][0]['json']['option_names']);
        $t->same(71, $result['batches'][0]['schema_generated_ids'][0]['option_id']);
    },
    'records current and next savepoint snapshots around generated import rows' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'snapshot', 'json' => $jsonRows([
                ['option_name' => 'snapshot_settings', 'option_value' => '{"snapshot":true}'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('snapshot', $result['batches'][0]['current_savepoint']['name']);
        $t->same(0, $result['batches'][0]['current_savepoint']['wal_frame']);
        $t->same('snapshot_next', $result['batches'][0]['next_savepoint']['name']);
        $t->same(1, $result['batches'][0]['next_savepoint']['wal_frame']);
        $t->same(true, in_array('snapshot_settings', $result['batches'][0]['next_savepoint']['option_names'], true));
    },
    'keeps open schema import rows visible but unreleased' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'open_schema', 'json' => $jsonRows([
                ['option_name' => 'open_schema_settings', 'option_value' => '{"open":true}'],
            ]), 'path' => '$.rows', 'release' => false],
        ]);

        $t->same('open', $result['batches'][0]['status']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'open_schema_settings'], $result['final_option_names']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old'], $result['released_option_names']);
    },
    'rolls schema failures back without advancing current or next WAL frames' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'bad_schema', 'json' => $jsonRows([
                ['option_name' => 'widget_recent', 'option_value' => 'not-json'],
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
                ['option_name' => 'plugin_one_settings', 'option_value' => '{"ok":true}'],
            ]), 'path' => '$.rows'],
            ['name' => 'reject_next', 'json' => $jsonRows([
                ['option_name' => 'theme_mods_bad', 'option_value' => '{bad'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same(['release_first'], $result['released_batches']);
        $t->same(['reject_next'], $result['rolled_back_batches']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'plugin_one_settings'], $result['released_option_names']);
        $t->same(1, $result['wal']['current_frame']);
    },
    'reports replace-conflict schema imports separately from defaults' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'replace_siteurl', 'json' => $jsonRows([
                ['option_id' => 90, 'option_name' => 'siteurl', 'option_value' => 'https://imported.test', 'autoload' => 'yes'],
            ]), 'path' => '$.rows'],
        ], ['replace_conflicts' => true]);

        $t->same(1, $result['batches'][0]['deleted']);
        $t->same('siteurl', $result['batches'][0]['schema_conflicts'][0]['option_name']);
        $t->same('delete_conflicting_current', $result['batches'][0]['schema_conflicts'][0]['action']);
        $t->same(true, in_array('siteurl', $result['final_option_names'], true));
    },
    'rolls duplicate option-name conflicts back when replacement is disabled' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'conflict_no_replace', 'json' => $jsonRows([
                ['option_id' => 90, 'option_name' => 'siteurl', 'option_value' => 'https://duplicate.test', 'autoload' => 'yes'],
            ]), 'path' => '$.rows'],
        ], ['replace_conflicts' => false]);

        $t->same(['conflict_no_replace'], $result['rolled_back_batches']);
        $t->same(0, $result['wal']['frame_count']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old'], $result['final_option_names']);
    },
    'accepts JSONB schema import sources' => static function (TestRunner $t) use ($currentRows): void {
        $blob = new SQLiteBlobValue(SQLiteJsonB::encode(['rows' => [
            ['option_name' => 'jsonb_schema_settings', 'option_value' => '{"mode":"jsonb"}'],
        ]]));
        $result = SQLiteWordPressImportJsonSchemaSavepointPlan::plan($currentRows(), [
            ['name' => 'jsonb_schema', 'json' => $blob, 'path' => '$.rows'],
        ]);

        $t->same(['jsonb_schema'], $result['released_batches']);
        $t->same(['jsonb_schema_settings'], $result['batches'][0]['json']['option_names']);
        $t->same(1, $result['wal']['frame_count']);
    },
    'accepts JSON subtype schema import sources' => static function (TestRunner $t) use ($currentRows): void {
        $subtype = new SQLiteJsonSubtypeValue('{"rows":[{"option_name":"subtype_schema_settings","option_value":"{\"mode\":\"subtype\"}"}]}');
        $result = SQLiteWordPressImportJsonSchemaSavepointPlan::plan($currentRows(), [
            ['name' => 'subtype_schema', 'json' => $subtype, 'path' => '$.rows'],
        ]);

        $t->same(['subtype_schema'], $result['released_batches']);
        $t->same(['subtype_schema_settings'], $result['batches'][0]['json']['option_names']);
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
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWordPressImportJsonSchemaSavepointPlan::plan($currentRows(), []));
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
        $t->same('autoload', $result['batches'][0]['schema_defaulted_fields'][1]['field']);
    };
}

foreach ([
    'missing option value' => [['option_name' => 'missing_settings']],
    'empty option name' => [['option_name' => '', 'option_value' => '{"ok":true}']],
    'unknown field' => [['option_name' => 'unknown_settings', 'option_value' => '{"ok":true}', 'extra' => true]],
    'bad autoload' => [['option_name' => 'bad_autoload_settings', 'option_value' => '{"ok":true}', 'autoload' => 'maybe']],
    'bad widget json text' => [['option_name' => 'widget_text', 'option_value' => 'plain']],
    'bad theme mods json text' => [['option_name' => 'theme_mods_current', 'option_value' => '{bad']],
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
        ['option_name' => 'migration_meta_settings', 'option_value' => '{"ok":true}', 'migration_source' => 'wxr'],
        ['allowed' => ['option_id', 'option_name', 'option_value', 'autoload', 'migration_source']],
    ],
    'allows scalar plugin values when JSON patterns are narrowed' => [
        ['option_name' => 'plugin_plain_settings', 'option_value' => 'plain'],
        ['json_option_patterns' => ['/^theme_mods_/']],
    ],
    'uses configured autoload default' => [
        ['option_name' => 'default_auto_settings', 'option_value' => '{"ok":true}'],
        ['defaults' => ['autoload' => 'auto']],
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

foreach ([64 => 2, 65 => 3, 128 => 3, 129 => 4, 192 => 4, 193 => 5] as $optionId => $pageNumber) {
    $tests["explicit schema option {$optionId} maps to WAL page {$pageNumber}"] = static function (TestRunner $t) use ($currentRows, $jsonRows, $optionId, $pageNumber): void {
        $rows = $currentRows();
        $result = SQLiteWordPressImportJsonSchemaSavepointPlan::plan($rows, [
            ['name' => 'explicit_' . $optionId, 'json' => $jsonRows([
                ['option_id' => $optionId, 'option_name' => 'explicit_' . $optionId . '_settings', 'option_value' => '{"id":' . $optionId . '}'],
            ]), 'path' => '$.rows'],
        ], ['database_path' => '/tmp/wp-schema-explicit-savepoint.sqlite']);

        $t->same([$pageNumber], $result['batches'][0]['dirty_pages']);
        $t->same($pageNumber, $result['wal']['frames'][0]['page_number']);
        $t->same($optionId, $result['batches'][0]['schema_generated_ids'][0]['option_id']);
    };
}

foreach (['$', '$.rows', '$.payload.rows'] as $pathIndex => $path) {
    $tests["schema import savepoint extracts rows from {$path}"] = static function (TestRunner $t) use ($plan, $path, $pathIndex): void {
        $row = ['option_name' => 'path_' . $pathIndex . '_settings', 'option_value' => '{"path":' . $pathIndex . '}'];
        $json = match ($path) {
            '$' => json_encode([$row], JSON_THROW_ON_ERROR),
            '$.rows' => json_encode(['rows' => [$row]], JSON_THROW_ON_ERROR),
            default => json_encode(['payload' => ['rows' => [$row]]], JSON_THROW_ON_ERROR),
        };
        $result = $plan([
            ['name' => 'path_' . $pathIndex, 'json' => $json, 'path' => $path],
        ]);

        $t->same(['path_' . $pathIndex . '_settings'], $result['batches'][0]['json']['option_names']);
        $t->same(1, $result['batches'][0]['json']['schema']['accepted_rows']);
    };
}

$tests['dependency marker names schema savepoint import'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $result = $plan([
        ['name' => 'deps', 'json' => $jsonRows([
            ['option_name' => 'deps_settings', 'option_value' => '{"ok":true}'],
        ]), 'path' => '$.rows'],
    ]);

    $t->same(true, in_array('sqlite-wordpress-import-json-schema-savepoint', $result['dependencies'], true));
    $t->same(true, $result['wal']['schema_savepoint_import']);
};

return $tests;
