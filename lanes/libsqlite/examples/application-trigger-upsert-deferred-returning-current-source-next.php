<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'parent_option_id' => 10, 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'parent_option_id' => 10, 'revision' => 1],
];
$parents = [
    ['parent_id' => 10, 'name' => 'core'],
    ['parent_id' => 20, 'name' => 'network'],
];
$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
    'parent_option_id' => static fn (array $old, array $incoming): mixed => $incoming['parent_option_id'],
    'revision' => static fn (array $old, array $incoming): mixed => $old['revision'] + 1,
];
$triggers = [[
    'name' => 'wp_options_bu_siteurl_suffix',
    'timing' => 'before',
    'event' => 'update',
    'action' => 'set-new',
    'when' => ['new.option_name', '=', 'siteurl'],
    'set' => ['option_value' => 'concat:new.option_value:/wp'],
]];
$returning = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_id', 'as' => 'id'],
    ['expr' => 'old_or_null.option_id', 'as' => 'old_id'],
    ['expr' => 'new.parent_option_id', 'as' => 'parent_id'],
];

$summary = SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan::execute(
    $rows,
    [
        ['option_id' => 11, 'option_name' => 'siteurl', 'option_value' => 'https://broken.test', 'autoload' => 'yes', 'parent_option_id' => 99, 'revision' => 0],
        ['option_id' => 12, 'option_name' => 'fresh_bad', 'option_value' => 'bad', 'autoload' => 'no', 'parent_option_id' => 98, 'revision' => 0],
    ],
    [
        ['option_id' => 21, 'option_name' => 'siteurl', 'option_value' => 'https://retry.test', 'autoload' => 'yes', 'parent_option_id' => 20, 'revision' => 0],
        ['option_id' => 22, 'option_name' => 'fresh_good', 'option_value' => 'ok', 'autoload' => 'no', 'parent_option_id' => 10, 'revision' => 0],
    ],
    ['option_name'],
    $assignments,
    $triggers,
    $returning,
    $parents,
    [
        'child_key' => 'parent_option_id',
        'parent_key' => 'parent_id',
        'child_table' => 'wp_options',
        'parent_table' => 'wp_option_groups',
        'deferred' => true,
    ],
    [
        'savepoint' => 'wp_import_deferred',
        'current_source' => 'wp-options-current',
        'next_source' => 'wp-options-next',
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'trigger-upsert-deferred-returning-current-source-next137-rolled-back');
    assert($summary['commit_blocked_after_returning'] === true);
    assert(array_column($summary['returning_rows'], 'name') === ['siteurl', 'fresh_good']);
    assert(array_column($summary['attempted_current_returning_rows'], 'name') === ['siteurl', 'fresh_bad']);
    assert($summary['next_rows'][0]['option_value'] === 'https://retry.test/wp');
    echo "application-trigger-upsert-deferred-returning-current-source-next137 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
