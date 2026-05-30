<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerDirtyPageCacheSpillPlan.php';

use PortLibs\LibSqlite\SQLitePagerDirtyPageCacheSpillPlan;

$walPlan = SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(
    16,
    9,
    5,
    [
        ['page' => 4, 'bytes' => 4096, 'walFrame' => 21],
        ['page' => 7, 'bytes' => 4096, 'walFrame' => 22],
        ['page' => 11, 'bytes' => 2048, 'journaled' => false],
    ],
    'wal',
    true,
    'shared',
    true,
    2
);

$rollbackPlan = SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(
    16,
    7,
    5,
    [
        ['page' => 3, 'bytes' => 4096, 'journaled' => true],
        ['page' => 6, 'bytes' => 4096, 'journaled' => true, 'pinned' => true],
        ['page' => 9, 'bytes' => 4096, 'journaled' => true],
    ],
    'delete',
    true,
    'reserved',
    true,
    1
);

if (
    $walPlan['next']['wal_frame_pages'] !== [4, 7]
    || $walPlan['next']['database_image'] !== 'unchanged_until_checkpoint'
    || $rollbackPlan['next']['spilled_pages'] !== [3]
    || $rollbackPlan['operations'][0]['op'] !== 'promote_lock'
) {
    fwrite(STDERR, "application-pager-cache-spill-journalmode-current-source-next107 self-test failed\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo "application-pager-cache-spill-journalmode-current-source-next107 self-test passed\n";
}

return [
    'scenario' => 'application-pager-cache-spill-journalmode-current-source-next107',
    'applicationUse' => 'Plan copied wp_options import dirty-page cache spill with current SQLite journal-mode routing: WAL spills append frames while rollback-journal modes write database pages only after synced journal evidence and exclusive-lock promotion.',
    'wal' => [
        'status' => $walPlan['status'],
        'spill_target' => $walPlan['spill_target'],
        'wal_frame_pages' => $walPlan['next']['wal_frame_pages'],
        'database_image' => $walPlan['next']['database_image'],
    ],
    'rollback' => [
        'status' => $rollbackPlan['status'],
        'spill_target' => $rollbackPlan['spill_target'],
        'spilled_pages' => $rollbackPlan['next']['spilled_pages'],
        'operations' => $rollbackPlan['operations'],
    ],
    'dependencyClosure' => 'no new support component needed; this reuses the existing native PHP pager dirty-page cache-spill planner and records journal-mode-specific WAL versus rollback-journal routing',
];
