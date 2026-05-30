<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next194.sqlite';
$masterPath = '/srv/wp-content/database/wp-next194.sqlite-mj';
$mainJournal = $databasePath . '-journal';
$metaJournal = '/srv/wp-content/database/wp-next194-meta.sqlite-journal';
$masterBytes = $mainJournal . "\n" . $metaJournal . "\n";
$members = [$mainJournal, $metaJournal];
$ordinals = [$mainJournal => 1, $metaJournal => 2];
$completeDigest = hash('sha256', $masterPath . '|' . strlen($masterBytes) . '|' . implode("\n", $members) . '|' . hash('sha256', $masterBytes));
$sourceId = 'wp-next194-current-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (int $pageNumber, string $image, string $source): string => hash('sha256', $pageNumber . '|' . $source . '|' . hash('sha256', $image));
$snapshot = static function (string $group, array $pageDigests): string {
    ksort($pageDigests, SORT_NUMERIC);
    $parts = [];
    foreach ($pageDigests as $pageNumber => $pageDigest) {
        $parts[] = $pageNumber . ':' . $pageDigest;
    }

    return hash('sha256', $group . '|' . implode('|', $parts));
};

$before = [
    1 => $page('wp next194 schema before master reader snapshot'),
    2 => $page('wp next194 active_plugins before master reader snapshot'),
    3 => $page('wp next194 plugin settings before master reader snapshot'),
];
$current = [
    2 => $page('wp next194 active_plugins after master recovery'),
    3 => $before[3],
];
$sources = [
    2 => 'master-journal-member:' . $mainJournal,
    3 => 'master-journal-member:' . $metaJournal,
];
$beforeSource = 'database-image-before-master-journal-recovery-next190';
$currentDigests = [
    1 => $digest(1, $before[1], $beforeSource),
    2 => $digest(2, $current[2], $sources[2]),
    3 => $digest(3, $current[3], $sources[3]),
];
$oldDigests = [
    2 => $digest(2, $before[2], $beforeSource),
    3 => $digest(3, $before[3], $beforeSource),
];
$currentSnapshot = $snapshot('wp-options-reader', [2 => $currentDigests[2], 3 => $currentDigests[3]]);
$oldSnapshot = $snapshot('wp-options-reader', [2 => $oldDigests[2], 3 => $oldDigests[3]]);

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::transactionSnapshotDigestFence(
    $databasePath,
    $masterPath,
    $masterBytes,
    implode('', $before),
    $pageSize,
    [
        1 => ['label' => 'wp-schema-retained', 'image' => $before[1], 'source_id' => $sourceId, 'epoch' => 194, 'master_member_ordinals' => $ordinals, 'master_complete_read_digest' => $completeDigest, 'master_byte_length' => strlen($masterBytes), 'page_source_digest' => $currentDigests[1], 'reader_transaction_id' => 'wp-schema-reader', 'reader_snapshot_digest' => $snapshot('wp-schema-reader', [1 => $currentDigests[1]])],
        2 => ['label' => 'wp-active-plugins-refresh-stale-transaction', 'image' => $before[2], 'source_id' => $sourceId, 'epoch' => 194, 'master_member_ordinals' => $ordinals, 'master_complete_read_digest' => $completeDigest, 'master_byte_length' => strlen($masterBytes), 'page_source_digest' => $currentDigests[2], 'reader_transaction_id' => 'wp-options-reader', 'reader_snapshot_digest' => $oldSnapshot],
        3 => ['label' => 'wp-plugin-settings-byte-identical-stale-transaction', 'image' => $before[3], 'source_id' => $sourceId, 'epoch' => 194, 'master_member_ordinals' => $ordinals, 'master_complete_read_digest' => $completeDigest, 'master_byte_length' => strlen($masterBytes), 'page_source_digest' => $currentDigests[3], 'reader_transaction_id' => 'wp-options-reader', 'reader_snapshot_digest' => $oldSnapshot],
    ],
    [
        ['reader_id' => 'schema-reader', 'reader_transaction_id' => 'wp-schema-reader', 'page_number' => 1, 'page_source_digest' => $currentDigests[1], 'reader_snapshot_digest' => $snapshot('wp-schema-reader', [1 => $currentDigests[1]])],
        ['reader_id' => 'active-reader', 'reader_transaction_id' => 'wp-options-reader', 'page_number' => 2, 'page_source_digest' => $currentDigests[2], 'reader_snapshot_digest' => $currentSnapshot],
        ['reader_id' => 'settings-reader', 'reader_transaction_id' => 'wp-options-reader', 'page_number' => 3, 'page_source_digest' => $currentDigests[3], 'reader_snapshot_digest' => $currentSnapshot],
    ],
    $current,
    $sources,
    $sourceId,
    194,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-transaction-snapshot-digest-fence',
    'status' => $plan['status'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'transactionInvalidatedPages' => $plan['transaction_snapshot_invalidated_cache_page_numbers'],
    'activeCacheHit' => $plan['read_cache_hits']['active-reader'],
    'settingsCacheHit' => $plan['read_cache_hits']['settings-reader'],
    'settingsReason' => $plan['next_reads'][2]['snapshot_reason'],
    'wordpressUse' => 'A copied WordPress options reader that spans active_plugins and plugin settings reopens the whole reader transaction after master-journal recovery, instead of mixing one refreshed page with a byte-identical stale cache ticket.',
    'dependencyClosure' => 'no new support component needed; this composes lane-local master-journal complete-read and per-page current-source fences with transaction-wide reader snapshot admission',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next194'
    || $summary['retainedCachePages'] !== [1]
    || $summary['invalidatedCachePages'] !== [2, 3]
    || $summary['transactionInvalidatedPages'] !== [2, 3]
    || $summary['activeCacheHit'] !== false
    || $summary['settingsCacheHit'] !== false
    || $summary['settingsReason'] !== 'reader_transaction_reopened_after_snapshot_source_change'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-transaction-snapshot-digest-fence self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-transaction-snapshot-digest-fence self-test passed\n";
