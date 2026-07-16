<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next182.sqlite';
$masterPath = '/srv/wp-content/database/wp-next182.sqlite-mj';
$mainJournal = $databasePath . '-journal';
$usersJournal = '/srv/wp-content/database/wp-next182-users.sqlite-journal';
$sourceId = 'wp-next182-current-source';
$nonce = 0x182;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$journalPages = [
    1 => $page('wp next182 recovered schema from checksum journal'),
    2 => $page('wp next182 recovered active_plugins from checksum journal'),
];
$header = SQLiteRollbackJournalHeader::MAGIC . pack('NNNNN', SQLiteRollbackJournalHeader::UNKNOWN_PAGE_COUNT, $nonce, 3, 512, $pageSize);
$journalBytes = str_pad($header, 512, "\0", STR_PAD_RIGHT);
foreach ($journalPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}

$masterBytes = $mainJournal . "\n" . $usersJournal . "\n";
$masterDigest = hash('sha256', $mainJournal . "\n" . $usersJournal);
$journalDigest = hash('sha256', implode('|', [
    $mainJournal,
    strlen($journalBytes),
    SQLiteRollbackJournalHeader::UNKNOWN_PAGE_COUNT,
    $nonce,
    3,
    512,
    $pageSize,
    '1,2',
    hash('sha256', $journalBytes),
]));

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planChecksumValidatedUnknownCountFence(
    $databasePath,
    $masterPath,
    $masterBytes,
    $journalBytes,
    $page('wp next182 stale schema before checksum recovery') .
        $page('wp next182 stale active_plugins before checksum recovery') .
        $page('wp next182 unchanged autoload options page'),
    $pageSize,
    [
        1 => [
            'reader_id' => 'schema-cache',
            'image' => $journalPages[1],
            'source_id' => $sourceId,
            'epoch' => 11,
            'master_digest' => $masterDigest,
            'journal_digest' => $journalDigest,
            'checksum_nonce' => $nonce,
            'journal_record_count' => 2,
            'journal_page_numbers' => [1, 2],
        ],
        2 => [
            'reader_id' => 'active-plugins-cache',
            'image' => $page('wp next182 stale active_plugins cache'),
            'source_id' => $sourceId,
            'epoch' => 11,
            'master_digest' => $masterDigest,
            'journal_digest' => hash('sha256', 'stale-journal-bytes'),
            'checksum_nonce' => $nonce,
            'journal_record_count' => 2,
            'journal_page_numbers' => [1, 2],
        ],
    ],
    [1, 2],
    [2 => $page('wp next182 rewritten active_plugins after checksum recovery')],
    $sourceId,
    11,
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-checksum-unknown-count',
    'status' => $plan['status'],
    'unknownPageCount' => $plan['unknown_page_count'],
    'checksumNonce' => $plan['checksum_nonce'],
    'retainedCachePages' => $plan['retained_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_page_numbers'],
    'activePluginsBeforeWrite' => $plan['next_writes'][0]['before_prefix'],
    'applicationUse' => 'A copied Application database can keep a schema reader-cache page only after the current master journal points at a checksum-valid rollback journal scanned to EOF; stale active_plugins cache rows reopen before the next import write.',
    'dependencyClosure' => 'no new support component needed; this reuses rollback-journal checksum validation and pager reader-cache primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next182'
    || $summary['unknownPageCount'] !== true
    || $summary['retainedCachePages'] !== [1]
    || $summary['invalidatedCachePages'] !== [2]
    || $summary['activePluginsBeforeWrite'] !== 'wp next182 recovered active_plugins from checksum journal'
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-checksum-unknown-count self-test failed\n");
    exit(1);
}

echo "application-pager-master-journal-reader-cache-checksum-unknown-count self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
