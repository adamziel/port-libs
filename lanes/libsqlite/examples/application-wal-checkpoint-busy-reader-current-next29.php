<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x29292929;
$salt2 = 0x71717171;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp_options schema page baseline')
    . $page('wp_options active_plugins baseline')
    . $page('wp_options autoload index baseline');

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 29, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (int $pageNumber, int $commitPageCount, string $image) use (&$walBytes, &$seed, $salt1, $salt2): void {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$appendFrame(2, 0, $page('reader-pinned active_plugins before checkpoint'));
$appendFrame(3, 3, $page('checkpointable autoload index page'));
$appendFrame(2, 0, $page('later active_plugins draft'));
$appendFrame(2, 3, $page('next-reader active_plugins committed'));

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = $wal->checkpointBusyReaderCurrentNext($databaseBytes, [2, 3], 'full', 2);

$report = [
    'scenario' => 'application-wal-checkpoint-busy-reader-current-next29',
    'applicationUse' => 'A copied wp_options import runs FULL checkpoint while an existing reader is pinned at an older WAL frame; current readers keep their snapshot, while next readers use the checkpointed database plus preserved WAL tail.',
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'checkpointedFrames' => $plan['checkpoint']['checkpointed_frame_count'],
    'remainingCommittedFrames' => $plan['checkpoint']['remaining_committed_frame_count'],
    'walAction' => $plan['wal_action'],
    'currentReaderSources' => $plan['current_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'currentReaderFrames' => $plan['current_reader_frame_indexes'],
    'nextReaderFrames' => $plan['next_reader_frame_indexes'],
    'nextUsesCheckpointDatabase' => $plan['next_uses_checkpoint_database'],
    'nextUsesPreservedWal' => $plan['next_uses_preserved_wal'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($report['status'] === 'busy');
    assert($report['reason'] === 'reader_blocks_checkpoint_completion');
    assert($report['currentReaderFrames'] === [1, 2]);
    assert($report['nextReaderFrames'] === [4, 2]);
    assert($report['nextUsesCheckpointDatabase'] === true);
    assert($report['nextUsesPreservedWal'] === true);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
