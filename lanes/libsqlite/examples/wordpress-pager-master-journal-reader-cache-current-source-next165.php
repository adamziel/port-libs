<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next165.sqlite';
$masterPath = '/srv/wp-content/database/wp-next165.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next165-network.sqlite-journal\n";
$digest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-next165-network.sqlite-journal");
$sourceId = 'wp-next165-current-master-header';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$pages = [
    1 => $page('wp next165 page1 change counter schema cookie current'),
    2 => $page('wp next165 active_plugins current after master recovery'),
    3 => $page('wp next165 autoload index current after master recovery'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext165(
    $databasePath,
    $masterPath,
    $masterBytes,
    $pageSize,
    $pages,
    [
        1 => ['image' => $pages[1], 'source_id' => $sourceId, 'epoch' => 6, 'reader_id' => 'wp-header-reader', 'master_journal_digest' => $digest, 'change_counter' => 18, 'schema_cookie' => 4, 'end_frame' => 5],
        2 => ['image' => $pages[2], 'source_id' => $sourceId, 'epoch' => 6, 'reader_id' => 'wp-active-reader', 'master_journal_digest' => $digest, 'change_counter' => 17, 'schema_cookie' => 4, 'end_frame' => 5],
        3 => ['image' => $page('wp next165 stale autoload index before schema cookie'), 'source_id' => $sourceId, 'epoch' => 6, 'reader_id' => 'wp-index-reader', 'master_journal_digest' => $digest, 'change_counter' => 18, 'schema_cookie' => 3, 'end_frame' => 5],
    ],
    [
        ['reader_id' => 'wp-header-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 6, 'change_counter' => 18, 'schema_cookie' => 4, 'end_frame' => 5],
        ['reader_id' => 'wp-active-reader', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 6, 'change_counter' => 17, 'schema_cookie' => 4, 'end_frame' => 5],
        ['reader_id' => 'wp-index-reader', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 6, 'change_counter' => 18, 'schema_cookie' => 3, 'end_frame' => 5],
    ],
    $sourceId,
    6,
    18,
    4,
    5,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next165',
    'status' => $plan['status'],
    'retainedCachePages' => $plan['cache']['retained_page_numbers'],
    'invalidatedCachePages' => $plan['cache']['invalidated_page_numbers'],
    'headerCacheHit' => $plan['read_cache_hits']['wp-header-reader'],
    'activePluginsCacheHit' => $plan['read_cache_hits']['wp-active-reader'],
    'indexPrefix' => $plan['read_prefixes']['wp-index-reader'],
    'wordpressUse' => 'A copied wp_options recovery can retain a page-1 reader cache only when the current master-journal membership, change counter, schema cookie, and WAL end-frame still match; active_plugins and autoload pages with stale header tickets reopen from the recovered current source.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache and header-generation primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next165'
    || $summary['retainedCachePages'] !== [1]
    || $summary['invalidatedCachePages'] !== [2, 3]
    || $summary['headerCacheHit'] !== true
    || $summary['activePluginsCacheHit'] !== false
    || $summary['indexPrefix'] !== 'wp next165 autoload index current after master recovery'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next165 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next165 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
