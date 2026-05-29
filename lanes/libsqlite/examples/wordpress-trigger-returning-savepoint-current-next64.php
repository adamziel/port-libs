<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningForeignKeySavepointPlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test/home', 'autoload' => 'yes'],
];
$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => '_origin'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => '_origin'],
];
$plan = SQLiteTriggerReturningForeignKeySavepointPlan::savepointBoundaryYield(
    $parents,
    $children,
    [
        'parent_key' => 'option_id',
        'child_key' => 'option_id',
        'on_update' => 'no action',
        'on_delete' => 'no action',
        'deferred' => true,
    ],
    [
        [
            'name' => 'wp_options_bu_touch',
            'timing' => 'before',
            'event' => 'update',
            'action' => 'set-new',
            'set' => ['option_value' => 'triggered-before-returning'],
        ],
    ],
    [
        'operation' => 'update',
        'savepoint' => 'wp_import_batch',
        'where' => static fn (array $row): bool => $row['autoload'] === 'yes',
        'assignments' => ['option_id' => static fn (array $row): int => (int) $row['option_id'] + 100],
        'returning' => [
            ['expr' => 'old.option_id', 'as' => 'old_id'],
            ['expr' => 'new.option_id', 'as' => 'new_id'],
            'option_name',
            'option_value',
        ],
        'rollback_on_deferred_violation' => true,
        'page_images' => [2 => str_repeat('O', 512)],
        'dirty_pages' => [2 => str_repeat('D', 512), 3 => str_repeat('M', 512)],
        'wal_start_frame' => 9,
        'wal_frames' => [
            ['frame_index' => 10, 'page_number' => 2],
            ['frame_index' => 11, 'page_number' => 3, 'commit_frame' => true],
        ],
    ],
);

echo json_encode([
    'status' => $plan['status'],
    'boundary' => $plan['current_next_boundary'],
    'current_returning_rows' => $plan['current_returning_rows'],
    'next_returning_rows' => $plan['next_returning_rows'],
    'current_rowids' => $plan['current_rowids'],
    'next_rowids' => $plan['next_rowids'],
    'rollback_page_numbers' => $plan['rollback_page_numbers'],
    'discarded_wal_frames' => array_column($plan['discarded_wal_frames'], 'frame_index'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
