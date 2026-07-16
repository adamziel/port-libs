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
];
$recursiveTrigger = [[
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
]];
$rollbackTrigger = [[
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'level', 'operator' => '=', 'value' => 2],
    'rollback' => true,
]];
$options = [
    'page_size' => 512,
    'savepoint_page_images' => [
        3 => $page('before-option-leaf'),
        4 => $page('before-audit-leaf'),
    ],
    'dirty_pages' => [
        3 => $page('dirty-option-leaf'),
        4 => $page('dirty-audit-leaf'),
        5 => $page('dirty-index-leaf'),
    ],
    'wal_start_frame' => 2,
    'wal_frames' => [
        ['frame_index' => 3, 'page_number' => 3],
        ['frame_index' => 4, 'page_number' => 4],
        ['frame_index' => 5, 'page_number' => 5, 'commit_frame' => true],
    ],
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

$rollbackPlan = static fn (): array => $run([$rollbackTrigger[0], $recursiveTrigger[0]]);
$successPlan = static fn (): array => $run($recursiveTrigger);

$cases = [
    'rollback restores current savepoint row names' => [static fn (): mixed => array_column($rollbackPlan()['rows'], 'option_name'), ['siteurl', 'home', 'preflight_marker']],
    'rollback preserves preflight row inside current savepoint' => [static fn (): mixed => $rollbackPlan()['rows'][2]['option_name'], 'preflight_marker'],
    'rollback removes seed row' => [static fn (): mixed => in_array('plugin_seed', array_column($rollbackPlan()['rows'], 'option_name'), true), false],
    'rollback removes trigger child row' => [static fn (): mixed => in_array('plugin_seed:child', array_column($rollbackPlan()['rows'], 'option_name'), true), false],
    'rollback clears change count' => [static fn (): mixed => $rollbackPlan()['changes'], 0],
    'rollback clears inserted diagnostics' => [static fn (): mixed => $rollbackPlan()['inserted'], []],
    'rollback clears ignored diagnostics' => [static fn (): mixed => $rollbackPlan()['ignored'], []],
    'rollback marks savepoint scope' => [static fn (): mixed => $rollbackPlan()['rollback_scope'], 'current-savepoint'],
    'rollback records trigger reason' => [static fn (): mixed => $rollbackPlan()['rollback_reason'], 'trigger-raise-rollback-current-savepoint'],
    'rollback keeps transaction active' => [static fn (): mixed => $rollbackPlan()['transaction_active_after'], true],
    'rollback keeps savepoint active' => [static fn (): mixed => $rollbackPlan()['savepoint_active_after'], true],
    'rollback names savepoint' => [static fn (): mixed => $rollbackPlan()['savepoint'], 'plugin_import'],
    'rollback reports row removal count' => [static fn (): mixed => $rollbackPlan()['rollback_rows_removed'], 2],
    'rollback restores option and audit pages' => [static fn (): mixed => $rollbackPlan()['restored_page_numbers'], [3, 4, 5]],
    'rollback page numbers mirror restored pages' => [static fn (): mixed => $rollbackPlan()['rollback_page_numbers'], [3, 4, 5]],
    'rollback retains wal prefix frame' => [static fn (): mixed => $rollbackPlan()['rollback_to_wal_frame'], 2],
    'rollback discards trigger wal frame count' => [static fn (): mixed => count($rollbackPlan()['discarded_wal_frames']), 3],
    'rollback discards first savepoint wal frame' => [static fn (): mixed => $rollbackPlan()['discarded_wal_frames'][0]['frame_index'], 3],
    'rollback discards second savepoint page' => [static fn (): mixed => $rollbackPlan()['discarded_wal_frames'][1]['page_number'], 4],
    'rollback discards commit marker frame' => [static fn (): mixed => $rollbackPlan()['discarded_wal_frames'][2]['commit_frame'], true],
    'rollback carries savepoint dependency tag' => [static fn (): mixed => in_array('sqlite-savepoint-current-rollback', $rollbackPlan()['dependencies'], true), true],
    'rollback carries trigger dependency tag' => [static fn (): mixed => in_array('sqlite-trigger-raise-rollback', $rollbackPlan()['dependencies'], true), true],
    'rollback effects include seed insert' => [static fn (): mixed => $rollbackPlan()['effects'][0]['result'], 'inserted'],
    'rollback effects include top rollback skip' => [static fn (): mixed => $rollbackPlan()['effects'][1]['result'], 'when-skipped'],
    'rollback effects include recursive trigger fire' => [static fn (): mixed => $rollbackPlan()['effects'][2]['result'], 'fired'],
    'rollback effects include child insert' => [static fn (): mixed => $rollbackPlan()['effects'][3]['result'], 'inserted'],
    'rollback effects include current savepoint rollback' => [static fn (): mixed => $rollbackPlan()['effects'][4]['result'], 'rollback-current-savepoint'],
    'rollback trigger effect has depth one' => [static fn (): mixed => $rollbackPlan()['effects'][4]['depth'], 1],
    'rollback trigger effect uses rollback action' => [static fn (): mixed => $rollbackPlan()['effects'][4]['effective_conflict_action'], 'rollback'],
    'rollback trigger sees child option name' => [static fn (): mixed => $rollbackPlan()['effects'][4]['row']['option_name'], 'plugin_seed:child'],
    'rollback recursive trigger sees seed autoload value' => [static fn (): mixed => $rollbackPlan()['effects'][2]['row']['autoload'], 'yes'],
    'successful plan keeps recursive rows' => [static fn (): mixed => array_column($successPlan()['rows'], 'option_name'), ['siteurl', 'home', 'preflight_marker', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child']],
    'successful plan counts seed and children' => [static fn (): mixed => $successPlan()['changes'], 3],
    'successful plan does not rollback' => [static fn (): mixed => $successPlan()['rolled_back_to_savepoint'], false],
    'successful plan has no rollback reason' => [static fn (): mixed => $successPlan()['rollback_reason'], null],
    'successful plan reports none scope' => [static fn (): mixed => $successPlan()['rollback_scope'], 'none'],
    'successful plan inserted names match new rows' => [static fn (): mixed => array_column($successPlan()['inserted'], 'option_name'), ['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child']],
    'successful plan last trigger when skipped' => [static fn (): mixed => $successPlan()['effects'][5]['result'], 'when-skipped'],
    'successful plan keeps dependency tags' => [static fn (): mixed => $successPlan()['dependencies'], ['sqlite-savepoint-current-rollback', 'sqlite-trigger-raise-rollback', 'sqlite-returning-current-row']],
    'recursive trigger disabled suppresses grandchild' => [static fn (): mixed => array_column($run($recursiveTrigger, null, ['recursive_triggers' => false])['rows'], 'option_name'), ['siteurl', 'home', 'preflight_marker', 'plugin_seed', 'plugin_seed:child']],
    'recursive trigger disabled still counts top child' => [static fn (): mixed => $run($recursiveTrigger, null, ['recursive_triggers' => false])['changes'], 2],
    'duplicate seed is ignored without savepoint rollback' => [static fn (): mixed => $run($recursiveTrigger, [['option_id' => 20, 'option_name' => 'siteurl', 'level' => 1, 'autoload' => 'yes']])['ignored'][0]['option_name'], 'siteurl'],
    'duplicate seed leaves rows at savepoint image' => [static fn (): mixed => array_column($run($recursiveTrigger, [['option_id' => 20, 'option_name' => 'siteurl', 'level' => 1, 'autoload' => 'yes']])['rows'], 'option_name'), ['siteurl', 'home', 'preflight_marker']],
    'when in operator can fire rollback' => [static fn (): mixed => $run([[
        'timing' => 'after', 'event' => 'insert', 'table' => 'target', 'action' => 'insert',
        'when' => ['column' => 'option_name', 'operator' => 'in', 'value' => ['plugin_seed']],
        'rollback' => true,
    ]])['rolled_back_to_savepoint'], true],
    'when not equal can skip rollback' => [static fn (): mixed => $run([[
        'timing' => 'after', 'event' => 'insert', 'table' => 'target', 'action' => 'insert',
        'when' => ['column' => 'option_name', 'operator' => '!=', 'value' => 'plugin_seed'],
        'rollback' => true,
    ]])['rolled_back_to_savepoint'], false],
    'empty savepoint name rejected' => [static fn (): mixed => SQLiteSavepointTriggerRollbackPlan::insertRows($outerRows, $savepointRows, $inputRows, $recursiveTrigger, ['option_name'], ''), InvalidArgumentException::class],
    'empty unique columns rejected' => [static fn (): mixed => SQLiteSavepointTriggerRollbackPlan::insertRows($outerRows, $savepointRows, $inputRows, $recursiveTrigger, [], 'plugin_import'), InvalidArgumentException::class],
    'malformed unique column rejected' => [static fn (): mixed => SQLiteSavepointTriggerRollbackPlan::insertRows($outerRows, $savepointRows, $inputRows, $recursiveTrigger, ['1bad'], 'plugin_import'), InvalidArgumentException::class],
    'negative max depth rejected' => [static fn (): mixed => $run($recursiveTrigger, null, ['max_depth' => -1]), InvalidArgumentException::class],
    'max depth overflow rejected' => [static fn (): mixed => $run($recursiveTrigger, null, ['max_depth' => 1]), RuntimeException::class],
    'malformed trigger timing rejected' => [static fn (): mixed => $run([['timing' => 'before', 'event' => 'insert', 'table' => 'target', 'action' => 'insert', 'rollback' => true]]), InvalidArgumentException::class],
    'malformed trigger event rejected' => [static fn (): mixed => $run([['timing' => 'after', 'event' => 'update', 'table' => 'target', 'action' => 'insert', 'rollback' => true]]), InvalidArgumentException::class],
    'malformed trigger target rejected' => [static fn (): mixed => $run([['timing' => 'after', 'event' => 'insert', 'table' => 'side', 'action' => 'insert', 'rollback' => true]]), InvalidArgumentException::class],
    'missing insert row rejected for non rollback trigger' => [static fn (): mixed => $run([['timing' => 'after', 'event' => 'insert', 'table' => 'target', 'action' => 'insert']]), InvalidArgumentException::class],
    'missing new column rejected' => [static fn (): mixed => $run([[
        'timing' => 'after', 'event' => 'insert', 'table' => 'target', 'action' => 'insert',
        'insert_row' => ['option_id' => 'new.missing', 'option_name' => 'child', 'level' => 2, 'autoload' => 'yes'],
    ]]), InvalidArgumentException::class],
    'unsupported when operator rejected' => [static fn (): mixed => $run([[
        'timing' => 'after', 'event' => 'insert', 'table' => 'target', 'action' => 'insert',
        'when' => ['column' => 'level', 'operator' => 'between', 'value' => [1, 2]],
        'rollback' => true,
    ]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['savepoint trigger rollback current next20 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
