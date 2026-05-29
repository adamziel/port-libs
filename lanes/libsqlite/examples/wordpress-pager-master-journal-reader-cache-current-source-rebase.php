<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next158.sqlite';
$masterPath = '/srv/wp-content/database/wp-next158.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('wp next158 stale schema before reader recovery'),
    2 => $page('wp next158 stale wp_options before reader recovery'),
    3 => $page('wp next158 stale plugin setting before reader recovery'),
];
$recovered = [
    1 => $page('wp next158 recovered schema current source'),
    2 => $page('wp next158 recovered wp_options current source'),
    3 => $page('wp next158 recovered plugin setting current source'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planCurrentSourceReaderCacheRebase(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n",
    $databasePath . "-journal\n/srv/wp-content/database/site-next158.sqlite-journal\n",
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => ['image' => $recovered[1], 'source_id' => 'wp-next158-source', 'epoch' => 5, 'end_frame' => 2, 'source' => 'reader-cache-after-recovery'],
        2 => ['image' => $before[2], 'source_id' => 'wp-next158-source', 'epoch' => 5, 'end_frame' => 2, 'source' => 'clean-stale-reader-cache'],
        3 => ['image' => $before[3], 'source_id' => 'wp-next158-source', 'epoch' => 5, 'end_frame' => 2, 'pinned' => true, 'source' => 'pinned-plugin-reader-before-recovery'],
    ],
    [1, 2, 3],
    'wp-next158-source',
    5,
    2,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-rebase',
    'status' => $plan['status'],
    'readerCacheStaleRejected' => $plan['reader_cache_stale_rejected'],
    'retainedReaderCachePages' => $plan['retained_reader_cache_page_numbers'],
    'refreshedReaderCachePages' => $plan['refreshed_reader_cache_page_numbers'],
    'invalidatedReaderCachePages' => $plan['invalidated_reader_cache_page_numbers'],
    'nextReadPrefixes' => array_column($plan['next_reads'], 'prefix'),
    'wordpressUse' => 'Copied WordPress option reads after master-journal recovery keep only reader-cache pages rebased to the current source; stale clean pages are refreshed and pinned stale readers are forced back to recovered database bytes.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal recovery and reader cache source tracking primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next158'
    || $summary['readerCacheStaleRejected'] !== true
    || $summary['refreshedReaderCachePages'] !== [2]
    || $summary['invalidatedReaderCachePages'] !== [3]
    || $summary['nextReadPrefixes'][2] !== 'wp next158 recovered plugin setting current source'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-rebase self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-current-source-rebase self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
