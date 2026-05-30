<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next190.sqlite';
$masterPath = '/srv/wp-content/database/wp-next190.sqlite-mj';
$mainJournal = $databasePath . '-journal';
$metaJournal = '/srv/wp-content/database/wp-next190-meta.sqlite-journal';
$masterBytes = $mainJournal . "\n" . $metaJournal . "\n";
$members = [$mainJournal, $metaJournal];
$ordinals = [$mainJournal => 1, $metaJournal => 2];
$completeDigest = hash('sha256', $masterPath . '|' . strlen($masterBytes) . '|' . implode("\n", $members) . '|' . hash('sha256', $masterBytes));
$sourceId = 'wp-next190-current-page-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (int $pageNumber, string $image, string $source): string => hash('sha256', $pageNumber . '|' . $source . '|' . hash('sha256', $image));
$before = [
    1 => $page('wp next190 schema before master recovery'),
    2 => $page('wp next190 active_plugins byte-identical before source'),
    3 => $page('wp next190 plugin settings before source'),
];
$current = [
    2 => $before[2],
    3 => $page('wp next190 plugin settings after master recovery'),
];
$sources = [
    2 => 'master-journal-member:' . $mainJournal,
    3 => 'master-journal-member:' . $metaJournal,
];
$beforeSource = 'database-image-before-master-journal-recovery-next190';
$currentDigest = static fn (int $pageNumber): string => $digest($pageNumber, $current[$pageNumber] ?? $before[$pageNumber], $sources[$pageNumber] ?? $beforeSource);

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planPageSourceDigestFence(
    $databasePath,
    $masterPath,
    $masterBytes,
    implode('', $before),
    $pageSize,
    [
        1 => ['label' => 'wp-schema-retained', 'image' => $before[1], 'source_id' => $sourceId, 'epoch' => 190, 'master_member_ordinals' => $ordinals, 'master_complete_read_digest' => $completeDigest, 'master_byte_length' => strlen($masterBytes), 'page_source_digest' => $currentDigest(1)],
        2 => ['label' => 'wp-active-plugins-byte-identical-source-change', 'image' => $before[2], 'source_id' => $sourceId, 'epoch' => 190, 'master_member_ordinals' => $ordinals, 'master_complete_read_digest' => $completeDigest, 'master_byte_length' => strlen($masterBytes), 'page_source_digest' => $digest(2, $before[2], $beforeSource)],
        3 => ['label' => 'wp-settings-refresh', 'image' => $before[3], 'source_id' => $sourceId, 'epoch' => 190, 'master_member_ordinals' => $ordinals, 'master_complete_read_digest' => $completeDigest, 'master_byte_length' => strlen($masterBytes), 'page_source_digest' => $currentDigest(3)],
    ],
    [
        ['reader_id' => 'schema-reader', 'page_number' => 1, 'page_source_digest' => $currentDigest(1)],
        ['reader_id' => 'active-reader', 'page_number' => 2, 'page_source_digest' => $digest(2, $before[2], $beforeSource)],
        ['reader_id' => 'settings-reader', 'page_number' => 3, 'page_source_digest' => $currentDigest(3)],
    ],
    $current,
    $sources,
    $sourceId,
    190,
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-page-source-digest',
    'status' => $plan['status'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'activePluginsReason' => $plan['reader_rows'][1]['source_reason'],
    'activePluginsCacheHit' => $plan['read_cache_hits']['active-reader'],
    'settingsPrefix' => $plan['next_reads'][2]['prefix'],
    'applicationUse' => 'After a copied wp_options recovery, a byte-identical active_plugins page is still reopened when its reader-cache ticket came from the pre-master source rather than the current master-journal member source.',
    'dependencyClosure' => 'no new support component needed; this composes lane-local complete master-journal reader-cache fencing with per-page source digests',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next190'
    || $summary['retainedCachePages'] !== [1]
    || $summary['refreshedCachePages'] !== [3]
    || $summary['invalidatedCachePages'] !== [2]
    || $summary['activePluginsReason'] !== 'reader_cache_page_source_digest_predates_current_source'
    || $summary['activePluginsCacheHit'] !== false
    || $summary['settingsPrefix'] !== 'wp next190 plugin settings after master recovery'
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-page-source-digest self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "application-pager-master-journal-reader-cache-page-source-digest self-test passed\n";
