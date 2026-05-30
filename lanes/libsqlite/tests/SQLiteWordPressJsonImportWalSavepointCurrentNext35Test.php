<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonImportWalSavepointPlan;

$currentRows = static fn (): array => [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => '[]', 'autoload' => 'yes'],
    ['option_id' => 70, 'option_name' => 'theme_mods_old', 'option_value' => '{"color":"blue"}', 'autoload' => 'no'],
];

$plan = static fn (array $imports, array $options = []) => SQLiteJsonImportWalSavepointPlan::plan(
    $currentRows(),
    $imports,
    $options + ['database_path' => '/tmp/wp-json-import-current-next35.sqlite', 'page_size' => 1024],
);

$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);

$tests = [
    'plans released json option imports as WAL frames' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'plugins', 'json' => $jsonRows([
                ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true}', 'autoload' => 'yes'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('planned', $result['status']);
        $t->same(['plugins'], $result['released_batches']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'plugin_settings'], $result['final_option_names']);
        $t->same(1, $result['wal']['frame_count']);
    },
    'updates current JSON option rows on the original page' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'active', 'json' => $jsonRows([
                ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => '["akismet/akismet.php"]', 'autoload' => 'yes'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same(1, $result['batches'][0]['updated']);
        $t->same([2], $result['batches'][0]['dirty_pages']);
        $t->same(2, $result['wal']['frames'][0]['page_number']);
    },
    'rolls malformed JSON imports back to the current WAL frame' => static function (TestRunner $t) use ($plan): void {
        $result = $plan([
            ['name' => 'bad_json', 'json' => '{"rows":[', 'path' => '$.rows'],
        ]);

        $t->same(['bad_json'], $result['rolled_back_batches']);
        $t->same(0, $result['wal']['current_frame']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old'], $result['final_option_names']);
        $t->same('rolled_back', $result['batches'][0]['status']);
    },
    'preserves released rows when later JSON savepoint rolls back' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'first', 'json' => $jsonRows([
                ['option_name' => 'plugin_one', 'option_value' => '{"enabled":true}'],
            ]), 'path' => '$.rows'],
            ['name' => 'second', 'json' => '{"rows":[', 'path' => '$.rows'],
        ]);

        $t->same(['first'], $result['released_batches']);
        $t->same(['second'], $result['rolled_back_batches']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'plugin_one'], $result['released_option_names']);
        $t->same(1, $result['wal']['current_frame']);
    },
    'keeps open savepoint rows visible but not released' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'open_stage', 'json' => $jsonRows([
                ['option_name' => 'open_json_stage', 'option_value' => '{"staged":1}'],
            ]), 'path' => '$.rows', 'release' => false],
        ]);

        $t->same('open', $result['batches'][0]['status']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old', 'open_json_stage'], $result['final_option_names']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old'], $result['released_option_names']);
    },
    'reports JSONB input rows through the same import planner' => static function (TestRunner $t) use ($currentRows): void {
        $blob = new SQLiteBlobValue(SQLiteJsonB::encode(['rows' => [
            ['option_name' => 'jsonb_settings', 'option_value' => '{"mode":"fast"}', 'autoload' => 'no'],
        ]]));
        $result = SQLiteJsonImportWalSavepointPlan::plan($currentRows(), [
            ['name' => 'jsonb_stage', 'json' => $blob, 'path' => '$.rows'],
        ], ['database_path' => '/tmp/wp-jsonb-import-current-next35.sqlite']);

        $t->same(['jsonb_stage'], $result['released_batches']);
        $t->same(['jsonb_settings'], $result['batches'][0]['json']['option_names']);
        $t->same(1, $result['wal']['frame_count']);
    },
    'reports JSON subtype input rows through the same import planner' => static function (TestRunner $t) use ($currentRows): void {
        $subtype = new SQLiteJsonSubtypeValue('{"rows":[{"option_name":"subtype_settings","option_value":"{\"mode\":\"json\"}"}]}');
        $result = SQLiteJsonImportWalSavepointPlan::plan($currentRows(), [
            ['name' => 'subtype_stage', 'json' => $subtype, 'path' => '$.rows'],
        ], ['database_path' => '/tmp/wp-subtype-import-current-next35.sqlite']);

        $t->same(['subtype_stage'], $result['released_batches']);
        $t->same(['subtype_settings'], $result['batches'][0]['json']['option_names']);
        $t->same(1, $result['wal']['frame_count']);
    },
    'aborts malformed JSON when requested' => static function (TestRunner $t) use ($plan): void {
        $t->throws(LogicException::class, static fn () => $plan([
            ['name' => 'bad_abort', 'json' => '{"rows":[', 'path' => '$.rows', 'on_conflict' => 'abort'],
        ]));
    },
    'aborts unique-name conflicts when replacement is disabled and requested' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $t->throws(LogicException::class, static fn () => $plan([
            ['name' => 'rename_conflict', 'json' => $jsonRows([
                ['option_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://duplicate.test'],
            ]), 'path' => '$.rows', 'on_conflict' => 'abort'],
        ], ['replace_conflicts' => false]));
    },
    'rolls unique-name conflicts back when replacement is disabled' => static function (TestRunner $t) use ($plan, $jsonRows): void {
        $result = $plan([
            ['name' => 'rename_conflict', 'json' => $jsonRows([
                ['option_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://duplicate.test'],
            ]), 'path' => '$.rows'],
        ], ['replace_conflicts' => false]);

        $t->same(['rename_conflict'], $result['rolled_back_batches']);
        $t->same(['siteurl', 'active_plugins', 'theme_mods_old'], $result['final_option_names']);
        $t->same(0, $result['wal']['frame_count']);
    },
];

