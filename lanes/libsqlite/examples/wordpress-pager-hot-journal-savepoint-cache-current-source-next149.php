<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan;

$pageSize = 96;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceNextStatement(
    $pageSize,
    'wp_option_import',
    'retry_active_plugins',
    'journal-before-hot:149',
    'hot-recovered:150',
    [
        1 => ['image' => $page('wp schema root cached'), 'source' => 'database-cache', 'source_id' => 'journal-before-hot:149', 'epoch' => 149],
        2 => ['image' => $page('wp active_plugins stale wal cache'), 'source' => 'wal-cache', 'source_id' => 'journal-before-hot:149', 'epoch' => 149],
        3 => ['image' => $page('wp plugin option dirty savepoint'), 'source' => 'savepoint-write', 'source_id' => 'journal-before-hot:149', 'epoch' => 149, 'dirty' => true, 'savepoint' => 'wp_option_import'],
        4 => ['image' => $page('wp autoload index pinned'), 'source' => 'database-cache', 'source_id' => 'journal-before-hot:149', 'epoch' => 149, 'pinned' => true],
    ],
    [
        2 => $page('wp active_plugins hot journal recovered'),
    ],
    [
        3 => $page('wp plugin option savepoint before'),
        4 => $page('wp autoload index savepoint before'),
    ],
    [
        2 => $page('wp active_plugins retry write'),
        3 => $page('wp plugin option retry write'),
    ],
    [1, 2, 3, 4, 5],
    149
);

$summary = [
    'scenario' => 'wordpress-pager-hot-journal-savepoint-cache-current-source-next149',
    'wordpressUse' => 'A copied WordPress options import recovers a hot rollback journal, rolls back the active savepoint, refreshes page-cache source tokens, and captures clean before-images before retrying the next active_plugins statement.',
    'status' => $plan['status'],
    'retainedPages' => $plan['cache']['retained_page_numbers'],
    'invalidatedPages' => $plan['cache']['invalidated_page_numbers'],
    'hotPages' => $plan['cache']['hot_journal_page_numbers'],
    'savepointPages' => $plan['cache']['savepoint_before_page_numbers'],
    'nextWritePages' => $plan['next_statement']['write_page_numbers'],
    'dirtyPagesAfterRetry' => $plan['cache']['dirty_page_numbers'],
    'dependencyClosure' => 'no new support component needed; this reuses native PHP pager cache source-token, hot-journal recovery, and savepoint page-image rollback modeling',
];

if (
    $summary['status'] !== 'pager_hot_journal_savepoint_cache_current_source_next149'
    || $summary['retainedPages'] !== [1]
    || $summary['invalidatedPages'] !== [2, 3, 4]
    || $summary['nextWritePages'] !== [2, 3]
) {
    fwrite(STDERR, "unexpected pager hot-journal savepoint cache summary\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
