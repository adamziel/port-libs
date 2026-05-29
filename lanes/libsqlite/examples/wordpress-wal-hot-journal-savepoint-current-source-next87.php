<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointReplayPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanDatabase = $page('wp next87 clean header') . $page('wp next87 clean options') . $page('wp next87 clean index');
$dirtyDatabase = $page('wp next87 dirty header') . $page('wp next87 dirty options') . $page('wp next87 dirty index');

$nonce = 0x20260528;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', 3, $nonce, 3, $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ([1 => substr($cleanDatabase, 0, $pageSize), 2 => substr($cleanDatabase, $pageSize, $pageSize), 3 => substr($cleanDatabase, $pageSize * 2, $pageSize)] as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
}

$salt1 = 0x20260528;
$salt2 = 87;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 87, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp next87 schema retained'],
    [2, 3, 'wp next87 option retained commit'],
    [3, 0, 'wp next87 plugin draft'],
    [2, 3, 'wp next87 plugin commit discarded'],
] as $frame) {
    [$pageNumber, $commitPageCount, $label] = $frame;
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wordpress_import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin_settings_batch');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 2, true);

$plan = SQLiteWalHotJournalSavepointReplayPlan::replayCurrentSourceNext(
    SQLiteRollbackJournal::parse($journalBytes, true),
    $dirtyDatabase,
    $journalBytes,
    $savepoints,
    'plugin_settings_batch',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databasePath,
    [1, 2, 3]
);

$staleRejected = false;
try {
    SQLiteWalHotJournalSavepointReplayPlan::replayCurrentSourceNext(
        SQLiteRollbackJournal::parse(substr($journalBytes, 0, -1) . 'x'),
        $dirtyDatabase,
        $journalBytes,
        $savepoints,
        'plugin_settings_batch',
        SQLiteWal::parse($walBytes, $pageSize, true),
        $walBytes,
        $databasePath,
        [1]
    );
} catch (InvalidArgumentException) {
    $staleRejected = true;
}

$summary = [
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'hotRecovered' => $plan['hot_recovered'],
    'retainedFrames' => $plan['retained_frame_count'],
    'discardedFrames' => $plan['discarded_frame_count'],
    'currentSource' => $plan['current_source'],
    'currentSources' => $plan['current_reader_sources'],
    'discardedPluginCommitPresent' => str_contains((string) $plan['wal_recovery']['checkpoint_database_bytes'], 'wp next87 plugin commit discarded'),
    'staleParsedJournalRejected' => $staleRejected,
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'hot_journal_recovered_savepoint_wal_replayed' || $summary['retainedFrames'] !== 2 || $summary['discardedFrames'] !== 2) {
        fwrite(STDERR, "unexpected current-source replay summary\n");
        exit(1);
    }
    if (!$summary['staleParsedJournalRejected'] || $summary['discardedPluginCommitPresent']) {
        fwrite(STDERR, "current-source guard failed\n");
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