foreach (range(1, 18) as $batch) {
    $tests["imports generated plugin settings batch {$batch} with current WAL frame"] = static function (TestRunner $t) use ($plan, $jsonRows, $batch): void {
        $result = $plan([
            ['name' => 'plugin_batch_' . $batch, 'json' => $jsonRows([
                ['option_name' => 'plugin_' . $batch . '_settings', 'option_value' => '{"rank":' . $batch . '}', 'autoload' => $batch % 2 === 0 ? 'yes' : 'no'],
            ]), 'path' => '$.rows'],
        ]);

        $t->same('plugin_batch_' . $batch, $result['wal']['frames'][0]['savepoint']);
        $t->same($batch % 2 === 0 ? 'yes' : 'no', $result['final_rows'][3]['autoload']);
        $t->same(1, $result['wal']['current_frame']);
    };
}

foreach ([64 => 2, 65 => 3, 128 => 3, 129 => 4, 192 => 4, 193 => 5, 256 => 5, 257 => 6] as $optionId => $pageNumber) {
    $tests["maps updated option {$optionId} to WAL page {$pageNumber}"] = static function (TestRunner $t) use ($currentRows, $jsonRows, $optionId, $pageNumber): void {
        $rows = $currentRows();
        $rows[] = ['option_id' => $optionId, 'option_name' => 'generated_' . $optionId, 'option_value' => 'old', 'autoload' => 'no'];
        $result = SQLiteJsonImportWalSavepointPlan::plan($rows, [
            ['name' => 'page_' . $optionId, 'json' => $jsonRows([
                ['option_id' => $optionId, 'option_name' => 'generated_' . $optionId, 'option_value' => 'new'],
            ]), 'path' => '$.rows'],
        ], ['database_path' => '/tmp/wp-page-map-current-next35.sqlite']);

        $t->same([$pageNumber], $result['batches'][0]['dirty_pages']);
        $t->same($pageNumber, $result['wal']['frames'][0]['page_number']);
        $t->same(true, $result['wal']['frames'][0]['commit_frame']);
    };
}

