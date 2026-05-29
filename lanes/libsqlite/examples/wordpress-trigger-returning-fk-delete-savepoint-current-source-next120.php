<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test/home', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Example', 'autoload' => 'yes'],
];
$optionMeta = [
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => '_imported'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => '_imported'],
    ['meta_id' => 13, 'option_id' => 3, 'meta_key' => '_imported'],
];
$page = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);

$plan = SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute(
    $options,
    $optionMeta,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_delete' => 'no action', 'deferred' => true],
    [
        'savepoint' => 'wp_options_delete',
        'where' => static fn (array $row): bool => in_array($row['option_name'], ['siteurl', 'home'], true),
        'returning' => [
            ['expr' => 'old.option_id', 'as' => 'deleted_id'],
            'option_name',
            static fn (array $old, string $event): string => $event . ':' . $old['option_name'],
        ],
        'after_triggers' => [
            ['name' => 'wp_options_ad_audit', 'action' => 'insert_child', 'row' => ['meta_id' => 99, 'option_id' => 'old.option_id', 'meta_key' => '_deleted_audit']],
        ],
        'rollback_on_deferred_violation' => true,
        'page_images' => [2 => $page('options-before'), 5 => $page('meta-before')],
        'dirty_pages' => [2 => $page('options-dirty'), 5 => $page('meta-dirty')],
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
    echo "wordpress-trigger-returning-fk-delete-savepoint-current-source-next120 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'returning_before_rollback' => $plan['current_returning_rows'],
    'returning_after_rollback' => $plan['next_returning_rows'],
    'restored_option_ids' => $plan['next_rowids'],
    'rollback_reason' => $plan['rollback_reason'],
], JSON_PRETTY_PRINT) . PHP_EOL;
