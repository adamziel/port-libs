<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next155.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('wp next155 clean sqlite header'),
    2 => $page('wp next155 clean wp_options root'),
    3 => $page('wp next155 clean autoload index'),
    4 => $page('wp next155 clean rewrite rules'),
];
$dirtyDatabase = $page('wp next155 dirty sqlite header')
    . $page('wp next155 dirty wp_options root')
    . $page('wp next155 dirty autoload index')
    . $page('wp next155 dirty rewrite rules');
$hotDatabase = implode('', $cleanPages);

$nonce = 0x2026155;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
}

$makeWalBytes = static function (array $frames) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 155, 0x15515501, 0x15515502);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commit, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commit, 0x15515501, 0x15515502);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWalBytes([
    [1, 0, 'wp next155 schema draft before savepoint'],
    [2, 4, 'wp next155 wp_options commit before savepoint'],
    [3, 0, 'wp next155 rolled back autoload savepoint frame'],
    [4, 4, 'wp next155 rolled back rewrite savepoint frame'],
]);
$checkpointDatabase = $page('wp next155 schema draft before savepoint')
    . $page('wp next155 wp_options commit before savepoint')
    . $page('wp next155 clean autoload index')
    . $page('wp next155 clean rewrite rules');

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointDatabaseVisibilityPlan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $hotDatabase,
    $walBytes,
    $checkpointDatabase,
    [1, 2, 3, 4],
    2,
    4
);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next155',
    'applicationUse' => 'A copied Application SQLite database recovers a hot rollback journal, rolls a plugin-import savepoint back to an earlier WAL prefix, checkpoints only the retained current-source pages, and requires stale readers to reopen before seeing the checkpoint.',
    'status' => $plan['status'],
    'checkpointAllowed' => $plan['checkpoint_allowed'],
    'rollbackFrame' => $plan['savepoint_rollback_frame'],
    'readerPostRollbackPages' => $plan['reader_post_rollback_page_numbers'],
    'checkpointLabels' => $plan['checkpoint_labels'],
    'operationReasons' => $plan['operation_reasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($argv[1] ?? '') === '--self-test') {
        assert($summary['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next155');
        assert($summary['checkpointAllowed'] === true);
        assert($summary['readerPostRollbackPages'] === [3, 4]);
        echo 'application-wal-hot-journal-savepoint-checkpoint-current-source-next155 self-test passed' . PHP_EOL;
        return $summary;
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
