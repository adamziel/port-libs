<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next170.sqlite';
$masterPath = '/srv/wp-content/database/wp-next170.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next170-meta.sqlite-journal\n";
$masterDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-next170-meta.sqlite-journal");
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$journalBytes = static function (array $pages) use ($pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('NNNNN', count($pages), 0x170, 5, 512, $pageSize);
    $bytes = str_pad($header, 512, "\0", STR_PAD_RIGHT);
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', 0);
    }
    return $bytes;
};

$before = [
    1 => $page('wp next170 stale schema before rollback journal source'),
    2 => $page('wp next170 stale active_plugins before rollback journal source'),
    3 => $page('wp next170 stale plugin settings before rollback journal source'),
    4 => $page('wp next170 unchanged comments before rollback journal source'),
    5 => $page('wp next170 stale transients before rollback journal source'),
];
$recovered = [
    1 => $page('wp next170 recovered schema from rollback journal source'),
    2 => $page('wp next170 recovered active_plugins from rollback journal source'),
    3 => $page('wp next170 recovered plugin settings from rollback journal source'),
    5 => $page('wp next170 recovered transients from rollback journal source'),
];
$journal = $journalBytes($recovered);
$journalDigest = hash('sha256', implode('|', [
    $databasePath . '-journal',
    strlen($journal),
    4,
    0x170,
    5,
    512,
    $pageSize,
    '1,2,3,5',
    hash('sha256', $journal),
]));
$sourceId = 'wp-next170-before-rollback-source';

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext170(
    $databasePath,
    $masterPath,
    $masterBytes,
    $journal,
    implode('', $before),
    $pageSize,
    [
        1 => ['label' => 'wp-schema-cache', 'image' => $recovered[1], 'source_id' => $sourceId, 'epoch' => 4, 'master_journal_digest' => $masterDigest, 'journal_source_digest' => $journalDigest, 'journal_page_count' => 4, 'journal_initial_page_count' => 5, 'journal_page_numbers' => [1, 2, 3, 5]],
        2 => ['label' => 'wp-active-plugins-stale-source', 'image' => $recovered[2], 'source_id' => $sourceId, 'epoch' => 4, 'master_journal_digest' => $masterDigest, 'journal_source_digest' => hash('sha256', 'old-journal'), 'journal_page_count' => 4, 'journal_initial_page_count' => 5, 'journal_page_numbers' => [1, 2, 3, 5]],
        3 => ['label' => 'wp-settings-refresh', 'image' => $before[3], 'source_id' => $sourceId, 'epoch' => 4, 'master_journal_digest' => $masterDigest, 'journal_source_digest' => $journalDigest, 'journal_page_count' => 4, 'journal_initial_page_count' => 5, 'journal_page_numbers' => [1, 2, 3, 5]],
    ],
    [1, 2, 3, 4, 5],
    [2 => $page('wp next170 rewritten active_plugins after rollback source fence')],
    $sourceId,
    4,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next170',
    'status' => $plan['status'],
    'journalPageNumbers' => $plan['current_journal_page_numbers'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'staleJournalReason' => $plan['reader_rows'][1]['reason'],
    'staleJournalCacheHit' => $plan['next_reads'][1]['cache_hit'],
    'nextWriteBeforePrefix' => $plan['next_writes'][0]['before_prefix'],
    'wordpressUse' => 'A copied wp_options recovery rejects reader-cache pages keyed from an older rollback-journal source before rewriting active_plugins.',
    'dependencyClosure' => 'no new support component needed; this reuses native rollback-journal parsing and pager reader-cache primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next170'
    || $summary['invalidatedCachePages'] !== [2]
    || $summary['refreshedCachePages'] !== [3]
    || $summary['staleJournalReason'] !== 'reader_cache_rollback_journal_source_digest_mismatch'
    || $summary['staleJournalCacheHit'] !== false
    || $summary['nextWriteBeforePrefix'] !== 'wp next170 recovered active_plugins from rollback journal source'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next170 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next170 self-test passed\n";
