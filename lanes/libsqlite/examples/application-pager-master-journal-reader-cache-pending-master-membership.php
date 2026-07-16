<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next181.sqlite';
$masterPath = '/srv/wp-content/database/wp-next181.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next181-meta.sqlite-journal\n";
$pendingMasterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next181-meta.sqlite-journal\n/srv/wp-content/database/wp-next181-pending.sqlite-journal\n";
$masterDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-next181-meta.sqlite-journal");
$pendingMasterDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-next181-meta.sqlite-journal\n/srv/wp-content/database/wp-next181-pending.sqlite-journal");
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
    1 => $page('wp next181 stale schema before rollback journal source'),
    2 => $page('wp next181 stale active_plugins before rollback journal source'),
    3 => $page('wp next181 stale plugin settings before rollback journal source'),
    4 => $page('wp next181 unchanged comments before rollback journal source'),
    5 => $page('wp next181 stale transients before rollback journal source'),
];
$recovered = [
    1 => $page('wp next181 recovered schema from rollback journal source'),
    2 => $page('wp next181 recovered active_plugins from rollback journal source'),
    3 => $page('wp next181 recovered plugin settings from rollback journal source'),
    5 => $page('wp next181 recovered transients from rollback journal source'),
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
$sourceId = 'wp-next181-before-rollback-source';

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planPendingMasterJournalMembershipFence(
    $databasePath,
    $masterPath,
    $masterBytes,
    $pendingMasterBytes,
    $journal,
    implode('', $before),
    $pageSize,
    [
        1 => ['label' => 'wp-schema-cache', 'image' => $recovered[1], 'source_id' => $sourceId, 'epoch' => 4, 'master_journal_digest' => $masterDigest, 'journal_source_digest' => $journalDigest, 'journal_page_count' => 4, 'journal_initial_page_count' => 5, 'journal_page_numbers' => [1, 2, 3, 5]],
        2 => ['label' => 'wp-active-plugins-pending-master-source', 'image' => $recovered[2], 'source_id' => $sourceId, 'epoch' => 4, 'master_journal_digest' => $pendingMasterDigest, 'journal_source_digest' => $journalDigest, 'journal_page_count' => 4, 'journal_initial_page_count' => 5, 'journal_page_numbers' => [1, 2, 3, 5]],
        3 => ['label' => 'wp-settings-refresh', 'image' => $before[3], 'source_id' => $sourceId, 'epoch' => 4, 'master_journal_digest' => $masterDigest, 'journal_source_digest' => $journalDigest, 'journal_page_count' => 4, 'journal_initial_page_count' => 5, 'journal_page_numbers' => [1, 2, 3, 5]],
    ],
    [1, 2, 3, 4, 5],
    [2 => $page('wp next181 rewritten active_plugins after rollback source fence')],
    $sourceId,
    4,
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-pending-master-membership',
    'status' => $plan['status'],
    'pendingMasterMembers' => $plan['pending_members'],
    'pendingMasterAdded' => $plan['pending_members_added'],
    'journalPageNumbers' => $plan['current_journal_page_numbers'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'pendingMasterReason' => $plan['reader_rows'][1]['reason'],
    'pendingMasterCacheHit' => $plan['next_reads'][1]['cache_hit'],
    'nextWriteBeforePrefix' => $plan['next_writes'][0]['before_prefix'],
    'applicationUse' => 'A copied wp_options recovery rejects reader-cache pages keyed from a pending master-journal membership before rewriting active_plugins from the current source.',
    'dependencyClosure' => 'no new support component needed; this reuses native rollback-journal parsing and pager reader-cache primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next181'
    || $summary['invalidatedCachePages'] !== [2]
    || $summary['refreshedCachePages'] !== [3]
    || $summary['pendingMasterAdded'] !== ['/srv/wp-content/database/wp-next181-pending.sqlite-journal']
    || $summary['pendingMasterReason'] !== 'reader_cache_pending_master_journal_source_rejected'
    || $summary['pendingMasterCacheHit'] !== false
    || $summary['nextWriteBeforePrefix'] !== 'wp next181 recovered active_plugins from rollback journal source'
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-pending-master-membership self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "application-pager-master-journal-reader-cache-pending-master-membership self-test passed\n";
