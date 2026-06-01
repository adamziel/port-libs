<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonUpsertMigrationPlan;

$tests = [];

$currentRows = [
    [
        'setting_id' => 1,
        'key_name' => 'content_widget',
        'key_value' => '{"version":1,"widgets":{"hero":{"title":"Old","enabled":false}},"source":"current"}',
        'load_policy' => 'yes',
        'migration_generation' => 2,
    ],
    [
        'setting_id' => 2,
        'key_name' => 'visual_profile',
        'key_value' => '{"version":3,"mods":{"color":"blue"},"source":"current"}',
        'load_policy' => 'no',
        'migration_generation' => 4,
    ],
    [
        'setting_id' => 3,
        'key_name' => 'active_modules',
        'key_value' => '{"modules":["analytics/analytics.php"],"source":"current"}',
        'load_policy' => 'yes',
        'migration_generation' => 1,
    ],
];

$incomingRows = [
    [
        'setting_id' => 10,
        'key_name' => 'content_widget',
        'key_value' => '{"version":5,"widgets":{"hero":{"title":"Imported","enabled":true}},"source":"import"}',
        'load_policy' => 'no',
        'migration_generation' => 7,
    ],
    [
        'setting_id' => 11,
        'key_name' => 'route_rules',
        'key_value' => '{"version":1,"rules":{"post":"index.php?p=$matches[1]"},"source":"import"}',
        'load_policy' => 'yes',
        'migration_generation' => 1,
    ],
    [
        'setting_id' => 12,
        'key_name' => 'visual_profile',
        'key_value' => '{"version":6,"mods":{"color":"green","font":"serif"},"source":"import"}',
        'load_policy' => 'yes',
        'migration_generation' => 8,
    ],
];

$jsonSetValues = [
    '$.migrated' => 1,
    '$.source' => ['excluded_json' => '$.source'],
    '$.previous_source' => ['current_json' => '$.source'],
    '$.incoming_version' => ['excluded_json' => '$.version'],
    '$.previous_generation' => ['current_column' => 'migration_generation'],
    '$.load_policy_after' => ['excluded_column' => 'load_policy'],
    '$.app_import' => ['json' => '{"tool":"data-liberation","batch":27}'],
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
            'setting_id' => 20,
            'key_name' => 'migration_state',
            'key_value' => '{"version":1,"source":"first"}',
            'load_policy' => 'no',
            'migration_generation' => 1,
        ],
        [
            'setting_id' => 21,
            'key_name' => 'migration_state',
            'key_value' => '{"version":2,"source":"second"}',
            'load_policy' => 'yes',
            'migration_generation' => 3,
        ],
    ],
    $jsonSetValues,
);

$skipPlan = static fn (): array => SQLiteJsonUpsertMigrationPlan::execute(
    $currentRows,
    [
        [
            'setting_id' => 30,
            'key_name' => 'active_modules',
            'key_value' => '{"modules":["editor/editor.php"],"source":"stale"}',
            'load_policy' => 'no',
            'migration_generation' => 0,
        ],
    ],
    $jsonSetValues,
    static fn (array $current, array $excluded): bool => (int) ($excluded['migration_generation'] ?? 0) >= (int) ($current['migration_generation'] ?? 0),
);

