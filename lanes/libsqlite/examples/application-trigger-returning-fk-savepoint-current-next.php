<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerReturningFkSavepointCurrentNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningFkSavepointCurrentNextPlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes', 'revision' => 2],
    ['option_id' => 3, 'option_name' => 'plugin_guard', 'option_value' => 'blocked', 'autoload' => 'no', 'revision' => 3],
];
$meta = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'owner', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'owner', 'meta_value' => 'core'],
    ['meta_id' => 12, 'option_id' => 3, 'meta_key' => 'owner', 'meta_value' => 'plugin'],
];

$plan = SQLiteTriggerReturningFkSavepointCurrentNextPlan::update(
    $parents,
    $meta,
    [
        'option_id' => static fn (array $old): int => (int) $old['option_id'] + 100,
        'option_value' => static fn (array $old): string => $old['option_name'] . ':migrated',
        'revision' => static fn (array $old): int => (int) $old['revision'] + 1,
    ],
    static fn (array $row): bool => $row['autoload'] === 'yes',
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'cascade'],
    [[
        'name' => 'wp_options_bu_url_prefix',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.autoload', '=', 'yes'],
        'set' => ['option_value' => 'concat:preview::new.option_name'],
        'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
    ]],
    ['option_id', 'option_name', ['expr' => 'old.option_id', 'as' => 'old_option_id']],
    ['savepoint' => 'wp_option_fk_returning'],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'released');
    assert(array_column($plan['next_child'], 'option_id') === [101, 102, 3]);
    assert(array_column($plan['returning_rows'], 'old_option_id') === [1, 2]);
    echo "application-trigger-returning-fk-savepoint-current-next74 self-test passed\n";
    return;
}

echo json_encode([
    'savepoint' => $plan['savepoint'],
    'status' => $plan['status'],
    'returning' => $plan['returning_rows'],
    'foreign_key_actions' => $plan['foreign_key_actions'],
    'next_child_keys' => array_column($plan['next_child'], 'option_id'),
], JSON_PRETTY_PRINT) . "\n";
