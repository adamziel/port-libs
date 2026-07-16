<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalReaderSavepointRestartCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderSavepointRestartCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base')
    . $page('wp active_plugins base')
    . $page('wp autoload index base')
    . $page('wp plugin settings base')
    . $page('wp transient base');

$makeWalBytes = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x12112101;
    $salt2 = 0x12112102;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 121, $salt1, $salt2);
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

$staleWalBytes = $makeWalBytes([
    [1, 0, 'wp schema retained draft'],
    [2, 5, 'wp active_plugins retained commit'],
    [4, 0, 'wp plugin stale failed import'],
    [5, 5, 'wp transient stale failed commit'],
    [2, 5, 'wp active_plugins stale failed tail'],
]);
$restartedWalBytes = $makeWalBytes([
    [1, 0, 'wp schema retained draft'],
    [2, 5, 'wp active_plugins retained commit'],
    [3, 0, 'wp autoload restarted retry draft'],
    [4, 5, 'wp plugin settings restarted retry commit'],
    [2, 5, 'wp active_plugins restarted retry tail'],
]);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-options-import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordWalFrameWrite(3, 4);
$savepoints->recordWalFrameWrite(4, 5, true);
$savepoints->recordWalFrameWrite(5, 2, true);

$summary = SQLiteWalReaderSavepointRestartCurrentSourceNextPlan::plan(
    $savepoints,
    'plugin-settings',
    SQLiteWal::parse($staleWalBytes, $pageSize, true),
    $staleWalBytes,
    $staleWalBytes,
    $restartedWalBytes,
    $databaseBytes,
    [2, 3, 4, 5]
);

$output = [
    'behavior' => 'wal_reader_savepoint_restart_current_source_next121',
    'applicationUse' => 'After a failed wp_options import rolls back to a SAVEPOINT, stale reader WAL tail frames are ignored and the retry writer appends from the retained prefix as the current source.',
    'status' => $summary['status'],
    'retainedFrameCount' => $summary['retained_frame_count'],
    'discardedFrameIndexes' => $summary['discarded_frame_indexes'],
    'staleReaderTailPagesIgnored' => $summary['stale_reader_tail_pages_ignored'],
    'nextWriterFrameIndexes' => $summary['next_writer_frame_indexes'],
    'nextWriterPageNumbers' => $summary['next_writer_page_numbers'],
    'nextReplacedStaleTailPages' => $summary['next_replaced_stale_tail_pages'],
    'sourceTransitions' => $summary['source_transitions'],
    'dependencies' => $summary['dependencies'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($output['status'] === 'reader-savepoint-restart-current-source-next121');
    assert($output['retainedFrameCount'] === 2);
    assert($output['discardedFrameIndexes'] === [3, 4, 5]);
    assert($output['nextWriterFrameIndexes'] === [3, 4, 5]);
    assert($output['nextReplacedStaleTailPages'] === [2, 4]);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
