<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next183.sqlite';
$master = '/srv/wp-content/database/wp-next183.sqlite-mj';
$sourceId = 'wp-next183-publication-source';
$masterBytes = $database . "-journal\n/srv/wp-content/database/wp-next183-users.sqlite-journal\n";
$publication = 183;
$masterDigest = hash('sha256', 'wp-next183-current-master-source');
$oldDigest = hash('sha256', 'wp-next183-old-master-source');
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 13, 0x57504f50]));
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 13), 60, 4);
    $page = substr_replace($page, pack('N', 0x57504f50), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};

$before = [
    1 => $formatPage('wp next183 stale header before master recovery'),
    2 => $page('wp next183 stale options page before master recovery'),
    3 => $page('wp next183 stale active plugins before master recovery'),
];
$recovered = [
    1 => $formatPage('wp next183 current header after master recovery'),
    2 => $page('wp next183 recovered options page after master recovery'),
    3 => $page('wp next183 recovered active plugins after master recovery'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext183(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => ['label' => 'wp-schema-publication-retained', 'image' => $recovered[1], 'source_id' => $sourceId, 'epoch' => 183, 'reader_id' => 'schema-reader', 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest],
        2 => ['label' => 'wp-options-publication-refresh', 'image' => $before[2], 'source_id' => $sourceId, 'epoch' => 183, 'reader_id' => 'options-reader', 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest],
        3 => ['label' => 'wp-active-plugins-old-publication', 'image' => $recovered[3], 'source_id' => $sourceId, 'epoch' => 183, 'reader_id' => 'active-reader', 'format_signature' => $formatSignature, 'publication_generation' => 182, 'master_source_digest' => $oldDigest],
    ],
    [
        ['reader_id' => 'schema-read', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 183, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest],
        ['reader_id' => 'options-read', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 183, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest],
        ['reader_id' => 'active-read', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 183, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest],
    ],
    $sourceId,
    183,
    $publication,
    $masterDigest,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next183',
    'status' => $plan['status'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'publicationInvalidatedPages' => $plan['publication_invalidated_cache_page_numbers'],
    'optionsReadPrefix' => $plan['read_prefixes']['options-read'],
    'activePluginsCacheHit' => $plan['read_cache_hits']['active-read'],
    'wordpressUse' => 'A copied wp_options database prevents an old reader-cache publication from being reused after a newer master-journal recovery source is opened.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local master-journal reader-cache current-source primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next183'
    || $summary['retainedCachePages'] !== [1]
    || $summary['refreshedCachePages'] !== [2]
    || $summary['publicationInvalidatedPages'] !== [3]
    || $summary['optionsReadPrefix'] !== 'wp next183 recovered options page after master recovery'
    || $summary['activePluginsCacheHit'] !== false
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next183 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next183 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
