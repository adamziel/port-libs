<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 96;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = '/tmp/wp-content/database/.ht.sqlite';
$members = [
    $database . '-journal',
    '/tmp/wp-content/database/wp_comments.sqlite-journal',
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext160(
    $database,
    '/tmp/wp-content/database/.ht.sqlite-mj160',
    $database . "-journal\n/tmp/wp-content/database/old-cache.sqlite-journal\n",
    implode("\n", $members) . "\n",
    $pageSize,
    [
        1 => $page('wp schema root current master source'),
        2 => $page('wp active_plugins current recovered'),
        3 => $page('wp plugin settings current recovered'),
    ],
    [
        1 => ['image' => $page('wp schema root current master source'), 'source_id' => 'master-reader-current:160', 'epoch' => 160, 'master_members' => $members],
        2 => ['image' => $page('wp stale active_plugins reader cache'), 'source_id' => 'master-reader-current:160', 'epoch' => 160, 'master_members' => $members],
        3 => ['image' => $page('wp plugin settings current recovered'), 'source_id' => 'master-reader-current:160', 'epoch' => 160, 'dirty' => true, 'master_members' => $members],
    ],
    [1, 2, 3],
    'master-reader-current:160',
    160
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next160',
    'wordpressUse' => 'A copied WordPress options reader re-reads the current master journal and rejects stale pager-cache pages before serving active_plugins and plugin settings pages.',
    'status' => $plan['status'],
    'cacheStaleRejected' => $plan['cache_stale_rejected'],
    'retainedPages' => $plan['cache']['retained_page_numbers'],
    'invalidatedPages' => $plan['cache']['invalidated_page_numbers'],
    'readCacheHits' => $plan['read_cache_hits'],
    'dependencyClosure' => 'no new support component needed; reuses native master-journal member parsing and pager-cache current-source digest fencing',
];

if (
    $summary['status'] !== 'pager_master_journal_reader_cache_current_source_next160'
    || $summary['cacheStaleRejected'] !== true
    || $summary['retainedPages'] !== [1]
    || $summary['invalidatedPages'] !== [2, 3]
    || $summary['readCacheHits'] !== [1 => true, 2 => false, 3 => false]
) {
    fwrite(STDERR, "unexpected pager master-journal reader-cache current-source next160 summary\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
