<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 128;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = '/srv/wp-content/database/wp-options-next166.sqlite';
$master = '/srv/wp-content/database/wp-options-next166.sqlite-mj';
$masterBytes = $database . "-journal\n/srv/wp-content/database/wp-comments-next166.sqlite-journal\n";
$masterDigest = hash('sha256', $masterBytes);

$currentPages = [
    1 => $page('wp next166 schema current after master recovery'),
    2 => $page('wp next166 active_plugins current after master recovery'),
    3 => $page('wp next166 autoload current after master recovery'),
    4 => $page('wp next166 stale overflow before truncate'),
];
$nextPages = [
    1 => $currentPages[1],
    2 => $page('wp next166 active_plugins next committed'),
    3 => $currentPages[3],
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planReaderCacheNextSourceFence(
    $database,
    $master,
    $masterBytes,
    $pageSize,
    $currentPages,
    $nextPages,
    [
        1 => ['image' => $currentPages[1], 'source_id' => 'wp-current-next166', 'epoch' => 8, 'generation' => 22, 'schema_cookie' => 701, 'page_count' => 4, 'master_digest' => $masterDigest],
        2 => ['image' => $currentPages[2], 'source_id' => 'wp-current-next166', 'epoch' => 8, 'generation' => 22, 'schema_cookie' => 701, 'page_count' => 4, 'master_digest' => $masterDigest],
        3 => ['image' => $currentPages[3], 'source_id' => 'wp-current-next166', 'epoch' => 8, 'generation' => 22, 'schema_cookie' => 701, 'page_count' => 4, 'pinned' => true, 'master_digest' => $masterDigest],
        4 => ['image' => $currentPages[4], 'source_id' => 'wp-current-next166', 'epoch' => 8, 'generation' => 22, 'schema_cookie' => 701, 'page_count' => 4, 'master_digest' => $masterDigest],
    ],
    [
        ['reader_id' => 'schema-reader', 'page_number' => 1],
        ['reader_id' => 'active-plugins-reader', 'page_number' => 2],
        ['reader_id' => 'autoload-reader', 'page_number' => 3],
    ],
    'wp-current-next166',
    'wp-next-next166',
    8,
    9,
    22,
    23,
    701,
    702,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next166',
    'status' => $plan['status'],
    'schemaChanged' => $plan['schema_changed'],
    'pageCountTruncated' => $plan['page_count_truncated'],
    'reusablePages' => $plan['reusable_page_numbers'],
    'invalidatedPages' => $plan['invalidated_page_numbers'],
    'activePluginsCacheHit' => $plan['read_cache_hits']['active-plugins-reader'],
    'autoloadCacheHit' => $plan['read_cache_hits']['autoload-reader'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'wordpressUse' => 'A copied wp_options reader can keep unchanged schema/autoload pages after master-journal recovery, while active_plugins and truncated overflow pages are reopened against the next source generation before plugin import continues.',
    'dependencyClosure' => 'no new support component needed; this composes existing lane-local master-journal reader-cache current-source primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next166'
    || $summary['schemaChanged'] !== true
    || $summary['pageCountTruncated'] !== true
    || $summary['reusablePages'] !== [1, 3]
    || $summary['invalidatedPages'] !== [2, 4]
    || $summary['activePluginsCacheHit'] !== false
    || $summary['autoloadCacheHit'] !== true
    || $summary['reopenReaders'] !== ['active-plugins-reader']
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next166 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next166 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
