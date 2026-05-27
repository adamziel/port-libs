<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointTriggerRollbackPlan;

$page = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);

$outerRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'level' => 0, 'autoload' => 'yes'],
];
$savepointRows = [
    ...$outerRows,
    ['option_id' => 3, 'option_name' => 'preflight_marker', 'level' => 0, 'autoload' => 'no'],
];
$inputRows = [
    ['option_id' => 10, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes'],
    ['option_id' => 20, 'option_name' => 'second_plugin', 'level' => 1, 'autoload' => 'no'],
];
$recursiveTrigger = [
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'level', 'operator' => '<', 'value' => 3],
    'insert_row' => [
        'option_id' => 'new_increment.option_id',
        'option_name' => 'concat:new.option_name::child',
        'level' => 'new_increment.level',
        'autoload' => 'new.autoload',
    ],
];
$rollbackOnChild = [
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'option_name', 'operator' => '=', 'value' => 'plugin_seed:child'],
    'rollback' => true,
];
$options = [
    'page_size' => 512,
    'savepoint_page_images' => [
        3 => $page('before-options'),
        4 => $page('before-index'),
    ],
    'dirty_pages' => [
        3 => $page('dirty-options'),
        4 => $page('dirty-index'),
        5 => $page('dirty-overflow'),
    ],
    'wal_start_frame' => 7,
    'wal_frames' => [
        ['frame_index' => 8, 'page_number' => 3],
        ['frame_index' => 9, 'page_number' => 4],
        ['frame_index' => 10, 'page_number' => 5, 'commit_frame' => true],
    ],
    'returning' => ['name' => 'option_name', 'depth' => 'level', 'autoload' => 'autoload'],
];

$run = static function (array $triggers, array $input = null, array $extraOptions = []) use ($outerRows, $savepointRows, $inputRows, $options): array {
    return SQLiteSavepointTriggerRollbackPlan::insertRows(
        $outerRows,
        $savepointRows,
        $input ?? $inputRows,
        $triggers,
        ['option_name'],
        'plugin_import',
        $extraOptions + $options
    );
};
$success = static fn (): array => $run([$recursiveTrigger]);
$rollback = static fn (): array => $run([$recursiveTrigger, $rollbackOnChild]);
$wildcard = static fn (): array => $run([$recursiveTrigger], null, ['returning' => ['*']]);
$computed = static fn (): array => $run([$recursiveTrigger], null, [
    'returning' => [
        'label' => static fn (array $row): string => $row['option_name'] . ':' . $row['autoload'],
        'level_next' => static fn (array $row): int => $row['level'] + 1,
    ],
]);
$duplicate = static fn (): array => $run([$recursiveTrigger], [
    ['option_id' => 99, 'option_name' => 'siteurl', 'level' => 1, 'autoload' => 'yes'],
]);
$recursiveOff = static fn (): array => $run([$recursiveTrigger], null, ['recursive_triggers' => false]);
$emptyReturning = static fn (): array => $run([$recursiveTrigger], null, ['returning' => []]);

