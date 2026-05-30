<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSchemaJsonSavepointWalPlan;

$currentRows = static fn (): array => [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => '[]', 'autoload' => 'yes'],
    ['option_id' => 70, 'option_name' => 'theme_mods_old', 'option_value' => '{"color":"blue"}', 'autoload' => 'no'],
];

$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
$plan = static fn (array $imports, array $options = []) => SQLiteSchemaJsonSavepointWalPlan::plan(
    $currentRows(),
    $imports,
    $options + ['database_path' => '/tmp/wp-schema-json-savepoint.sqlite', 'page_size' => 1024],
);

$tests = [
    'releases schema-valid Application JSON rows into the current WAL frame' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'theme_schema', 'json' => $jsonRows([
                ['option_name' => 'theme_mods_modern', 'option_value' => '{"palette":["blue"]}', 'autoload' => 'no'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('planned', $result['status']);
        $t->same(true, $result['schema_json_savepoint_wal']);
        $t->same(['theme_schema'], $result['released_batches']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'theme_mods_modern'], $result['final_option_names']);
        $t->same(1, $result['wal']['current_frame']);
    },
    'rolls schema-invalid JSON rows back without advancing WAL frames' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'bad_schema', 'json' => $jsonRows([
                ['option_name' => 'theme_mods_bad', 'option_value' => 'not json', 'autoload' => 'no'],
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
                ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true}', 'autoload' => 'yes'],
            ]), 'path' => '$.rows'],
            ['name' => 'reject_second', 'json' => $jsonRows([
                ['option_name' => 'widget_recent', 'option_value' => 'broken', 'autoload' => 'yes'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same(['release_first'], $result['released_batches']);
        $t->same(['reject_second'], $result['rolled_back_batches']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'plugin_settings'], $result['released_option_names']);
        $t->same(1, $result['wal']['current_frame']);
    },
    'keeps open schema-valid rows visible but unreleased' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'open_schema', 'json' => $jsonRows([
                ['option_name' => 'widget_sidebar', 'option_value' => '{"blocks":[]}', 'autoload' => 'yes'],
            ]), 'path' => '$.rows', 'release' => false],
        ]);

        $t->same('open', $result['batches'][0]['status']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'widget_sidebar'], $result['final_option_names']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old'], $result['released_option_names']);
    },
    'accepts JSONB schema import payloads' => static function (TestRunner $t) use ($currentRows): void {
        $blob = new SQLiteBlobValue(SQLiteJsonB::encode(['rows' => [
            ['option_name' => 'jsonb_settings', 'option_value' => '{"mode":"fast"}', 'autoload' => 'no'],
        ]]));
        $result = SQLiteSchemaJsonSavepointWalPlan::plan($currentRows(), [
            ['name' => 'jsonb_schema', 'json' => $blob, 'path' => '$.rows'],
        ], ['database_path' => '/tmp/wp-schema-jsonb.sqlite']);

        $t->same(['jsonb_schema'], $result['released_batches']);
        $t->same(['jsonb_settings'], $result['batches'][0]['json']['option_names']);
        $t->same(1, $result['wal']['frame_count']);
    },
    'accepts JSON subtype schema import payloads' => static function (TestRunner $t) use ($currentRows): void {
        $subtype = new SQLiteJsonSubtypeValue('{"rows":[{"option_name":"subtype_settings","option_value":"{\"mode\":\"json\"}","autoload":"no"}]}');
        $result = SQLiteSchemaJsonSavepointWalPlan::plan($currentRows(), [
            ['name' => 'subtype_schema', 'json' => $subtype, 'path' => '$.rows'],
        ], ['database_path' => '/tmp/wp-schema-subtype.sqlite']);

        $t->same(['subtype_schema'], $result['released_batches']);
        $t->same(['subtype_settings'], $result['batches'][0]['json']['option_names']);
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
                ['option_name' => 'plugin_' . $batch . '_settings', 'option_value' => '{"rank":' . $batch . '}', 'autoload' => $batch % 2 === 0 ? 'yes' : 'no'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('settings_' . $batch, $result['wal']['frames'][0]['savepoint']);
        $t->same(1, $result['batches'][0]['json']['schema']['accepted_rows']);
        $t->same(1, $result['wal']['current_frame']);
    };
}

