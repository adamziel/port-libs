<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerCacheSpillMasterJournalCurrentSourceNext150Plan;

require_once __DIR__ . '/../src/SQLitePagerDirtyPageCacheSpillPlan.php';
require_once __DIR__ . '/../src/SQLitePagerCacheSpillMasterJournalCurrentSourceNext150Plan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next150.sqlite';
$journalPath = $databasePath . '-journal';
$masterPath = '/srv/wp-content/database/wp-next150-master-journal';
$siteJournalPath = '/srv/wp-content/database/site-next150.sqlite-journal';
$sourceId = 'wp-next150-current-master';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$databasePages = [
    1 => $page('wp next150 schema root before retry spill'),
    2 => $page('wp next150 options root before retry spill'),
    3 => $page('wp next150 autoload index before retry spill'),
    4 => $page('wp next150 transient cache before retry spill'),
];

$plan = SQLitePagerCacheSpillMasterJournalCurrentSourceNext150Plan::plan(
    $databasePath,
    $journalPath,
    $masterPath,
    $siteJournalPath . "\n" . $journalPath . "\n",
    $journalPath . "\n" . $siteJournalPath . "\n",
    $journalPath . "\n" . $siteJournalPath . "\n",
    implode('', $databasePages),
    $pageSize,
    [
        1 => ['image' => $page('wp next150 dirty schema cache after import'), 'before_image' => $databasePages[1], 'master_member' => $journalPath, 'source_id' => $sourceId, 'epoch' => 3, 'bytes' => $pageSize, 'walFrame' => 31],
        2 => ['image' => $page('wp next150 dirty option cache after import'), 'before_image' => $databasePages[2], 'master_member' => $journalPath, 'source_id' => $sourceId, 'epoch' => 3, 'bytes' => $pageSize, 'walFrame' => 32],
        3 => ['image' => $page('wp next150 stale autoload cache after import'), 'before_image' => $page('wp next150 stale autoload before image'), 'master_member' => $journalPath, 'source_id' => $sourceId, 'epoch' => 3, 'bytes' => $pageSize, 'walFrame' => 33],
        4 => ['image' => $page('wp next150 pinned transient cache after import'), 'before_image' => $databasePages[4], 'master_member' => $journalPath, 'source_id' => $sourceId, 'epoch' => 3, 'pinned' => true, 'bytes' => $pageSize, 'walFrame' => 34],
    ],
    8,
    4,
    'delete',
    true,
    'reserved',
    true,
    null,
    $sourceId,
    3,
);

$summary = [
    'scenario' => 'wordpress-pager-cache-spill-master-journal-current-source-next150',
    'status' => $plan['status'],
    'spilledPages' => $plan['spilled_page_numbers'],
    'rejectedPages' => $plan['rejected_pages'],
    'cachedMasterStale' => $plan['cached_master_stale'],
    'wordpressUse' => 'During copied WordPress SQLite imports that span attached databases, dirty pager-cache pages may spill only after the current master journal is re-read and each page before-image is proven to match the current master-journal source.',
    'dependencyClosure' => 'no new support component needed; this composes lane-local master-journal source validation with the existing native PHP cache-spill journal-mode planner',
];

if ($summary['status'] !== 'pager_cache_spill_master_journal_current_source_next150'
    || $summary['spilledPages'] !== [1, 2]
    || $summary['rejectedPages'][3] !== ['before_image_mismatch_current_database']
    || $summary['rejectedPages'][4] !== ['cache_page_pinned']
) {
    fwrite(STDERR, "wordpress-pager-cache-spill-master-journal-current-source-next150 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-cache-spill-master-journal-current-source-next150 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