$cases = [
    'successful returning names include direct input rows only' => [static fn (): mixed => array_column($success()['returning_rows'], 'name'), ['plugin_seed', 'second_plugin']],
    'successful returning excludes recursive child rows' => [static fn (): mixed => in_array('plugin_seed:child', array_column($success()['returning_rows'], 'name'), true), false],
    'successful returning excludes second recursive child rows' => [static fn (): mixed => in_array('second_plugin:child', array_column($success()['returning_rows'], 'name'), true), false],
    'successful attempted returning equals committed returning' => [static fn (): mixed => $success()['attempted_returning_rows'], $success()['returning_rows']],
    'successful returning columns preserve aliases' => [static fn (): mixed => $success()['returning_columns'], ['name', 'depth', 'autoload']],
    'successful returning is before after trigger side effects' => [static fn (): mixed => $success()['returning_after_triggers'], false],
    'successful changes still include recursive inserts' => [static fn (): mixed => $success()['changes'], 6],
    'successful inserted rows include recursive child rows' => [static fn (): mixed => array_column($success()['inserted'], 'option_name'), ['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'second_plugin', 'second_plugin:child', 'second_plugin:child:child']],
    'successful row image includes recursive children' => [static fn (): mixed => array_column($success()['rows'], 'option_name'), ['siteurl', 'home', 'preflight_marker', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'second_plugin', 'second_plugin:child', 'second_plugin:child:child']],
    'successful first returning depth uses top-level row' => [static fn (): mixed => $success()['returning_rows'][0]['depth'], 1],
    'successful second returning autoload uses direct row' => [static fn (): mixed => $success()['returning_rows'][1]['autoload'], 'no'],
    'successful first returning key order is stable' => [static fn (): mixed => array_keys($success()['returning_rows'][0]), ['name', 'depth', 'autoload']],
    'successful dependencies include returning tag' => [static fn (): mixed => in_array('sqlite-returning-current-row', $success()['dependencies'], true), true],
    'successful dependencies include trigger rollback tag' => [static fn (): mixed => in_array('sqlite-trigger-raise-rollback', $success()['dependencies'], true), true],
    'successful dependencies include savepoint tag' => [static fn (): mixed => in_array('sqlite-savepoint-current-rollback', $success()['dependencies'], true), true],
    'wildcard returning preserves option id' => [static fn (): mixed => $wildcard()['returning_rows'][0]['option_id'], 10],
    'wildcard returning preserves option name' => [static fn (): mixed => $wildcard()['returning_rows'][1]['option_name'], 'second_plugin'],
    'wildcard returning columns advertise wildcard' => [static fn (): mixed => $wildcard()['returning_columns'], ['*']],
    'wildcard returning has full row keys' => [static fn (): mixed => array_keys($wildcard()['returning_rows'][0]), ['option_id', 'option_name', 'level', 'autoload']],
    'computed returning evaluates callable label' => [static fn (): mixed => array_column($computed()['returning_rows'], 'label'), ['plugin_seed:yes', 'second_plugin:no']],
    'computed returning evaluates callable numeric expression' => [static fn (): mixed => array_column($computed()['returning_rows'], 'level_next'), [2, 2]],
    'computed returning columns advertise aliases' => [static fn (): mixed => $computed()['returning_columns'], ['label', 'level_next']],
    'empty returning defaults to wildcard row' => [static fn (): mixed => $emptyReturning()['returning_rows'][0]['option_name'], 'plugin_seed'],
    'empty returning columns advertise wildcard' => [static fn (): mixed => $emptyReturning()['returning_columns'], ['*']],

    'rollback clears committed returning rows' => [static fn (): mixed => $rollback()['returning_rows'], []],
    'rollback preserves attempted direct returning row' => [static fn (): mixed => array_column($rollback()['attempted_returning_rows'], 'name'), ['plugin_seed']],
    'rollback attempted returning excludes trigger child row' => [static fn (): mixed => in_array('plugin_seed:child', array_column($rollback()['attempted_returning_rows'], 'name'), true), false],
    'rollback restores current savepoint rows' => [static fn (): mixed => array_column($rollback()['rows'], 'option_name'), ['siteurl', 'home', 'preflight_marker']],
    'rollback leaves second top-level row unattempted' => [static fn (): mixed => in_array('second_plugin', array_column($rollback()['attempted_returning_rows'], 'name'), true), false],
    'rollback clears changes count' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback clears inserted diagnostics' => [static fn (): mixed => $rollback()['inserted'], []],
    'rollback clears ignored diagnostics' => [static fn (): mixed => $rollback()['ignored'], []],
    'rollback marks current savepoint scope' => [static fn (): mixed => $rollback()['rollback_scope'], 'current-savepoint'],
    'rollback records trigger rollback reason' => [static fn (): mixed => $rollback()['rollback_reason'], 'trigger-raise-rollback-current-savepoint'],
    'rollback keeps savepoint active' => [static fn (): mixed => $rollback()['savepoint_active_after'], true],
    'rollback keeps transaction active' => [static fn (): mixed => $rollback()['transaction_active_after'], true],
    'rollback reports restored page numbers' => [static fn (): mixed => $rollback()['restored_page_numbers'], [3, 4, 5]],
    'rollback reports rollback page numbers' => [static fn (): mixed => $rollback()['rollback_page_numbers'], [3, 4, 5]],
    'rollback reports wal frame prefix' => [static fn (): mixed => $rollback()['rollback_to_wal_frame'], 7],
    'rollback discards savepoint wal frame indexes' => [static fn (): mixed => array_column($rollback()['discarded_wal_frames'], 'frame_index'), [8, 9, 10]],
    'rollback discards commit frame marker' => [static fn (): mixed => $rollback()['discarded_wal_frames'][2]['commit_frame'], true],
    'rollback effects include direct insert' => [static fn (): mixed => $rollback()['effects'][0]['result'], 'inserted'],
    'rollback effects include recursive trigger fire' => [static fn (): mixed => in_array('fired', array_column($rollback()['effects'], 'result'), true), true],
    'rollback effects include child insert' => [static fn (): mixed => in_array('plugin_seed:child', array_map(static fn (array $effect): mixed => $effect['row']['option_name'] ?? null, $rollback()['effects']), true), true],
    'rollback effect depth is recursive child' => [static fn (): mixed => $rollback()['effects'][array_search('rollback-current-savepoint', array_column($rollback()['effects'], 'result'), true)]['depth'], 1],
    'rollback effect action is rollback' => [static fn (): mixed => $rollback()['effects'][array_search('rollback-current-savepoint', array_column($rollback()['effects'], 'result'), true)]['effective_conflict_action'], 'rollback'],
    'rollback returning columns still preserve aliases' => [static fn (): mixed => $rollback()['returning_columns'], ['name', 'depth', 'autoload']],

    'duplicate ignored has no returning rows' => [static fn (): mixed => $duplicate()['returning_rows'], []],
    'duplicate ignored records ignored option' => [static fn (): mixed => array_column($duplicate()['ignored'], 'option_name'), ['siteurl']],
    'duplicate ignored attempted returning is empty' => [static fn (): mixed => $duplicate()['attempted_returning_rows'], []],
    'duplicate ignored keeps savepoint rows' => [static fn (): mixed => array_column($duplicate()['rows'], 'option_name'), ['siteurl', 'home', 'preflight_marker']],
    'recursive disabled returns top-level rows only' => [static fn (): mixed => array_column($recursiveOff()['returning_rows'], 'name'), ['plugin_seed', 'second_plugin']],
    'recursive disabled rows include one child per input' => [static fn (): mixed => array_column($recursiveOff()['rows'], 'option_name'), ['siteurl', 'home', 'preflight_marker', 'plugin_seed', 'plugin_seed:child', 'second_plugin', 'second_plugin:child']],
    'recursive disabled change count includes top and first child' => [static fn (): mixed => $recursiveOff()['changes'], 4],
    'recursive disabled records suppression effect' => [static fn (): mixed => in_array('recursive-trigger-suppressed', array_column($recursiveOff()['effects'], 'result'), true), true],

    'missing returning column rejected' => [static fn (): mixed => $run([$recursiveTrigger], null, ['returning' => ['missing']]), InvalidArgumentException::class],
    'malformed returning column rejected' => [static fn (): mixed => $run([$recursiveTrigger], null, ['returning' => [123]]), InvalidArgumentException::class],
    'callable returning requires alias' => [static fn (): mixed => $run([$recursiveTrigger], null, ['returning' => [static fn (array $row): string => (string) $row['option_name']]]), InvalidArgumentException::class],
    'returning missing column is checked before recursive trigger fire completes' => [static fn (): mixed => $run([$recursiveTrigger, $rollbackOnChild], null, ['returning' => ['missing']]), InvalidArgumentException::class],
    'empty savepoint still rejected with returning' => [static fn (): mixed => SQLiteSavepointTriggerRollbackPlan::insertRows($outerRows, $savepointRows, $inputRows, [$recursiveTrigger], ['option_name'], '', $options), InvalidArgumentException::class],
    'malformed unique column still rejected with returning' => [static fn (): mixed => SQLiteSavepointTriggerRollbackPlan::insertRows($outerRows, $savepointRows, $inputRows, [$recursiveTrigger], ['1bad'], 'plugin_import', $options), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger recursive savepoint returning current next34 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
