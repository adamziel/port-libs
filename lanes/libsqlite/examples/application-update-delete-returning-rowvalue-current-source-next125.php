<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24, 'status' => 'keep', 'option_value' => 'https://example.test'],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24, 'status' => 'keep', 'option_value' => 'https://example.test'],
        ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'status' => 'keep', 'option_value' => 'feed'],
        ['option_id' => 4, 'option_name' => '_transient_big', 'autoload' => 'no', 'bytes' => 110, 'status' => 'keep', 'option_value' => str_repeat('x', 8)],
        ['option_id' => 5, 'option_name' => '_transient_small', 'autoload' => 'no', 'bytes' => 7, 'status' => 'keep', 'option_value' => 'tiny'],
        ['option_id' => 6, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 95, 'status' => 'keep', 'option_value' => 'plugins'],
        ['option_id' => 7, 'option_name' => 'orphaned_cache', 'autoload' => null, 'bytes' => 50, 'status' => 'keep', 'option_value' => 'nullable'],
    ],
];

$delete = SQLiteUpdateDeleteReturningSql::execute(
    "DELETE FROM wp_options WHERE (autoload, bytes) BETWEEN ('no', 10) AND ('no', 100) RETURNING option_id, option_name, (autoload, bytes) BETWEEN ('no', 10) AND ('no', 100) AS in_range ORDER BY option_id",
    $tables,
);

$update = SQLiteUpdateDeleteReturningSql::execute(
    "UPDATE wp_options SET (status, option_value) = (autoload, option_name || ':row-value') WHERE (autoload, bytes) NOT BETWEEN ('no', 10) AND ('yes', 23) AND option_id IN (1, 2, 4) RETURNING option_id, status, option_value ORDER BY option_id",
    $tables,
);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options cleanup SQL that uses row-value BETWEEN predicates with UPDATE/DELETE RETURNING, keeping SQLite current-source NULL and simultaneous row-value assignment behavior in native PHP.',
    'deleteSelectedIds' => $delete['plan']->selectedIds,
    'deleteReturning' => $delete['returning'],
    'deleteRemainingIds' => array_column($delete['tables']['wp_options'], 'option_id'),
    'updateSelectedIds' => $update['plan']->selectedIds,
    'updateReturning' => $update['returning'],
    'updatedStatuses' => array_column($update['tables']['wp_options'], 'status', 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
