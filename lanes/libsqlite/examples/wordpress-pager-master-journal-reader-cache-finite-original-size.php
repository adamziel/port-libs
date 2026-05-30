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
$databasePath = '/srv/wp-content/database/wp-next185.sqlite';
$masterPath = '/srv/wp-content/database/wp-next185.sqlite-mj';
$journalPath = $databasePath . '-journal';
$usersJournal = '/srv/wp-content/database/wp-next185-users.sqlite-journal';
$sourceId = 'wp-next185-current-source';
$nonce = 0x185;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$journalPages = [
    1 => $page('wp next185 recovered schema after finite journal'),
    2 => $page('wp next185 recovered active_plugins after finite journal'),
    5 => $page('wp next185 ignored deleted transient overflow tail'),
];
$header = SQLiteRollbackJournalHeader::MAGIC . pack('NNNNN', 3, $nonce, 3, 512, $pageSize);
$journalBytes = str_pad($header, 512, "\0", STR_PAD_RIGHT);
foreach ($journalPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}

$masterBytes = $journalPath . "\n" . $usersJournal . "\n";
$masterDigest = hash('sha256', $journalPath . "\n" . $usersJournal);
$journalDigest = hash('sha256', implode('|', [
    $journalPath,
    strlen($journalBytes),
    3,
    $nonce,
    3,
    512,
    $pageSize,
    '1,2,5',
    hash('sha256', $journalBytes),
]));

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planFiniteOriginalSizeTruncationFence(
    $databasePath,
    $masterPath,
    $masterBytes,
    $journalBytes,
    $page('wp next185 stale schema before finite journal') .
        $page('wp next185 stale active_plugins before finite journal') .
        $page('wp next185 unchanged autoload options page') .
        $page('wp next185 transient option page removed by rollback') .
        $page('wp next185 transient overflow page removed by rollback'),
    $pageSize,
    [
        1 => [
            'reader_id' => 'schema-cache',
            'image' => $journalPages[1],
            'source_id' => $sourceId,
            'epoch' => 17,
            'master_digest' => $masterDigest,
            'journal_digest' => $journalDigest,
            'initial_database_page_count' => 3,
            'journal_page_numbers' => [1, 2, 5],
        ],
        4 => [
            'reader_id' => 'deleted-transient-cache',
            'image' => $page('wp next185 stale transient option cache'),
            'source_id' => $sourceId,
            'epoch' => 17,
            'master_digest' => $masterDigest,
            'journal_digest' => $journalDigest,
            'initial_database_page_count' => 3,
            'journal_page_numbers' => [1, 2, 5],
        ],
    ],
    [1, 2, 4],
    [2 => $page('wp next185 rewritten active_plugins after finite recovery')],
    $sourceId,
    17,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-finite-original-size',
    'status' => $plan['status'],
    'initialDatabasePageCount' => $plan['initial_database_page_count'],
    'truncatedPages' => $plan['truncated_page_numbers'],
    'ignoredJournalPages' => $plan['ignored_journal_page_numbers'],
    'blockedReads' => $plan['blocked_read_page_numbers'],
    'retainedCachePages' => $plan['retained_page_numbers'],
    'activePluginsBeforeWrite' => $plan['next_writes'][0]['before_prefix'],
    'wordpressUse' => 'A copied WordPress database can keep a schema reader-cache page after finite master-journal recovery, but transient option tail pages removed by the rollback journal cannot be served from cache or rewritten without reopening at the truncated current source.',
    'dependencyClosure' => 'no new support component needed; this reuses rollback-journal checksum parsing and pager reader-cache current-source primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next185'
    || $summary['initialDatabasePageCount'] !== 3
    || $summary['truncatedPages'] !== [4, 5]
    || $summary['ignoredJournalPages'] !== [5]
    || $summary['blockedReads'] !== [4]
    || $summary['retainedCachePages'] !== [1]
    || $summary['activePluginsBeforeWrite'] !== 'wp next185 recovered active_plugins after finite journal'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-finite-original-size self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-finite-original-size self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
