<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerReturningSavepointCurrentNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningSavepointCurrentNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'plugin_skip', 'option_value' => 'skip', 'autoload' => 'no', 'revision' => 1],
    ['option_id' => 3, 'option_name' => 'plugin_abort', 'option_value' => 'bad', 'autoload' => 'no', 'revision' => 1],
];

$plan = SQLiteTriggerReturningSavepointCurrentNextPlan::updateRows(
    'wp_option_import',
    $rows,
    [
        'option_value' => static fn (array $row): string => 'imported:' . $row['option_name'],
        'revision' => static fn (array $row): int => (int) $row['revision'] + 1,
    ],
    static fn (array $row): bool => $row['autoload'] === 'yes' || str_starts_with((string) $row['option_name'], 'plugin_'),
    [
        [
            'name' => 'wp_options_bu_skip_plugin',
            'timing' => 'before',
            'event' => 'update',
            'action' => 'raise',
            'raise' => 'ignore',
            'when' => ['old.option_name', '=', 'plugin_skip'],
            'reason' => 'plugin owns this option',
        ],
        [
            'name' => 'wp_options_au_abort_plugin',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'raise',
            'raise' => 'rollback',
            'when' => ['new.option_name', '=', 'plugin_abort'],
            'reason' => 'plugin aborts import',
        ],
    ],
    [
        'option_name',
        ['expr' => 'old.option_value', 'as' => 'old_value'],
        ['expr' => 'new.option_value', 'as' => 'new_value'],
    ],
);

if ($plan['status'] !== 'rolled-back' || $plan['rows'] !== $rows || count($plan['yield_stream']) !== 2 || count($plan['skipped']) !== 1) {
    fwrite(STDERR, "wordpress-trigger-returning-savepoint-current-next65 self-test failed\n");
    exit(1);
}

fwrite(STDOUT, "wordpress-trigger-returning-savepoint-current-next65 self-test passed\n");
fwrite(STDOUT, json_encode([
    'scenario' => 'wordpress-trigger-returning-savepoint-current-next65',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'diagnostic_yields' => array_column(array_column($plan['yield_stream'], 'returning'), 'option_name'),
    'skipped_options' => array_column(array_column($plan['skipped'], 'current_row'), 'option_name'),
    'rollback_reason' => $plan['rollback_reason'],
    'wordpressUse' => 'Preview wp_options trigger RETURNING current/next diagnostics when RAISE(IGNORE) skips plugin-owned rows and a later trigger RAISE(ROLLBACK) restores the import savepoint.',
], JSON_PRETTY_PRINT) . PHP_EOL);
