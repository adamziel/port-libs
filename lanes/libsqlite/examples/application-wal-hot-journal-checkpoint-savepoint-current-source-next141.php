<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next141.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('wp next141 clean sqlite header'),
    2 => $page('wp next141 clean wp_options root'),
    3 => $page('wp next141 clean autoload index'),
];
$dirtyDatabase = $page('wp next141 dirty sqlite header')
    . $page('wp next141 dirty wp_options root')
    . $page('wp next141 dirty autoload index');

$nonce = 0x14114101;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
}

$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$currentWalBytes = $makeWalBytes([
    [1, 0, 'wp next141 retained schema draft'],
    [2, 3, 'wp next141 retained wp_options commit'],
    [3, 3, 'wp next141 discarded autoload savepoint'],
], 141, 0x14114101, 0x14114102);
$nextWalBytes = $makeWalBytes([
    [1, 0, 'wp next141 retained schema draft'],
    [2, 3, 'wp next141 next writer wp_options commit'],
    [3, 3, 'wp next141 next writer autoload commit'],
], 142, 0x14214201, 0x14214202);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next141');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('autoload-batch-next141');
$stack->recordWalFrameWrite(3, 3, true);

$plan = SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan::plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $stack,
    'autoload-batch-next141',
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    $nextWalBytes,
    [1, 2, 3]
);

$summary = [
    'scenario' => 'application-wal-hot-journal-checkpoint-savepoint-current-source-next141',
    'applicationUse' => 'A copied wp_options import recovers a hot rollback journal, rolls back an inner savepoint, keeps the current reader pinned through checkpoint, and opens the next writer on a separate WAL source.',
    'status' => $plan['status'],
    'checkpointBusy' => $plan['checkpoint_busy'],
    'nextSourceSeparated' => $plan['next_source_separated'],
    'currentSources' => $plan['current_sources'],
    'checkpointSources' => $plan['checkpoint_sources'],
    'nextSources' => $plan['next_sources'],
    'nextChangedPageNumbers' => $plan['next_changed_page_numbers'],
    'operationReasons' => $plan['operation_reasons'],
    'dependencyClosure' => 'no new support component needed; this reuses native rollback-journal recovery, WAL transaction recovery, savepoint prefix truncation, and WAL reader snapshot logic',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($summary['status'] === 'wal-hot-journal-checkpoint-savepoint-current-source-next141');
    assert($summary['checkpointBusy'] === true);
    assert($summary['nextSourceSeparated'] === true);
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
