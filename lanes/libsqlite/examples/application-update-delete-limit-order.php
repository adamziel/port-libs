<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteLimitPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24, 'status' => 'keep'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24, 'status' => 'keep'],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'status' => 'keep'],
    ['option_id' => 4, 'option_name' => '_transient_big', 'autoload' => 'no', 'bytes' => 110, 'status' => 'keep'],
    ['option_id' => 5, 'option_name' => '_transient_small', 'autoload' => 'no', 'bytes' => 7, 'status' => 'keep'],
    ['option_id' => 6, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 95, 'status' => 'keep'],
];

$delete = SQLiteUpdateDeleteLimitPlan::delete(
    $options,
    static fn (array $row): bool => $row['autoload'] === 'no',
    [
        ['column' => 'bytes', 'direction' => 'DESC'],
        ['column' => 'option_name'],
    ],
    limit: 2,
    rowIdColumn: 'option_id',
);

$update = SQLiteUpdateDeleteLimitPlan::update(
    $options,
    static fn (array $row): bool => $row['autoload'] === 'no',
    [
        'status' => 'expired',
        'option_value' => static fn (array $row): string => 'expired:' . $row['option_name'],
    ],
    [
        ['column' => 'bytes'],
        ['column' => 'option_name'],
    ],
    limit: 2,
    offset: 1,
    rowIdColumn: 'option_id',
);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options UPDATE/DELETE cleanup statements that use SQLite ORDER BY with LIMIT/OFFSET, selecting affected rows by sorted option metadata while preserving deterministic source-order mutation diagnostics without requiring ext/sqlite.',
    'deleteSelectedOptionIds' => $delete->selectedIds,
    'deleteRemainingOptionIds' => array_column($delete->resultRows, 'option_id'),
    'updateSelectedOptionIds' => $update->selectedIds,
    'updateMutationOptionIds' => $update->mutationIds,
    'updatedStatuses' => array_column($update->resultRows, 'status', 'option_id'),
    'deleteSummary' => $delete->toArray(),
    'updateSummary' => $update->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
