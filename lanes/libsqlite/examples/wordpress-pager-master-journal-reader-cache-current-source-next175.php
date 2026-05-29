<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next175.sqlite';
$masterPath = '/srv/wp-content/database/wp-next175.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next175-users.sqlite-journal\n";
$masterDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-next175-users.sqlite-journal");
$sourceId = 'wp-next175-current-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$journalBytes = static function (array $pages, array $badChecksums = []) use ($pageSize): string {
    $nonce = 0x175;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('NNNNN', count($pages), $nonce, 5, 512, $pageSize);
    $bytes = str_pad($header, 512, "\0", STR_PAD_RIGHT);
    foreach ($pages as $pageNumber => $image) {
        $checksum = SQLiteRollbackJournal::pageChecksum($image, $nonce);
        if (in_array($pageNumber, $badChecksums, true)) {
            $checksum = ($checksum + 9) & 0xffffffff;
        }
        $bytes .= pack('N', $pageNumber) . $image . pack('N', $checksum);
    }
    return $bytes;
};

$before = [
    1 => $page('wp next175 stale schema before checksum recovery'),
    2 => $page('wp next175 stale active_plugins before checksum recovery'),
    3 => $page('wp next175 stale plugin settings before checksum recovery'),
    4 => $page('wp next175 unchanged comments before checksum recovery'),
    5 => $page('wp next175 stale transients before checksum recovery'),
];
$recovered = [
    1 => $page('wp next175 recovered schema checksum valid'),
    2 => $page('wp next175 recovered active_plugins checksum valid'),
    3 => $page('wp next175 recovered plugin settings checksum corrupt'),
    5 => $page('wp next175 recovered transients checksum valid'),
];
$journal = $journalBytes($recovered, [3]);
$journalDigest = hash('sha256', $databasePath . '-journal|' . strlen($journal) . '|' . hash('sha256', $journal));

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext175(
    $databasePath,
    $masterPath,
    $masterBytes,
    $journal,
    implode('', $before),
    $pageSize,
    [
        1 => ['reader_id' => 'schema-reader', 'image' => $recovered[1], 'source_id' => $sourceId, 'epoch' => 6, 'master_digest' => $masterDigest, 'journal_digest' => $journalDigest],
        2 => ['reader_id' => 'active-reader', 'image' => $before[2], 'source_id' => $sourceId, 'epoch' => 6, 'master_digest' => $masterDigest, 'journal_digest' => $journalDigest],
        3 => ['reader_id' => 'settings-reader', 'image' => $before[3], 'source_id' => $sourceId, 'epoch' => 6, 'master_digest' => $masterDigest, 'journal_digest' => $journalDigest],
    ],
    [1, 2, 3, 4, 5],
    [
        2 => $page('wp next175 rewritten active_plugins after checksum fence'),
        3 => $page('wp next175 blocked plugin settings after checksum fence'),
    ],
    $sourceId,
    6,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next175',
    'status' => $plan['status'],
    'validJournalPages' => $plan['valid_journal_page_numbers'],
    'corruptJournalPages' => $plan['corrupt_journal_page_numbers'],
    'retainedCachePages' => $plan['retained_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_page_numbers'],
    'settingsReason' => $plan['cache_rows'][2]['reason'],
    'settingsReadQuarantined' => $plan['next_reads'][2]['quarantined'],
    'activeWriteAllowed' => $plan['next_writes'][0]['allowed'],
    'settingsWriteAllowed' => $plan['next_writes'][1]['allowed'],
    'wordpressUse' => 'A copied wp_options recovery reuses only checksum-verified reader cache pages before rewriting active_plugins; plugin settings waits for a verified before-image.',
    'dependencyClosure' => 'no new support component needed; this reuses native rollback-journal checksum parsing and pager reader-cache primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next175'
    || $summary['corruptJournalPages'] !== [3]
    || $summary['retainedCachePages'] !== [1]
    || $summary['refreshedCachePages'] !== [2]
    || $summary['invalidatedCachePages'] !== [3]
    || $summary['settingsReason'] !== 'reader_cache_page_quarantined_by_rollback_journal_checksum'
    || $summary['settingsReadQuarantined'] !== true
    || $summary['activeWriteAllowed'] !== true
    || $summary['settingsWriteAllowed'] !== false
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next175 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next175 self-test passed\n";