foreach ([
    'missing option value' => [['option_name' => 'plugin_missing_settings']],
    'missing option name' => [['option_value' => '{"ok":true}']],
    'empty option name' => [['option_name' => '', 'option_value' => '{"ok":true}']],
    'unknown field' => [['option_name' => 'plugin_extra_settings', 'option_value' => '{"ok":true}', 'extra' => 1]],
    'bad autoload' => [['option_name' => 'plugin_autoload_settings', 'option_value' => '{"ok":true}', 'autoload' => 'maybe']],
    'bad JSON option text' => [['option_name' => 'widget_text', 'option_value' => 'not-json', 'autoload' => 'yes']],
    'bad theme mods JSON text' => [['option_name' => 'theme_mods_modern', 'option_value' => '{bad', 'autoload' => 'no']],
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
        ['option_name' => 'plugin_meta_settings', 'option_value' => '{"ok":true}', 'migration_source' => 'wxr'],
        ['allowed' => ['option_name', 'option_value', 'autoload', 'migration_source']],
    ],
    'allows scalar values when JSON pattern is narrowed' => [
        ['option_name' => 'plugin_scalar_settings', 'option_value' => 'plain'],
        ['json_option_patterns' => ['/^theme_mods_/']],
    ],
    'allows narrowed autoload enum' => [
        ['option_name' => 'plugin_auto_settings', 'option_value' => '{"ok":true}', 'autoload' => 'auto'],
        ['autoload' => ['yes', 'no', 'auto']],
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
        $rows[] = ['option_id' => $optionId, 'option_name' => 'plugin_' . $optionId . '_settings', 'option_value' => '{"old":true}', 'autoload' => 'no'];
        $result = SQLiteSchemaJsonSavepointWalPlan::plan($rows, [
            ['name' => 'update_' . $optionId, 'json' => $jsonRows([
                ['option_id' => $optionId, 'option_name' => 'plugin_' . $optionId . '_settings', 'option_value' => '{"new":true}', 'autoload' => 'no'],
            ]), 'path' => '$.rows'],
        ], ['database_path' => '/tmp/wp-schema-update.sqlite']);

        $t->same([$pageNumber], $result['batches'][0]['dirty_pages']);
        $t->same($pageNumber, $result['wal']['frames'][0]['page_number']);
        $t->same(1, $result['batches'][0]['json']['schema']['accepted_rows']);
    };
}

foreach (['$', '$.rows', '$.payload.rows'] as $pathIndex => $path) {
    $tests["schema import extracts rows from {$path}"] = static function (TestRunner $t) use ($plan, $path, $pathIndex): void {
        $row = ['option_name' => 'path_' . $pathIndex . '_settings', 'option_value' => '{"path":' . $pathIndex . '}', 'autoload' => 'no'];
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

foreach (['delete', 'truncate', 'persist', 'wal'] as $mode) {
    $tests["schema import preserves {$mode} journal option"] = static function (TestRunner $t) use ($plan, $jsonRows, $mode): void {
        $result = $plan([
            ['name' => 'mode_' . $mode, 'json' => $jsonRows([
                ['option_name' => 'mode_' . $mode . '_settings', 'option_value' => '{"mode":"' . $mode . '"}', 'autoload' => 'no'],
            ]), 'path' => '$.rows'],
        ], ['journal_mode' => $mode, 'sync_mode' => 'normal']);

        $t->same(1, $result['wal']['current_frame']);
        $t->same('released', $result['batches'][0]['status']);
    };
}

$tests['schema dependencies use canonical names'] = static function (TestRunner $t) use ($plan, $jsonRows): void {
    $result = $plan([
        ['name' => 'deps', 'json' => $jsonRows([
            ['option_name' => 'deps_settings', 'option_value' => '{"ok":true}', 'autoload' => 'no'],
        ]), 'path' => '$.rows'],
    ]);

    $t->same(true, in_array('sqlite-application-schema-json-savepoint-wal', $result['dependencies'], true));
};

return $tests;
