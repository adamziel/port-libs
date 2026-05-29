<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteDmlTriggerRecursionPlan.php';
require __DIR__ . '/../src/SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan.php';
require __DIR__ . '/../src/SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan;

$plan = SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::executeNext147(
    [['option_id' => 1, 'option_name' => 'siteurl', 'depth' => 0, 'autoload' => 'yes']],
    [['option_id' => 10, 'option_name' => 'plugin_current', 'depth' => 1, 'autoload' => 'yes']],
    [['option_id' => 20, 'option_name' => 'plugin_next', 'depth' => 1, 'autoload' => 'no']],
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
    [
        'new.option_id',
        ['expr' => 'option_name', 'as' => 'name'],
        'depth',
        'autoload',
    ],
    [
        'savepoint' => 'wp_recursive_options_import',
        'current_source' => 'wp@trigger147-current',
        'next_source' => 'wp@trigger147-next',
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $plan['status'] !== 'trigger-recursive-returning-savepoint-current-source-next147-current-rolled-back-next-admitted'
        || array_column($plan['returning_rows'], 'name') !== ['plugin_next', 'plugin_next:child', 'plugin_next:child:child']
        || array_column($plan['suppressed_returning_rows'], 'name') !== ['plugin_current', 'plugin_current:child', 'plugin_current:child:child']
        || array_column($plan['final_rows'], 'option_name') !== ['siteurl', 'plugin_next', 'plugin_next:child', 'plugin_next:child:child']
    ) {
        fwrite(STDERR, "wordpress-trigger-recursive-returning-savepoint-current-source-next147 self-test failed\n");
        exit(1);
    }

    echo "wordpress-trigger-recursive-returning-savepoint-current-source-next147 self-test passed\n";
    exit(0);
}

echo json_encode([
    'status' => $plan['status'],
    'admittedReturning' => array_column($plan['returning_rows'], 'name'),
    'suppressedReturning' => array_column($plan['suppressed_returning_rows'], 'name'),
    'finalRows' => array_column($plan['final_rows'], 'option_name'),
    'wordpressUse' => 'Model a copied wp_options import savepoint where recursive trigger RETURNING rows are yielded by the failed current source, suppressed after ROLLBACK TO, and the next source restarts from the savepoint image.',
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
