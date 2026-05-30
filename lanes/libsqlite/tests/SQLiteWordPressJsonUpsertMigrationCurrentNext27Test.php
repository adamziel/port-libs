<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonUpsertMigrationPlan;

$tests = [];

$currentRows = [
    [
        'option_id' => 1,
        'option_name' => 'widget_text',
        'option_value' => '{"version":1,"widgets":{"hero":{"title":"Old","enabled":false}},"source":"current"}',
        'autoload' => 'yes',
        'migration_generation' => 2,
    ],
    [
        'option_id' => 2,
        'option_name' => 'theme_mods_twentytwentyfour',
        'option_value' => '{"version":3,"mods":{"color":"blue"},"source":"current"}',
        'autoload' => 'no',
        'migration_generation' => 4,
    ],
    [
        'option_id' => 3,
        'option_name' => 'active_plugins',
        'option_value' => '{"plugins":["akismet/akismet.php"],"source":"current"}',
        'autoload' => 'yes',
        'migration_generation' => 1,
    ],
];

$incomingRows = [
    [
        'option_id' => 10,
        'option_name' => 'widget_text',
        'option_value' => '{"version":5,"widgets":{"hero":{"title":"Imported","enabled":true}},"source":"import"}',
        'autoload' => 'no',
        'migration_generation' => 7,
    ],
    [
        'option_id' => 11,
        'option_name' => 'rewrite_rules',
        'option_value' => '{"version":1,"rules":{"post":"index.php?p=$matches[1]"},"source":"import"}',
        'autoload' => 'yes',
        'migration_generation' => 1,
    ],
    [
        'option_id' => 12,
        'option_name' => 'theme_mods_twentytwentyfour',
        'option_value' => '{"version":6,"mods":{"color":"green","font":"serif"},"source":"import"}',
        'autoload' => 'yes',
        'migration_generation' => 8,
    ],
];

$jsonSetValues = [
    '$.migrated' => 1,
    '$.source' => ['excluded_json' => '$.source'],
    '$.previous_source' => ['current_json' => '$.source'],
    '$.incoming_version' => ['excluded_json' => '$.version'],
    '$.previous_generation' => ['current_column' => 'migration_generation'],
    '$.autoload_after' => ['excluded_column' => 'autoload'],
    '$.wp_import' => ['json' => '{"tool":"data-liberation","batch":27}'],
];

$plan = static fn (): array => SQLiteJsonUpsertMigrationPlan::execute(
    $currentRows,
    $incomingRows,
    $jsonSetValues,
    static fn (array $current, array $excluded): bool => (int) ($excluded['migration_generation'] ?? 0) >= (int) ($current['migration_generation'] ?? 0),
);

$repeatPlan = static fn (): array => SQLiteJsonUpsertMigrationPlan::execute(
    $currentRows,
    [
        [
            'option_id' => 20,
            'option_name' => 'migration_state',
            'option_value' => '{"version":1,"source":"first"}',
            'autoload' => 'no',
            'migration_generation' => 1,
        ],
        [
            'option_id' => 21,
            'option_name' => 'migration_state',
            'option_value' => '{"version":2,"source":"second"}',
            'autoload' => 'yes',
            'migration_generation' => 3,
        ],
    ],
    $jsonSetValues,
);

$skipPlan = static fn (): array => SQLiteJsonUpsertMigrationPlan::execute(
    $currentRows,
    [
        [
            'option_id' => 30,
            'option_name' => 'active_plugins',
            'option_value' => '{"plugins":["classic-editor/classic-editor.php"],"source":"stale"}',
            'autoload' => 'no',
            'migration_generation' => 0,
        ],
    ],
    $jsonSetValues,
    static fn (array $current, array $excluded): bool => (int) ($excluded['migration_generation'] ?? 0) >= (int) ($current['migration_generation'] ?? 0),
);

