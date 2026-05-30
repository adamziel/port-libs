<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next171.sqlite';
$masterPath = '/srv/wp-content/database/wp-next171.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next171-site.sqlite-journal\n";
$masterDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-next171-site.sqlite-journal");
$sourceId = 'wp-next171-current-after-master-recovery-ticket';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$pages = [
    1 => $page('wp next171 schema current after master recovery ticket'),
    2 => $page('wp next171 options current after master recovery ticket'),
    3 => $page('wp next171 active_plugins current after master recovery ticket'),
    4 => $page('wp next171 rewrite_rules current after master recovery ticket'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::masterJournalRecoveryReaderCacheTicket(
    $databasePath,
    $masterPath,
    $masterBytes,
    $pageSize,
    $pages,
    [
        1 => ['image' => $pages[1], 'source_id' => $sourceId, 'epoch' => 7, 'reader_id' => 'schema-reader', 'master_generation' => 12, 'master_deleted' => true, 'master_digest' => $masterDigest, 'recovery_sequence' => 4, 'read_lock_generation' => 6, 'shared' => true],
        2 => ['image' => $page('wp next171 options stale before recovery ticket'), 'source_id' => $sourceId, 'epoch' => 7, 'reader_id' => 'options-reader', 'master_generation' => 12, 'master_deleted' => true, 'master_digest' => $masterDigest, 'recovery_sequence' => 4, 'read_lock_generation' => 6],
        3 => ['image' => $pages[3], 'source_id' => $sourceId, 'epoch' => 7, 'reader_id' => 'active-plugins-reader', 'master_generation' => 12, 'master_deleted' => true, 'master_digest' => $masterDigest, 'recovery_sequence' => 3, 'read_lock_generation' => 6],
        4 => ['image' => $pages[4], 'source_id' => $sourceId, 'epoch' => 7, 'reader_id' => 'rewrite-reader', 'master_generation' => 12, 'master_deleted' => true, 'master_digest' => str_repeat('b', 64), 'recovery_sequence' => 4, 'read_lock_generation' => 6],
    ],
    [
        ['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 7, 'master_generation' => 12, 'recovery_sequence' => 4, 'read_lock_generation' => 6],
        ['reader_id' => 'options-reader', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 7, 'master_generation' => 12, 'recovery_sequence' => 4, 'read_lock_generation' => 6],
        ['reader_id' => 'active-plugins-reader', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 7, 'master_generation' => 12, 'recovery_sequence' => 3, 'read_lock_generation' => 6],
        ['reader_id' => 'rewrite-reader', 'page_number' => 4, 'source_id' => $sourceId, 'epoch' => 7, 'master_generation' => 12, 'recovery_sequence' => 4, 'read_lock_generation' => 6],
    ],
    $sourceId,
    7,
    12,
    true,
    4,
    6,
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-current-source-next171',
    'status' => $plan['status'],
    'recoverySequence' => $plan['current_source']['recovery_sequence'],
    'readLockGeneration' => $plan['current_source']['read_lock_generation'],
    'retainedPages' => $plan['retained_page_numbers'],
    'refreshedPages' => $plan['refreshed_page_numbers'],
    'invalidatedPages' => $plan['invalidated_page_numbers'],
    'schemaCacheHit' => $plan['read_cache_hits']['schema-reader'],
    'optionsPrefix' => $plan['read_prefixes']['options-reader'],
    'activePluginsReason' => $plan['cache_rows'][2]['reason'],
    'rewriteRulesReason' => $plan['cache_rows'][3]['reason'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'applicationUse' => 'A copied wp_options database can keep a shared schema cache page and refresh a stale options page only when the reader owns the current master-journal recovery/read-lock ticket; active_plugins and rewrite_rules readers opened before recovery are forced back to the current source.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal membership and reader-cache ticket primitives',
];

if (
    $summary['status'] !== 'pager-master-journal-reader-cache-current-source-next171'
    || $summary['recoverySequence'] !== 4
    || $summary['readLockGeneration'] !== 6
    || $summary['retainedPages'] !== [1]
    || $summary['refreshedPages'] !== [2]
    || $summary['invalidatedPages'] !== [3, 4]
    || $summary['schemaCacheHit'] !== true
    || $summary['optionsPrefix'] !== 'wp next171 options current after master recovery ticket'
    || $summary['activePluginsReason'] !== 'reader_cache_recovery_sequence_mismatch'
    || $summary['rewriteRulesReason'] !== 'reader_cache_master_digest_mismatch'
    || $summary['reopenReaders'] !== ['active-plugins-reader']
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-current-source-next171 self-test failed\n");
    exit(1);
}

echo "application-pager-master-journal-reader-cache-current-source-next171 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
