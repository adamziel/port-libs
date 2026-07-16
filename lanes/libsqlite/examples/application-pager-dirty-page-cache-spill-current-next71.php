<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerDirtyPageCacheSpillPlan.php';

use PortLibs\LibSqlite\SQLitePagerDirtyPageCacheSpillPlan;

$plan = SQLitePagerDirtyPageCacheSpillPlan::currentNext(
    12,
    7,
    5,
    [
        ['page' => 2, 'bytes' => 4096, 'journaled' => true, 'pinned' => true],
        ['page' => 5, 'bytes' => 4096, 'journaled' => true],
        ['page' => 8, 'bytes' => 1024, 'journaled' => false],
        ['page' => 9, 'bytes' => 4096, 'journaled' => true],
    ],
    true,
    'reserved',
    true,
    1
);

if ($plan['status'] !== 'spilled' || $plan['next']['spilled_pages'] !== [5] || $plan['next']['dirty_pages'] !== [2, 8, 9]) {
    fwrite(STDERR, "application-pager-dirty-page-cache-spill-current-next71 self-test failed\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo "application-pager-dirty-page-cache-spill-current-next71 self-test passed\n";
}

return [
    'scenario' => 'application-pager-dirty-page-cache-spill-current-next71',
    'applicationUse' => 'Plan SQLite pager dirty-page cache spill for copied wp_options imports where journaled unpinned dirty pages may be written before commit only after journal sync and exclusive-lock promotion.',
    'status' => $plan['status'],
    'spilled_pages' => $plan['next']['spilled_pages'],
    'remaining_dirty_pages' => $plan['next']['dirty_pages'],
    'operations' => $plan['operations'],
    'dependencyClosure' => 'no new support component needed; this is bounded pager state planning over existing native PHP rollback-journal and VFS lock/write primitives',
];
