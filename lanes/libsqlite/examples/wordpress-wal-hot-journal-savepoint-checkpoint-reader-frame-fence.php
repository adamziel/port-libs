<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next154.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next154 clean schema before plugin import'),
    2 => $page('wp next154 clean options before plugin import'),
    3 => $page('wp next154 clean active_plugins before plugin import'),
    4 => $page('wp next154 clean autoload before plugin import'),
];
$dirtyDatabase = $page('wp next154 dirty schema from crashed plugin import')
    . $page('wp next154 dirty options from crashed plugin import')
    . $page('wp next154 dirty active_plugins from crashed plugin import')
    . $page('wp next154 dirty autoload from crashed plugin import');
$hotDatabase = implode('', $cleanPages);

$header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), 0x2026154, count($cleanPages), $sectorSize, $pageSize);
$journalBytes = str_pad($header, $sectorSize, "\0");
foreach ($cleanPages as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, 0x2026154));
}

$salt1 = 0x15415401;
$salt2 = 0x15415402;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 154, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, 'wp next154 savepoint options draft'],
    [3, 4, 'wp next154 savepoint active_plugins commit'],
    [4, 4, 'wp next154 discarded autoload tail'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$checkpointDatabase = $page('wp next154 clean schema before plugin import')
    . $page('wp next154 savepoint options draft')
    . $page('wp next154 savepoint active_plugins commit')
    . $page('wp next154 clean autoload before plugin import');

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::savepointCheckpointReaderFrameFencePlan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $hotDatabase,
    $walBytes,
    $checkpointDatabase,
    'plugin-import',
    2,
    [1, 2, 3, 4],
    2
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next154',
    'status' => $plan['status'],
    'wordpressUse' => 'A copied WordPress plugin import recovers a hot rollback journal, rolls back the failed savepoint tail, then checkpoints only pages visible at the savepoint WAL frame boundary before resetting the current source.',
    'checkpointAllowed' => $plan['checkpoint_allowed'],
    'readerPreservedAtSavepoint' => $plan['reader_preserved_at_savepoint'],
    'tailDiscardedPages' => $plan['tail_discarded_page_numbers'],
    'checkpointLabels' => $plan['checkpoint_labels'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next154'
    || $summary['checkpointAllowed'] !== true
    || $summary['readerPreservedAtSavepoint'] !== true
    || $summary['tailDiscardedPages'] !== [4]
) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next154 self-test failed\n");
    exit(1);
}

echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next154 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
