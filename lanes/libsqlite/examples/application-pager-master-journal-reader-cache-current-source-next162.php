<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next162.sqlite';
$masterPath = '/srv/wp-content/database/wp-next162.sqlite-mj';
$sourceId = 'wp-next162-master-current-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$currentPages = [
    1 => $page('wp next162 schema recovered by master journal'),
    2 => $page('wp next162 options root recovered by master journal'),
    3 => $page('wp next162 plugin setting recovered by master journal'),
];

$summary = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planCurrentMasterJournalSourceEpochFence(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n",
    $databasePath . "-journal\n/srv/wp-content/database/wp-site-next162.sqlite-journal\n",
    $pageSize,
    $currentPages,
    [
        1 => ['image' => $currentPages[1], 'source_id' => $sourceId, 'epoch' => 4, 'reader_id' => 'schema-reader'],
        2 => ['image' => $page('wp next162 stale options root before master journal'), 'source_id' => $sourceId, 'epoch' => 4, 'reader_id' => 'options-reader'],
        3 => ['image' => $currentPages[3], 'source_id' => $sourceId, 'epoch' => 4, 'reader_id' => 'plugin-reader', 'next_source' => true],
    ],
    [
        ['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 4, 'end_frame' => 2],
        ['reader_id' => 'options-reader', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 4, 'end_frame' => 2],
        ['reader_id' => 'plugin-reader', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 4, 'end_frame' => 3],
    ],
    $sourceId,
    4,
    2,
);

$result = [
    'scenario' => 'application-pager-master-journal-reader-cache-current-source-next162',
    'status' => $summary['status'],
    'membershipChanged' => $summary['membership_changed'],
    'retainedPages' => $summary['cache']['retained_page_numbers'],
    'invalidatedPages' => $summary['cache']['invalidated_page_numbers'],
    'schemaCacheHit' => $summary['read_cache_hits']['schema-reader'],
    'optionsPrefix' => $summary['read_prefixes']['options-reader'],
    'pluginPrefix' => $summary['read_prefixes']['plugin-reader'],
    'reopenReaders' => $summary['reopen_reader_ids'],
    'applicationUse' => 'After a copied wp_options database recovers through a master journal, the next PHP reader reuses only cache pages proven to match the current source and reopens stale or speculative reader tickets.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local master-journal membership and pager reader-cache source tracking',
];

if (
    $result['status'] !== 'pager-master-journal-reader-cache-current-source-next162'
    || $result['retainedPages'] !== [1]
    || $result['invalidatedPages'] !== [2, 3]
    || $result['schemaCacheHit'] !== true
    || $result['optionsPrefix'] !== 'wp next162 options root recovered by master journal'
    || $result['reopenReaders'] !== ['plugin-reader']
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-current-source-next162 self-test failed\n");
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
