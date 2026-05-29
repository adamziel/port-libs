<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next144.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next144 clean sqlite header'),
    2 => $page('wp next144 clean wp_options root'),
    3 => $page('wp next144 clean active_plugins option'),
];
$dirtyDatabase = $page('wp next144 dirty sqlite header')
    . $page('wp next144 dirty wp_options root')
    . $page('wp next144 dirty active_plugins option');
$hotDatabase = implode('', $cleanPages);

$nonce = 0x2026144;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
}

$makeWalBytes = static function (array $frames) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 144, 0x14414401, 0x14414402);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commit, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commit, 0x14414401, 0x14414402);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWalBytes([
    [1, 0, 'wp next144 current reader schema draft'],
    [2, 3, 'wp next144 current reader wp_options commit'],
]);

$ready = SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $hotDatabase,
    $walBytes,
    [1, 2, 3],
    2
);

$staleReader = SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next144Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $dirtyDatabase,
    $walBytes,
    [1, 2, 3],
    2
);

$summary = [
    'scenario' => 'wordpress-wal-checkpoint-hot-journal-reader-current-source-next144',
    'wordpressUse' => 'After a copied wp_options database recovers a hot rollback journal, checkpoint reset is allowed only for readers pinned to the recovered database source, not dirty pre-recovery bytes with the same WAL header.',
    'readyStatus' => $ready['status'],
    'readyCheckpointAllowed' => $ready['checkpoint_allowed'],
    'staleStatus' => $staleReader['status'],
    'staleCheckpointAllowed' => $staleReader['checkpoint_allowed'],
    'staleReopenRequired' => $staleReader['reader_reopen_required'],
    'staleMismatchedPages' => $staleReader['mismatched_page_numbers'],
    'operationReasons' => $staleReader['operation_reasons'],
    'dependencyClosure' => 'no new support component needed; this reuses native rollback-journal recovery, WAL parsing, and reader database-source validation',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($summary['readyStatus'] === 'wal-checkpoint-hot-journal-reader-current-source-next144');
    assert($summary['readyCheckpointAllowed'] === true);
    assert($summary['staleStatus'] === 'wal-checkpoint-hot-journal-reader-current-source-reopen-next144');
    assert($summary['staleCheckpointAllowed'] === false);
    assert($summary['staleReopenRequired'] === true);
    assert($summary['staleMismatchedPages'] === [3]);

    if (in_array('--self-test', $_SERVER['argv'] ?? [], true)) {
        echo "wordpress-wal-checkpoint-hot-journal-reader-current-source-next144 self-test passed\n";
        return;
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