$cases = [
    'changes counts two updates and one insert' => [static fn (): mixed => $plan()['changes'], 3],
    'returning rows preserve source order' => [static fn (): mixed => array_column($plan()['returning_rows'], 'option_name'), ['widget_text', 'rewrite_rules', 'theme_mods_twentytwentyfour']],
    'updated rows are conflict rows only' => [static fn (): mixed => array_column($plan()['updated_rows'], 'option_name'), ['widget_text', 'theme_mods_twentytwentyfour']],
    'inserted rows are non-conflict rows only' => [static fn (): mixed => array_column($plan()['inserted_rows'], 'option_name'), ['rewrite_rules']],
    'skipped rows are empty for current import' => [static fn (): mixed => $plan()['skipped_rows'], []],
    'after keeps original order and appends insert' => [static fn (): mixed => array_column($plan()['after'], 'option_name'), ['widget_text', 'theme_mods_twentytwentyfour', 'active_plugins', 'rewrite_rules']],
    'before keeps original JSON unchanged' => [static fn (): mixed => $plan()['before'][0]['option_value'], $currentRows[0]['option_value']],
    'widget autoload updates from excluded row' => [static fn (): mixed => $plan()['after'][0]['autoload'], 'no'],
    'theme autoload updates from excluded row' => [static fn (): mixed => $plan()['after'][1]['autoload'], 'yes'],
    'insert autoload keeps incoming row' => [static fn (): mixed => $plan()['after'][3]['autoload'], 'yes'],
    'widget generation increments max current excluded' => [static fn (): mixed => $plan()['after'][0]['migration_generation'], 8],
    'theme generation increments max current excluded' => [static fn (): mixed => $plan()['after'][1]['migration_generation'], 9],
    'insert generation keeps prepared incoming generation' => [static fn (): mixed => $plan()['after'][3]['migration_generation'], 1],
    'decoded returning rows are exposed' => [static fn (): mixed => array_keys($plan()['decoded_returning'][0]), ['option_id', 'option_name', 'option_value', 'autoload', 'migration_generation', 'decoded_option_value']],
    'widget migrated flag is set' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_option_value']['migrated'], 1],
    'theme migrated flag is set' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_option_value']['migrated'], 1],
    'insert migrated flag is set before insert' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_option_value']['migrated'], 1],
    'widget source comes from excluded JSON' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_option_value']['source'], 'import'],
    'theme source comes from excluded JSON' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_option_value']['source'], 'import'],
    'insert source remains import JSON' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_option_value']['source'], 'import'],
    'widget previous source comes from current row' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_option_value']['previous_source'], 'current'],
    'theme previous source comes from current row' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_option_value']['previous_source'], 'current'],
    'insert previous source is null without current row' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_option_value']['previous_source'], null],
    'widget incoming version comes from excluded JSON' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_option_value']['incoming_version'], 5],
    'theme incoming version comes from excluded JSON' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_option_value']['incoming_version'], 6],
    'insert incoming version comes from incoming JSON' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_option_value']['incoming_version'], 1],
    'widget previous generation comes from current column' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_option_value']['previous_generation'], 2],
    'theme previous generation comes from current column' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_option_value']['previous_generation'], 4],
    'insert previous generation is null' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_option_value']['previous_generation'], null],
    'widget autoload json mirrors excluded column' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_option_value']['autoload_after'], 'no'],
    'theme autoload json mirrors excluded column' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_option_value']['autoload_after'], 'yes'],
    'insert autoload json mirrors incoming column' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_option_value']['autoload_after'], 'yes'],
    'widget nested JSON literal is preserved as object' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_option_value']['wp_import'], ['tool' => 'data-liberation', 'batch' => 27]],
    'insert nested JSON literal is preserved as object' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_option_value']['wp_import'], ['tool' => 'data-liberation', 'batch' => 27]],
    'widget existing nested data remains from current row' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_option_value']['widgets']['hero']['title'], 'Old'],
    'theme existing nested data remains from current row' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_option_value']['mods']['color'], 'blue'],
    'insert keeps incoming rules object' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_option_value']['rules']['post'], 'index.php?p=$matches[1]'],
    'widget option id remains current on update' => [static fn (): mixed => $plan()['returning_rows'][0]['option_id'], 1],
    'theme option id remains current on update' => [static fn (): mixed => $plan()['returning_rows'][2]['option_id'], 2],
    'insert option id is incoming on insert' => [static fn (): mixed => $plan()['returning_rows'][1]['option_id'], 11],
    'repeat row inserts then updates same statement key' => [static fn (): mixed => array_column($repeatPlan()['returning_rows'], 'option_name'), ['migration_state', 'migration_state']],
    'repeat second row sees first insert as current' => [static fn (): mixed => $repeatPlan()['decoded_returning'][1]['decoded_option_value']['previous_source'], 'first'],
    'repeat second row increments generation from inserted current' => [static fn (): mixed => $repeatPlan()['returning_rows'][1]['migration_generation'], 4],
    'repeat final table has one migration state row' => [static fn (): mixed => count(array_filter($repeatPlan()['after'], static fn (array $row): bool => $row['option_name'] === 'migration_state')), 1],
    'repeat final row source is second import' => [static fn (): mixed => array_values(array_filter($repeatPlan()['after'], static fn (array $row): bool => $row['option_name'] === 'migration_state'))[0]['option_value'], '{"version":1,"source":"second","migrated":1,"previous_source":"first","incoming_version":2,"previous_generation":1,"autoload_after":"yes","wp_import":{"tool":"data-liberation","batch":27}}'],
    'stale conflict is skipped by WHERE' => [static fn (): mixed => array_column($skipPlan()['skipped_rows'], 'option_name'), ['active_plugins']],
    'stale conflict makes no changes' => [static fn (): mixed => $skipPlan()['changes'], 0],
    'stale conflict leaves active plugins unchanged' => [static fn (): mixed => $skipPlan()['after'][2]['option_value'], $currentRows[2]['option_value']],
    'stale conflict returns no decoded rows' => [static fn (): mixed => $skipPlan()['decoded_returning'], []],
    'empty json set values rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, $incomingRows, []), InvalidArgumentException::class],
    'malformed json set path rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, $incomingRows, ['source' => 1]), InvalidArgumentException::class],
    'missing incoming option value rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [['option_name' => 'x']], $jsonSetValues), InvalidArgumentException::class],
    'non-text incoming option value rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [['option_name' => 'x', 'option_value' => 1]], $jsonSetValues), InvalidArgumentException::class],
    'missing excluded column rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, $incomingRows, ['$.x' => ['excluded_column' => 'missing']]), InvalidArgumentException::class],
    'missing current column rejected on update' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [$incomingRows[0]], ['$.x' => ['current_column' => 'missing']]), InvalidArgumentException::class],
    'missing current column is null on insert' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [$incomingRows[1]], ['$.x' => ['current_column' => 'missing']])['decoded_returning'][0]['decoded_option_value']['x'], null],
    'malformed value expression rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, $incomingRows, ['$.x' => ['bad' => 'shape']]), InvalidArgumentException::class],
    'non-string json literal rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, $incomingRows, ['$.x' => ['json' => ['bad']]]), InvalidArgumentException::class],
    'empty excluded json path rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, $incomingRows, ['$.x' => ['excluded_json' => '']]), InvalidArgumentException::class],
    'bad incoming JSON rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [['option_name' => 'bad', 'option_value' => '{']], $jsonSetValues), Throwable::class],
    'bad current JSON rejected on update' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute([['option_name' => 'widget_text', 'option_value' => '{']], [$incomingRows[0]], $jsonSetValues), Throwable::class],
    'literal null can be set into JSON' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [$incomingRows[1]], ['$.nullable' => ['literal' => null]])['decoded_returning'][0]['decoded_option_value']['nullable'], null],
    'literal false can be set into JSON' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [$incomingRows[1]], ['$.flag' => ['literal' => false]])['decoded_returning'][0]['decoded_option_value']['flag'], false],
    'literal string can be set into JSON' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [$incomingRows[1]], ['$.label' => 'copied'])['decoded_returning'][0]['decoded_option_value']['label'], 'copied'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['application json upsert migration current next27 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
