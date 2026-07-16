<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan;

$settings = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'public_url', 'key_value' => 'https://example.test/public_url', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'site_title', 'key_value' => 'Example', 'load_policy' => 'yes'],
];
$settingRefs = [
    ['meta_id' => 11, 'setting_id' => 1, 'meta_key' => '_imported'],
    ['meta_id' => 12, 'setting_id' => 2, 'meta_key' => '_imported'],
    ['meta_id' => 13, 'setting_id' => 3, 'meta_key' => '_imported'],
];
$page = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);

$plan = SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute(
    $settings,
    $settingRefs,
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_delete' => 'no action', 'deferred' => true],
    [
        'savepoint' => 'app_settings_delete',
        'where' => static fn (array $row): bool => in_array($row['key_name'], ['base_url', 'public_url'], true),
        'returning' => [
            ['expr' => 'old.setting_id', 'as' => 'deleted_id'],
            'key_name',
            static fn (array $old, string $event): string => $event . ':' . $old['key_name'],
        ],
        'after_triggers' => [
            ['name' => 'app_settings_ad_audit', 'action' => 'insert_child', 'row' => ['meta_id' => 99, 'setting_id' => 'old.setting_id', 'meta_key' => '_deleted_audit']],
        ],
        'rollback_on_deferred_violation' => true,
        'page_images' => [2 => $page('settings-before'), 5 => $page('meta-before')],
        'dirty_pages' => [2 => $page('settings-dirty'), 5 => $page('meta-dirty')],
        'wal_start_frame' => 44,
        'wal_frames' => [
            ['frame_index' => 45, 'page_number' => 2],
            ['frame_index' => 46, 'page_number' => 5, 'commit_frame' => true],
        ],
    ],
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'rolled-back');
    assert($plan['rollback_reason'] === 'deferred-foreign-key-violation');
    assert($plan['current_returning_rows'][0]['deleted_id'] === 1);
    assert($plan['next_returning_rows'] === []);
    assert($plan['next_rowids'] === [1, 2, 3]);
    assert($plan['rollback_to_wal_frame'] === 44);
    echo "application-trigger-returning-fk-delete-savepoint-current-source-next120 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'returning_before_rollback' => $plan['current_returning_rows'],
    'returning_after_rollback' => $plan['next_returning_rows'],
    'restored_setting_ids' => $plan['next_rowids'],
    'rollback_reason' => $plan['rollback_reason'],
], JSON_PRETTY_PRINT) . PHP_EOL;
