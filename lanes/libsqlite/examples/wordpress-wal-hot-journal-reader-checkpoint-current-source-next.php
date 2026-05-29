<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next148.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('wp next148 clean sqlite header'),
    2 => $page('wp next148 clean wp_options root'),
    3 => $page('wp next148 clean active_plugins'),
];
$dirtyDatabase = $page('wp next148 dirty sqlite header')
    . $page('wp next148 dirty wp_options root')
    . $page('wp next148 dirty active_plugins');
$hotDatabase = implode('', $cleanPages);

$nonce = 0x2026148;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
}

$makeWalBytes = static function (array $frames) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 148, 0x14814801, 0x14814802);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commit, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commit, 0x14814801, 0x14814802);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWalBytes([
    [1, 0, 'wp next148 reader schema draft'],
    [2, 3, 'wp next148 checkpoint wp_options commit'],
]);
$checkpointDatabase = $page('wp next148 reader schema draft')
    . $page('wp next148 checkpoint wp_options commit')
    . $page('wp next148 clean active_plugins');

$plan = SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan::plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $hotDatabase,
    $walBytes,
    $checkpointDatabase,
    [1, 2, 3],
    2
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-reader-checkpoint-current-source-next148',
    'wordpressUse' => 'A copied WordPress SQLite database recovers a hot rollback journal, checks that checkpoint database bytes were built from that recovered source plus current WAL frames, and keeps the current reader pinned until it reopens.',
    'status' => $plan['status'],
    'checkpointAllowed' => $plan['checkpoint_allowed'],
    'checkpointMatchesExpected' => $plan['checkpoint_database_matches_expected'],
    'readerSeparatedPages' => $plan['reader_separated_from_checkpoint_page_numbers'],
    'checkpointLabels' => $plan['checkpoint_actual_labels'],
    'operationReasons' => $plan['operation_reasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($argv[1] ?? '') === '--self-test') {
        assert($summary['status'] === 'wal-hot-journal-reader-checkpoint-current-source-next148');
        assert($summary['checkpointAllowed'] === true);
        assert($summary['checkpointMatchesExpected'] === true);
        echo 'wordpress-wal-hot-journal-reader-checkpoint-current-source-next148 self-test passed' . PHP_EOL;
        return $summary;
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
