<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan;

$pageSize = 104;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceDigestFence(
    $pageSize,
    'wp_option_import',
    'journal-before-hot:156',
    'hot-recovered:157',
    [
        1 => ['image' => $page('wp schema root recovered clean'), 'source' => 'database-cache', 'source_id' => 'hot-recovered:157', 'epoch' => 157],
        2 => ['image' => $page('wp active_plugins stale same token'), 'source' => 'wal-cache', 'source_id' => 'hot-recovered:157', 'epoch' => 157],
        3 => ['image' => $page('wp plugin settings stale same token'), 'source' => 'database-cache', 'source_id' => 'hot-recovered:157', 'epoch' => 157],
        4 => ['image' => $page('wp autoload index dirty failed write'), 'source' => 'savepoint-cache', 'source_id' => 'hot-recovered:157', 'epoch' => 157, 'dirty' => true],
    ],
    [
        2 => $page('wp active_plugins hot recovered current'),
    ],
    [
        1 => $page('wp schema root recovered clean'),
        3 => $page('wp plugin settings recovered current'),
        4 => $page('wp autoload index recovered current'),
    ],
    [
        2 => $page('wp active_plugins failed import write'),
        3 => $page('wp plugin settings failed import write'),
    ],
    [2, 3],
    [
        2 => $page('wp active_plugins retry import write'),
        3 => $page('wp plugin settings retry import write'),
    ],
    [1, 2, 3, 4],
    156
);

$summary = [
    'scenario' => 'application-pager-hot-journal-savepoint-cache-current-source-next157',
    'applicationUse' => 'A copied Application options import rejects stale same-token pager-cache images after hot-journal recovery before capturing savepoint and retry before-images.',
    'status' => $plan['status'],
    'retainedPages' => $plan['cache']['retained_page_numbers'],
    'invalidatedPages' => $plan['cache']['invalidated_page_numbers'],
    'invalidatedReasons' => array_column($plan['cache']['invalidated_entries'], 'reason'),
    'savepointBefore' => $plan['savepoint_before_page_numbers'],
    'retryBefore' => $plan['retry_before_page_numbers'],
    'dirtyAfterRetry' => $plan['dirty_page_numbers'],
    'dependencyClosure' => 'no new support component needed; reuses native pager cache, hot-journal recovery, and savepoint before-image primitives',
];

if (
    $summary['status'] !== 'pager_hot_journal_savepoint_cache_current_source_next157'
    || $summary['retainedPages'] !== [1]
    || $summary['invalidatedPages'] !== [2, 3, 4]
    || $summary['savepointBefore'] !== [2, 3]
    || $summary['retryBefore'] !== [2, 3]
) {
    fwrite(STDERR, "unexpected pager hot-journal savepoint cache current-source next157 summary\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
