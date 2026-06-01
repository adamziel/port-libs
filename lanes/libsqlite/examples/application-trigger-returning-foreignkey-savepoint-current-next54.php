<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningForeignKeySavepointPlan;

$parents = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'module_cache', 'key_value' => 'stale', 'load_policy' => 'no'],
];
$children = [
    ['meta_id' => 10, 'setting_id' => 1, 'meta_key' => 'source'],
    ['meta_id' => 11, 'setting_id' => 2, 'meta_key' => 'source'],
];
$plan = SQLiteTriggerReturningForeignKeySavepointPlan::run(
    $parents,
    $children,
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'no action', 'deferred' => true],
    [],
    [
        'operation' => 'update',
        'savepoint' => 'app_settings_import',
        'where' => static fn (array $row): bool => $row['key_name'] === 'module_cache',
        'assignments' => ['setting_id' => 202],
        'returning' => ['setting_id', 'key_name', ['expr' => 'old.setting_id', 'as' => 'old_setting_id']],
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