$cases = [
    'changes counts two updates and one insert' => [static fn (): mixed => $plan()['changes'], 3],
    'returning rows preserve source order' => [static fn (): mixed => array_column($plan()['returning_rows'], 'key_name'), ['content_widget', 'route_rules', 'visual_profile']],
    'updated rows are conflict rows only' => [static fn (): mixed => array_column($plan()['updated_rows'], 'key_name'), ['content_widget', 'visual_profile']],
    'inserted rows are non-conflict rows only' => [static fn (): mixed => array_column($plan()['inserted_rows'], 'key_name'), ['route_rules']],
    'skipped rows are empty for current import' => [static fn (): mixed => $plan()['skipped_rows'], []],
    'after keeps original order and appends insert' => [static fn (): mixed => array_column($plan()['after'], 'key_name'), ['content_widget', 'visual_profile', 'active_modules', 'route_rules']],
    'before keeps original JSON unchanged' => [static fn (): mixed => $plan()['before'][0]['key_value'], $currentRows[0]['key_value']],
    'widget load_policy updates from excluded row' => [static fn (): mixed => $plan()['after'][0]['load_policy'], 'no'],
    'visual profile load_policy updates from excluded row' => [static fn (): mixed => $plan()['after'][1]['load_policy'], 'yes'],
    'insert load_policy keeps incoming row' => [static fn (): mixed => $plan()['after'][3]['load_policy'], 'yes'],
    'widget generation increments max current excluded' => [static fn (): mixed => $plan()['after'][0]['migration_generation'], 8],
    'visual profile generation increments max current excluded' => [static fn (): mixed => $plan()['after'][1]['migration_generation'], 9],
    'insert generation keeps prepared incoming generation' => [static fn (): mixed => $plan()['after'][3]['migration_generation'], 1],
    'decoded returning rows are exposed' => [static fn (): mixed => array_keys($plan()['decoded_returning'][0]), ['setting_id', 'key_name', 'key_value', 'load_policy', 'migration_generation', 'decoded_key_value']],
    'widget migrated flag is set' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_key_value']['migrated'], 1],
    'visual profile migrated flag is set' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_key_value']['migrated'], 1],
    'insert migrated flag is set before insert' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_key_value']['migrated'], 1],
    'widget source comes from excluded JSON' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_key_value']['source'], 'import'],
    'visual profile source comes from excluded JSON' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_key_value']['source'], 'import'],
    'insert source remains import JSON' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_key_value']['source'], 'import'],
    'widget previous source comes from current row' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_key_value']['previous_source'], 'current'],
    'visual profile previous source comes from current row' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_key_value']['previous_source'], 'current'],
    'insert previous source is null without current row' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_key_value']['previous_source'], null],
    'widget incoming version comes from excluded JSON' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_key_value']['incoming_version'], 5],
    'visual profile incoming version comes from excluded JSON' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_key_value']['incoming_version'], 6],
    'insert incoming version comes from incoming JSON' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_key_value']['incoming_version'], 1],
    'widget previous generation comes from current column' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_key_value']['previous_generation'], 2],
    'visual profile previous generation comes from current column' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_key_value']['previous_generation'], 4],
    'insert previous generation is null' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_key_value']['previous_generation'], null],
    'widget load_policy json mirrors excluded column' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_key_value']['load_policy_after'], 'no'],
    'visual profile load_policy json mirrors excluded column' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_key_value']['load_policy_after'], 'yes'],
    'insert load_policy json mirrors incoming column' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_key_value']['load_policy_after'], 'yes'],
    'widget nested JSON literal is preserved as object' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_key_value']['app_import'], ['tool' => 'data-liberation', 'batch' => 27]],
    'insert nested JSON literal is preserved as object' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_key_value']['app_import'], ['tool' => 'data-liberation', 'batch' => 27]],
    'widget existing nested data remains from current row' => [static fn (): mixed => $plan()['decoded_returning'][0]['decoded_key_value']['widgets']['hero']['title'], 'Old'],
    'visual profile existing nested data remains from current row' => [static fn (): mixed => $plan()['decoded_returning'][2]['decoded_key_value']['mods']['color'], 'blue'],
    'insert keeps incoming rules object' => [static fn (): mixed => $plan()['decoded_returning'][1]['decoded_key_value']['rules']['post'], 'index.php?p=$matches[1]'],
    'widget setting id remains current on update' => [static fn (): mixed => $plan()['returning_rows'][0]['setting_id'], 1],
    'visual profile setting id remains current on update' => [static fn (): mixed => $plan()['returning_rows'][2]['setting_id'], 2],
    'insert setting id is incoming on insert' => [static fn (): mixed => $plan()['returning_rows'][1]['setting_id'], 11],
    'repeat row inserts then updates same statement key' => [static fn (): mixed => array_column($repeatPlan()['returning_rows'], 'key_name'), ['migration_state', 'migration_state']],
    'repeat second row sees first insert as current' => [static fn (): mixed => $repeatPlan()['decoded_returning'][1]['decoded_key_value']['previous_source'], 'first'],
    'repeat second row increments generation from inserted current' => [static fn (): mixed => $repeatPlan()['returning_rows'][1]['migration_generation'], 4],
    'repeat final table has one migration state row' => [static fn (): mixed => count(array_filter($repeatPlan()['after'], static fn (array $row): bool => $row['key_name'] === 'migration_state')), 1],
    'repeat final row source is second import' => [static fn (): mixed => array_values(array_filter($repeatPlan()['after'], static fn (array $row): bool => $row['key_name'] === 'migration_state'))[0]['key_value'], '{"version":1,"source":"second","migrated":1,"previous_source":"first","incoming_version":2,"previous_generation":1,"load_policy_after":"yes","app_import":{"tool":"data-liberation","batch":27}}'],
    'stale conflict is skipped by WHERE' => [static fn (): mixed => array_column($skipPlan()['skipped_rows'], 'key_name'), ['active_modules']],
    'stale conflict makes no changes' => [static fn (): mixed => $skipPlan()['changes'], 0],
    'stale conflict leaves active modules unchanged' => [static fn (): mixed => $skipPlan()['after'][2]['key_value'], $currentRows[2]['key_value']],
    'stale conflict returns no decoded rows' => [static fn (): mixed => $skipPlan()['decoded_returning'], []],
    'empty json set values rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, $incomingRows, []), InvalidArgumentException::class],
    'malformed json set path rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, $incomingRows, ['source' => 1]), InvalidArgumentException::class],
    'missing incoming key value rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [['key_name' => 'x']], $jsonSetValues), InvalidArgumentException::class],
    'non-text incoming key value rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [['key_name' => 'x', 'key_value' => 1]], $jsonSetValues), InvalidArgumentException::class],
    'missing excluded column rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, $incomingRows, ['$.x' => ['excluded_column' => 'missing']]), InvalidArgumentException::class],
    'missing current column rejected on update' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [$incomingRows[0]], ['$.x' => ['current_column' => 'missing']]), InvalidArgumentException::class],
    'missing current column is null on insert' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [$incomingRows[1]], ['$.x' => ['current_column' => 'missing']])['decoded_returning'][0]['decoded_key_value']['x'], null],
    'malformed value expression rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, $incomingRows, ['$.x' => ['bad' => 'shape']]), InvalidArgumentException::class],
    'non-string json literal rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, $incomingRows, ['$.x' => ['json' => ['bad']]]), InvalidArgumentException::class],
    'empty excluded json path rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, $incomingRows, ['$.x' => ['excluded_json' => '']]), InvalidArgumentException::class],
    'bad incoming JSON rejected' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [['key_name' => 'bad', 'key_value' => '{']], $jsonSetValues), Throwable::class],
    'bad current JSON rejected on update' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute([['key_name' => 'content_widget', 'key_value' => '{']], [$incomingRows[0]], $jsonSetValues), Throwable::class],
    'literal null can be set into JSON' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [$incomingRows[1]], ['$.nullable' => ['literal' => null]])['decoded_returning'][0]['decoded_key_value']['nullable'], null],
    'literal false can be set into JSON' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [$incomingRows[1]], ['$.flag' => ['literal' => false]])['decoded_returning'][0]['decoded_key_value']['flag'], false],
    'literal string can be set into JSON' => [static fn (): mixed => SQLiteJsonUpsertMigrationPlan::execute($currentRows, [$incomingRows[1]], ['$.label' => 'copied'])['decoded_returning'][0]['decoded_key_value']['label'], 'copied'],
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
