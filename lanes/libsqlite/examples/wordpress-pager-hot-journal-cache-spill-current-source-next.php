<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerDirtyPageCacheSpillPlan.php';
require_once __DIR__ . '/../src/SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/srv/wp-content/database/wp-next127.sqlite';

$staleDatabase = $page('wp stale header after crashed importer')
    . $page('wp stale active_plugins after crashed importer')
    . $page('wp stale option row after crashed importer')
    . $page('wp stale autoload index after crashed importer');

$plan = SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan::plan(
    $databasePath,
    $staleDatabase,
    $pageSize,
    [
        1 => $page('wp hot journal recovered header'),
        2 => $page('wp hot journal recovered active_plugins'),
        3 => $page('wp hot journal recovered option row'),
    ],
    [
        ['page' => 2, 'image' => $page('wp retry active_plugins cache page'), 'bytes' => $pageSize, 'journaled' => true],
        ['page' => 3, 'image' => $page('wp retry option row cache page'), 'bytes' => $pageSize, 'journaled' => true],
        ['page' => 4, 'image' => $page('wp retry autoload index pinned page'), 'bytes' => $pageSize, 'journaled' => true, 'pinned' => true],
    ],
    6,
    3,
    true,
    'reserved',
    true,
    2
);

$summary = [
    'scenario' => 'wordpress-pager-hot-journal-cache-spill-current-source-next127',
    'status' => $plan['status'],
    'hotJournalRecoveredPages' => $plan['hot_journal_recovered_page_numbers'],
    'spilledPages' => $plan['spilled_page_numbers'],
    'currentSourceVerified' => $plan['current_source_verified'],
    'page2CurrentSource' => $plan['cache_page_sources'][2]['current_source_prefix'],
    'page2CacheWrite' => $plan['cache_page_sources'][2]['cache_prefix'],
    'wordpressUse' => 'After a copied wp_options import crashes with a hot rollback journal, native PHP pager planning must recover the hot-journal page images before allowing cache-spill writes from the retry transaction.',
    'dependencyClosure' => 'no new support component needed; this reuses the existing native PHP dirty-page cache-spill planner and adds current-source ordering around hot-journal recovery',
    'dependencies' => $plan['dependencies'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