foreach (['$', '$.rows', '$.payload.rows'] as $pathIndex => $path) {
    $tests["extracts JSON import rows from path {$path}"] = static function (TestRunner $t) use ($plan, $path, $pathIndex): void {
        $row = ['option_name' => 'path_' . $pathIndex . '_settings', 'option_value' => '{"path":' . $pathIndex . '}'];
        $json = match ($path) {
            '$' => json_encode([$row], JSON_THROW_ON_ERROR),
            '$.rows' => json_encode(['rows' => [$row]], JSON_THROW_ON_ERROR),
            default => json_encode(['payload' => ['rows' => [$row]]], JSON_THROW_ON_ERROR),
        };

        $result = $plan([
            ['name' => 'path_stage_' . $pathIndex, 'json' => $json, 'path' => $path],
        ]);

        $t->same(['path_' . $pathIndex . '_settings'], $result['batches'][0]['json']['option_names']);
        $t->same(1, $result['batches'][0]['json']['row_count']);
    };
}

foreach ([
    'empty path' => '$.missing',
    'scalar path' => '$.count',
    'non row object' => '$.rows',
    'non json input' => '$',
    'empty row name' => '$.rows',
    'bad autoload value' => '$.rows',
] as $label => $path) {
    $tests["rolls back {$label} JSON import shape"] = static function (TestRunner $t) use ($plan, $path, $label): void {
        $json = match ($label) {
            'scalar path' => '{"count":3}',
            'non row object' => '{"rows":{"value":"missing-name"}}',
            'non json input' => 17,
            'empty row name' => '{"rows":[{"option_name":"","option_value":"x"}]}',
            'bad autoload value' => '{"rows":[{"option_name":"bad_autoload","autoload":"maybe"}]}',
            default => '{"rows":[]}',
        };
        $path = $label === 'empty path' ? '$.missing' : $path;
        $result = $plan([
            ['name' => 'shape_' . preg_replace('/[^a-z0-9]+/', '_', $label), 'json' => $json, 'path' => $path],
        ]);

        $t->same('rolled_back', $result['batches'][0]['status']);
        $t->same(0, $result['wal']['frame_count']);
    };
}

foreach ([
    'delete' => ['journal_mode' => 'delete', 'sync_mode' => 'full'],
    'truncate' => ['journal_mode' => 'truncate', 'sync_mode' => 'normal'],
    'persist' => ['journal_mode' => 'persist', 'sync_mode' => 'off'],
    'wal' => ['journal_mode' => 'wal', 'sync_mode' => 'normal'],
] as $mode => $options) {
    $tests["preserves {$mode} import options while tracking WAL current frame"] = static function (TestRunner $t) use ($plan, $jsonRows, $mode, $options): void {
        $result = $plan([
            ['name' => 'mode_' . $mode, 'json' => $jsonRows([
                ['option_name' => 'mode_' . $mode . '_settings', 'option_value' => '{"mode":"' . $mode . '"}'],
            ]), 'path' => '$.rows'],
        ], $options);

        $t->same($mode, $result['journal_mode']);
        $t->same($options['sync_mode'], $result['sync_mode']);
        $t->same(1, $result['wal']['current_frame']);
    };
}

$tests['rejects empty import list'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonImportWalSavepointPlan::plan($currentRows(), []));
};

$tests['rejects unsafe database paths'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonImportWalSavepointPlan::plan($currentRows(), [
        ['json' => '{"rows":[]}'],
    ], ['database_path' => '../wp.sqlite']));
};

$tests['rejects invalid savepoint names'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan([
        ['name' => 'bad-name', 'json' => '{"rows":[]}'],
    ]));
};

$tests['rejects unsupported conflict action'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan([
        ['name' => 'bad_conflict', 'json' => '{"rows":[]}', 'on_conflict' => 'ignore'],
    ]));
};

$tests['rejects unsupported journal modes'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonImportWalSavepointPlan::plan($currentRows(), [
        ['json' => '{"rows":[]}'],
    ], ['database_path' => '/tmp/wp-json-import.sqlite', 'journal_mode' => 'memory']));
};

$tests['rejects unsupported sync modes'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonImportWalSavepointPlan::plan($currentRows(), [
        ['json' => '{"rows":[]}'],
    ], ['database_path' => '/tmp/wp-json-import.sqlite', 'sync_mode' => 'extra']));
};

$tests['rejects invalid page sizes'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonImportWalSavepointPlan::plan($currentRows(), [
        ['json' => '{"rows":[]}'],
    ], ['database_path' => '/tmp/wp-json-import.sqlite', 'page_size' => 1000]));
};

return $tests;
