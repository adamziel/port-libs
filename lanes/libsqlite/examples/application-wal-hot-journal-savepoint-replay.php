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

$cleanDatabase = $page('wp clean header before hot journal') . $page('wp clean options before hot journal') . $page('wp clean autoload before hot journal');
$dirtyDatabase = $page('wp dirty header after crash') . $page('wp dirty options after crash') . $page('wp dirty autoload after crash');

$nonce = 0x20260527;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', 3, $nonce, 3, $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ([1 => substr($cleanDatabase, 0, $pageSize), 2 => substr($cleanDatabase, $pageSize, $pageSize), 3 => substr($cleanDatabase, $pageSize * 2, $pageSize)] as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
}

$salt1 = 0x20260527;
$salt2 = 0x00000038;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 38, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp schema wal retained'],
    [2, 3, 'wp plugin index retained before failed batch'],
    [3, 0, 'wp failed plugin option draft'],
    [2, 3, 'wp failed plugin option commit'],
    [1, 0, 'wp nested retry draft'],
] as $frame) {
    [$pageNumber, $commitPageCount, $label] = $frame;
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('application_import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin_settings_batch');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 2, true);
$savepoints->savepoint('nested_plugin_retry');
$savepoints->recordWalFrameWrite(5, 1);

$plan = SQLiteWalHotJournalSavepointReplayPlan::replayCurrentNext(
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

$summary = [
    'status' => $plan['status'],
    'hotRecovered' => $plan['hot_recovered'],
    'savepoint' => $plan['savepoint'],
    'retainedFrames' => $plan['retained_frame_count'],
    'discardedFrames' => $plan['discarded_frame_count'],
    'currentSources' => $plan['current_reader_sources'],
    'nextSources' => $plan['next_reader_sources'],
    'checkpointPages' => $plan['checkpoint_database_page_count'],
    'discardedPluginCommitPresent' => str_contains((string) $plan['wal_recovery']['checkpoint_database_bytes'], 'wp failed plugin option commit'),
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'hot_journal_recovered_savepoint_wal_replayed' || $summary['retainedFrames'] !== 2 || $summary['discardedFrames'] !== 3) {
        fwrite(STDERR, "unexpected WAL hot-journal savepoint replay summary\n");
        exit(1);
    }
    if ($summary['discardedPluginCommitPresent']) {
        fwrite(STDERR, "discarded plugin commit reached checkpoint image\n");
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
