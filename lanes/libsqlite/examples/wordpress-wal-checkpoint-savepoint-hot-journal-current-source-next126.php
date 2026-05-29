<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$clean = [
    1 => $page('wp clean schema before crashed plugin import'),
    2 => $page('wp clean option rows before crashed plugin import'),
    3 => $page('wp clean autoload index before crashed plugin import'),
    4 => $page('wp clean transient rows before crashed plugin import'),
];
$dirty = [
    1 => $page('wp dirty schema from hot journal crash'),
    2 => $page('wp dirty option rows from hot journal crash'),
    3 => $page('wp dirty autoload index from hot journal crash'),
    4 => $page('wp dirty transient rows from hot journal crash'),
];
$databaseBytes = implode('', $dirty);
$nonce = 0x12600001;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($clean), $nonce, 4, $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($clean as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}

$salt1 = 0x12612601;
$salt2 = 0x12612602;
$walPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 126, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($walPrefix, false);
$walBytes = $walPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 1, 0, $page('wp retained schema version after hot recovery'));
$walBytes = $appendFrame($walBytes, $seed, 2, 4, $page('wp retained siteurl commit after hot recovery'));
$walBytes = $appendFrame($walBytes, $seed, 3, 0, $page('wp discarded plugin autoload draft in savepoint'));
$walBytes = $appendFrame($walBytes, $seed, 4, 4, $page('wp discarded transient cleanup commit in savepoint'));
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-batch');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4, true);

$plan = SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan::plan(
    $stack,
    'plugin-batch',
    $databasePath,
    $databaseBytes,
    $journalBytes,
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    'restart',
    1
);

echo json_encode([
    'scenario' => 'wordpress-wal-checkpoint-savepoint-hot-journal-current-source-next126',
    'wordpressUse' => 'Recover a copied wp_options database from a hot rollback journal, discard WAL frames inside a failed plugin import savepoint, and checkpoint only the retained WAL prefix without requiring ext/sqlite.',
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'savepoint' => $plan['savepoint'],
    'retainedFrameCount' => $plan['retained_frame_count'],
    'discardedFrameCount' => $plan['discarded_frame_count'],
    'checkpointBusy' => $plan['checkpoint_busy'],
    'checkpointWalAction' => $plan['checkpoint_wal_action'],
    'releasedWalAction' => $plan['released_wal_action'],
    'hotRestoredPages' => $plan['hot_restored_page_numbers'],
    'sourceTransitions' => $plan['source_transitions'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
