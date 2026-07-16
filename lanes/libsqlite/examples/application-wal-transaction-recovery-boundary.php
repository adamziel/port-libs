<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$databaseBytes = str_pad('wp-options-before-wal-schema', $pageSize, '.') . str_pad('wp-options-before-wal-data', $pageSize, '.');
$salt1 = 0x51515151;
$salt2 = 0x61616161;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 5, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $label) use ($pageSize, $salt1, $salt2): string {
    $pageImage = str_pad($label, $pageSize, "\0");
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $pageImage, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $pageImage;
};

$walBytes = $appendFrame($walBytes, $seed, 1, 0, 'wp-options schema committed before plugin import');
$walBytes = $appendFrame($walBytes, $seed, 2, 2, 'wp-options data committed before plugin import');
$walBytes = $appendFrame($walBytes, $seed, 3, 0, 'plugin settings draft frame that must be discarded');
$walBytes = substr_replace($appendFrame($walBytes, $seed, 4, 0, 'corrupt plugin tail frame'), 'X', 32 + ((24 + $pageSize) * 3) + 80, 1);

$boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes);

$summary = [
    'applicationUse' => 'Recover copied wp_options WAL sidecars only through the last complete committed transaction, discarding valid draft frames and corrupt tail bytes before checkpointing without requiring ext/sqlite.',
    'status' => $boundary['status'],
    'reason' => $boundary['reason'],
    'validFrameCount' => $boundary['valid_frame_count'],
    'committedFrameCount' => $boundary['committed_frame_count'],
    'firstInvalidFrame' => $boundary['first_invalid_frame'],
    'discardedValidTailFrames' => $boundary['discarded_valid_tail_frame_count'],
    'discardedCorruptTailFrames' => $boundary['discarded_corrupt_tail_frame_count'],
    'committedWalBytes' => strlen($boundary['committed_wal_bytes']),
    'validWalBytes' => strlen($boundary['valid_wal_bytes']),
    'checkpointContainsCommittedData' => str_contains($boundary['checkpoint_database_bytes'] ?? '', 'data committed before plugin import'),
    'checkpointContainsDraftPluginSettings' => str_contains($boundary['checkpoint_database_bytes'] ?? '', 'plugin settings draft frame'),
    'dependencies' => $boundary['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'recovered_committed_prefix');
    assert($summary['reason'] === 'uncommitted_valid_tail_before_corrupt_frame');
    assert($summary['validFrameCount'] === 3);
    assert($summary['committedFrameCount'] === 2);
    assert($summary['firstInvalidFrame'] === 4);
    assert($summary['discardedValidTailFrames'] === 1);
    assert($summary['discardedCorruptTailFrames'] === 1);
    assert($summary['checkpointContainsCommittedData'] === true);
    assert($summary['checkpointContainsDraftPluginSettings'] === false);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
