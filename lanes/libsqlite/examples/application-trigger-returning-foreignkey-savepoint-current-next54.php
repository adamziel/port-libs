<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningForeignKeySavepointPlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_cache', 'option_value' => 'stale', 'autoload' => 'no'],
];
$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source'],
];
$plan = SQLiteTriggerReturningForeignKeySavepointPlan::run(
    $parents,
    $children,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'no action', 'deferred' => true],
    [],
    [
        'operation' => 'update',
        'savepoint' => 'wp_import',
        'where' => static fn (array $row): bool => $row['option_name'] === 'plugin_cache',
        'assignments' => ['option_id' => 202],
        'returning' => ['option_id', 'option_name', ['expr' => 'old.option_id', 'as' => 'old_option_id']],
        'rollback_on_deferred_violation' => true,
        'page_images' => [2 => str_repeat('P', 512), 3 => str_repeat('M', 512)],
        'dirty_pages' => [2 => str_repeat('p', 512), 3 => str_repeat('m', 512)],
        'wal_start_frame' => 4,
        'wal_frames' => [
            ['frame_index' => 5, 'page_number' => 2],
            ['frame_index' => 6, 'page_number' => 3, 'commit_frame' => true],
        ],
    ],
);

if (($argv[1] ?? null) === '--self-test') {
    if ($plan['status'] !== 'rolled-back' || $plan['changes'] !== 0 || count($plan['attempted_yielded']) !== 1 || $plan['rollback_page_numbers'] !== [2, 3]) {
        fwrite(STDERR, "application-trigger-returning-foreignkey-savepoint-current-next54 self-test failed\n");
        exit(1);
    }
    echo "application-trigger-returning-foreignkey-savepoint-current-next54 self-test passed\n";
    exit(0);
}

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'attempted_returning' => $plan['attempted_yielded'][0]['returning'] ?? [],
    'committed_changes' => $plan['changes'],
    'attempted_changes' => $plan['attempted_changes'],
    'foreign_key_violations' => count($plan['foreign_key_violations']),
    'rollback_pages' => $plan['rollback_page_numbers'],
    'discarded_wal_frames' => array_column($plan['discarded_wal_frames'], 'frame_index'),
], JSON_PRETTY_PRINT) . "\n";
