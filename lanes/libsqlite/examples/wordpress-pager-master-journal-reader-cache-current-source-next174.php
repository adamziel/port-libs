<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next174.sqlite';
$masterPath = '/srv/wp-content/database/wp-next174.sqlite-mj';
$mainJournal = $databasePath . '-journal';
$networkJournal = '/srv/wp-content/database/wp-next174-network.sqlite-journal';
$sourceId = 'wp-next174-current-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$journalPages = [
    1 => $page('wp next174 recovered schema from rollback journal'),
    2 => $page('wp next174 recovered active_plugins from rollback journal'),
];
$header = SQLiteRollbackJournalHeader::MAGIC . pack('NNNNN', count($journalPages), 0x174, 3, 512, $pageSize);
$journalBytes = str_pad($header, 512, "\0", STR_PAD_RIGHT);
foreach ($journalPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', 0);
}

$canonicalMembers = [$networkJournal, $mainJournal];
sort($canonicalMembers, SORT_STRING);
$masterDigest = hash('sha256', implode("\n", $canonicalMembers));
$journalDigest = hash('sha256', implode('|', [
    $mainJournal,
    strlen($journalBytes),
    count($journalPages),
    0x174,
    3,
    512,
    $pageSize,
    '1,2',
    hash('sha256', $journalBytes),
]));

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext174(
    $databasePath,
    $masterPath,
    $networkJournal . "\n" . $mainJournal . "\n",
    $journalBytes,
    $page('wp next174 stale schema before recovery') . $page('wp next174 stale active_plugins before recovery') . $page('wp next174 unchanged options page'),
    $pageSize,
    [
        1 => [
            'label' => 'wp-schema-cache',
            'image' => $journalPages[1],
            'source_id' => $sourceId,
            'epoch' => 7,
            'master_journal_digest' => $masterDigest,
            'journal_source_digest' => $journalDigest,
            'journal_page_count' => 2,
            'journal_initial_page_count' => 3,
            'journal_page_numbers' => [1, 2],
        ],
    ],
    [1, 2],
    [2 => $page('wp next174 rewritten active_plugins after canonical master cache')],
    $sourceId,
    7,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next174',
    'status' => $plan['status'],
    'canonicalMembers' => $plan['canonical_members'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'firstReadCacheHit' => $plan['next_reads'][0]['cache_hit'],
    'secondWriteBefore' => $plan['next_writes'][0]['before_prefix'],
    'wordpressUse' => 'A copied WordPress database can reuse a schema reader-cache page when the same attached rollback journals appear in a different master-journal order, while the next active_plugins write still captures the recovered rollback source.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local rollback-journal and pager reader-cache primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next174'
    || $summary['retainedCachePages'] !== [1]
    || $summary['firstReadCacheHit'] !== true
    || $summary['secondWriteBefore'] !== 'wp next174 recovered active_plugins from rollback journal'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next174 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next174 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
