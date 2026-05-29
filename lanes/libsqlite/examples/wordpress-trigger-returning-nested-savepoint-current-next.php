<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerReturningNestedSavepointCurrentNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningNestedSavepointCurrentNextPlan;

$baseRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1],
];
$insertRows = [
    ['option_id' => 2, 'option_name' => 'plugin_abort', 'option_value' => 'draft', 'autoload' => 'no', 'revision' => 0],
];

$plan = SQLiteTriggerReturningNestedSavepointCurrentNextPlan::apply(
    'wp_import_outer',
    'wp_import_insert_release',
    'wp_import_update_rollback',
    $baseRows,
    $insertRows,
    ['option_value' => static fn (array $row): string => 'updated:' . $row['option_name']],
    static fn (array $row): bool => str_starts_with((string) $row['option_name'], 'plugin_'),
    [
        [
            'name' => 'wp_options_bi_import_tag',
            'timing' => 'before',
            'event' => 'insert',
            'action' => 'set-new',
            'set' => ['option_value' => static fn (array $old, array $new): string => 'inserted:' . $new['option_name']],
        ],
        [
            'name' => 'wp_options_au_abort_plugin',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'raise',
            'raise' => 'rollback',
            'when' => ['new.option_name', '=', 'plugin_abort'],
            'reason' => 'plugin update trigger aborts child savepoint',
        ],
    ],
    ['option_name', ['expr' => 'new.option_value', 'as' => 'next_value']],
);

if ($plan['status'] !== 'child-rolled-back' || count($plan['next_rows']) !== 2 || $plan['next_rows'][1]['option_value'] !== 'inserted:plugin_abort') {
    fwrite(STDERR, "wordpress-trigger-returning-nested-savepoint self-test failed\n");
    exit(1);
}

fwrite(STDOUT, "wordpress-trigger-returning-nested-savepoint self-test passed\n");
fwrite(STDOUT, json_encode([
    'scenario' => 'wordpress-trigger-returning-nested-savepoint',
    'status' => $plan['status'],
    'released_returning' => $plan['released_returning_rows'],
    'rolled_back_current_returning' => $plan['rollback_current_returning_rows'],
    'next_returning' => $plan['next_returning_rows'],
    'wordpressUse' => 'Preview nested wp_options import savepoints where INSERT RETURNING rows survive RELEASE into the outer transaction but a later trigger RAISE(ROLLBACK) suppresses only the rolled-back UPDATE RETURNING rows.',
], JSON_PRETTY_PRINT) . PHP_EOL);
