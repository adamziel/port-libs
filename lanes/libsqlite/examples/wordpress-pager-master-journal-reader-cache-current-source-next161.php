<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next161.sqlite';
$masterPath = '/srv/wp-content/database/wp-next161.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cachedDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/old-plugin-next161.sqlite-journal");
$currentDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/current-plugin-next161.sqlite-journal");

$before = [
    1 => $page('wp next161 stale schema before current master journal'),
    2 => $page('wp next161 stale active_plugins before current master journal'),
    3 => $page('wp next161 stale plugin settings before current master journal'),
];
$recovered = [
    1 => $page('wp next161 recovered schema from current master journal'),
    2 => $page('wp next161 recovered active_plugins from current master journal'),
    3 => $page('wp next161 recovered plugin settings from current master journal'),
];
$sourceBefore = 'wp-next161-before-current-master-source';

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext161(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n/srv/wp-content/database/old-plugin-next161.sqlite-journal\n",
    $databasePath . "-journal\n/srv/wp-content/database/current-plugin-next161.sqlite-journal\n",
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => ['label' => 'wp-schema-reader-cache', 'image' => $recovered[1], 'source_id' => $sourceBefore, 'epoch' => 3, 'master_journal_digest' => $cachedDigest],
        2 => ['label' => 'wp-pinned-active-plugins', 'image' => $before[2], 'source_id' => $sourceBefore, 'epoch' => 3, 'pinned' => true, 'master_journal_digest' => $currentDigest],
        3 => ['label' => 'wp-dirty-plugin-settings', 'image' => $before[3], 'source_id' => $sourceBefore, 'epoch' => 3, 'dirty' => true, 'master_journal_digest' => $currentDigest],
    ],
    [1, 2, 3],
    [2 => $page('wp next161 active_plugins rewrite after current master reader cache')],
    $sourceBefore,
    3,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next161',
    'status' => $plan['status'],
    'cachedMembershipStale' => $plan['cached_membership_stale'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'staleDigestReason' => $plan['reader_rows'][0]['reason'],
    'staleDigestCacheHit' => $plan['next_reads'][0]['cache_hit'],
    'requiresReaderReopen' => $plan['requires_reader_reopen'],
    'nextWriteBeforePrefix' => $plan['next_writes'][0]['before_prefix'],
    'wordpressUse' => 'A copied wp_options recovery can discard a reader-cache page whose image still matches but whose cache key was built from stale master-journal membership, then journal the next active_plugins write from the recovered current source.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal and reader-cache current-source primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next161'
    || $summary['cachedMembershipStale'] !== true
    || $summary['retainedCachePages'] !== []
    || $summary['invalidatedCachePages'] !== [1, 2, 3]
    || $summary['staleDigestReason'] !== 'reader_cache_master_journal_digest_predates_current_membership'
    || $summary['staleDigestCacheHit'] !== false
    || $summary['requiresReaderReopen'] !== true
    || $summary['nextWriteBeforePrefix'] !== 'wp next161 recovered active_plugins from current master journal'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next161 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next161 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
