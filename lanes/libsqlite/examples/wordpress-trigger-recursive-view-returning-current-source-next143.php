<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::insertThroughViewSources(
    [['option_id' => 10, 'option_name' => 'siteurl', 'depth' => 0, 'autoload' => 'yes']],
    [['option_id' => 20, 'option_name' => 'plugin_current', 'depth' => 1, 'autoload' => 'yes']],
    [['option_id' => 30, 'option_name' => 'plugin_next', 'depth' => 1, 'autoload' => 'no']],
    [[
        'timing' => 'after',
        'event' => 'insert',
        'table' => 'target',
        'action' => 'insert',
        'when' => ['column' => 'depth', 'operator' => '<', 'value' => 3],
        'insert_row' => [
            'option_id' => 'new_increment.option_id',
            'option_name' => 'concat:new.option_name::child',
            'depth' => 'new_increment.depth',
            'autoload' => 'new.autoload',
        ],
    ]],
    ['option_name'],
    ['new.option_id', ['expr' => 'option_name', 'as' => 'name'], 'depth'],
    [
        'view' => 'wp_option_import_view',
        'savepoint' => 'wp_import_next143',
        'current_source' => 'wp-options@before-current-recursive-view-import',
        'next_source' => 'wp-options@after-next-recursive-view-import',
    ],
);

$summary = [
    'scenario' => 'wordpress-trigger-recursive-view-returning-current-source-next143',
    'wordpressUse' => 'A copied wp_options import can yield RETURNING rows from an INSTEAD OF view trigger chain before rolling the current source back to a savepoint, then admit the next source from the saved image.',
    'status' => $plan['status'],
    'nextInput' => $plan['source_transition']['next_input'],
    'admittedNext' => array_column($plan['returning_rows'], 'name'),
    'suppressedCurrent' => array_column($plan['suppressed_returning_rows'], 'name'),
    'finalOptions' => array_column($plan['final_rows'], 'option_name'),
    'discardedReturning' => $plan['discarded_returning_count'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'current-recursive-view-rollback-next-source-admitted');
    assert($summary['nextInput'] === 'saved-current-source');
    assert($summary['admittedNext'] === ['plugin_next', 'plugin_next:child', 'plugin_next:child:child']);
    assert($summary['suppressedCurrent'] === ['plugin_current', 'plugin_current:child', 'plugin_current:child:child']);
    assert($summary['finalOptions'] === ['siteurl', 'plugin_next', 'plugin_next:child', 'plugin_next:child:child']);
    assert($summary['discardedReturning'] === 3);
    echo "wordpress-trigger-recursive-view-returning-current-source-next143 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
