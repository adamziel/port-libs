<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next167.sqlite';
$masterPath = '/srv/wp-content/database/wp-next167.sqlite-mj';
$sourceId = 'wp-next167-current-after-master-delete';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$pages = [
    1 => $page('wp next167 schema current after master delete'),
    2 => $page('wp next167 options current after master delete'),
    3 => $page('wp next167 active_plugins current after master delete'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext167(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n/srv/wp-content/database/wp-next167-site.sqlite-journal\n",
    $pageSize,
    $pages,
    [
        1 => ['image' => $pages[1], 'source_id' => $sourceId, 'epoch' => 5, 'reader_id' => 'schema-reader', 'master_generation' => 9, 'master_deleted' => true, 'shared' => true],
        2 => ['image' => $page('wp next167 options stale before master delete'), 'source_id' => $sourceId, 'epoch' => 5, 'reader_id' => 'options-reader', 'master_generation' => 9, 'master_deleted' => true],
        3 => ['image' => $pages[3], 'source_id' => $sourceId, 'epoch' => 5, 'reader_id' => 'active-plugins-reader', 'master_generation' => 8, 'master_deleted' => true],
    ],
    [
        ['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 5, 'master_generation' => 9],
        ['reader_id' => 'options-reader', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 5, 'master_generation' => 9],
        ['reader_id' => 'active-plugins-reader', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 5, 'master_generation' => 8],
    ],
    $sourceId,
    5,
    9,
    true,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next167',
    'status' => $plan['status'],
    'masterJournalDeleted' => $plan['current_source']['master_journal_deleted'],
    'retainedPages' => $plan['retained_page_numbers'],
    'refreshedPages' => $plan['refreshed_page_numbers'],
    'invalidatedPages' => $plan['invalidated_page_numbers'],
    'schemaCacheHit' => $plan['read_cache_hits']['schema-reader'],
    'optionsPrefix' => $plan['read_prefixes']['options-reader'],
    'activePluginsReason' => $plan['cache_rows'][2]['reason'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'wordpressUse' => 'A copied wp_options database can keep a shared schema cache page, refresh a stale options page, and reopen an active_plugins reader whose cache ticket still belongs to the previous master-journal generation after the master file is deleted.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal membership and reader-cache source ticket primitives',
];

if (
    $summary['status'] !== 'pager-master-journal-reader-cache-current-source-next167'
    || $summary['masterJournalDeleted'] !== true
    || $summary['retainedPages'] !== [1]
    || $summary['refreshedPages'] !== [2]
    || $summary['invalidatedPages'] !== [3]
    || $summary['schemaCacheHit'] !== true
    || $summary['optionsPrefix'] !== 'wp next167 options current after master delete'
    || $summary['activePluginsReason'] !== 'reader_cache_master_generation_mismatch'
    || $summary['reopenReaders'] !== ['active-plugins-reader']
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next167 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next167 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
