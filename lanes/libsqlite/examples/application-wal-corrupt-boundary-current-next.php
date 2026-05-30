<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp_options base schema') . $page('wp_options base option_value');
$salt1 = 0x24252627;
$salt2 = 0x34353637;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 21, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

$append = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $label) use ($pageSize, $salt1, $salt2): string {
    $image = str_pad($label, $pageSize, '.');
    $prefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $prefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$walBytes = $append($walBytes, $seed, 1, 0, 'wp_options schema committed before plugin import');
$walBytes = $append($walBytes, $seed, 2, 2, 'wp_options option_value committed before plugin import');
$walBytes = $append($walBytes, $seed, 3, 0, 'valid plugin draft frame after commit');
$walBytes = $append($walBytes, $seed, 4, 0, 'corrupt plugin draft frame after valid draft');
$corruptWalBytes = substr_replace($walBytes, 'X', 32 + ((24 + $pageSize) * 3) + 96, 1);

$boundary = SQLiteWal::corruptRecoveryCurrentNextBoundary($corruptWalBytes, $databaseBytes, [1, 2, 3]);

$summary = [
    'applicationUse' => 'Compare current reader-visible pages with the next recovered reader view for a copied wp_options WAL sidecar that has a valid draft frame followed by corrupt tail bytes.',
    'status' => $boundary['status'],
    'reason' => $boundary['reason'],
    'validFrameCount' => $boundary['valid_frame_count'],
    'committedFrameCount' => $boundary['committed_frame_count'],
    'firstInvalidFrame' => $boundary['first_invalid_frame'],
    'currentReaderSources' => $boundary['current_reader_sources'],
    'nextReaderSources' => $boundary['next_reader_sources'],
    'currentReaderFrames' => $boundary['current_reader_frame_indexes'],
    'nextReaderFrames' => $boundary['next_reader_frame_indexes'],
    'imagesMatchForVisiblePages' => SQLiteWal::corruptRecoveryCurrentNextBoundary($corruptWalBytes, $databaseBytes, [1, 2])['images_match'],
    'draftPageHiddenFromCurrentAndNext' => $boundary['current_reader_sources'][2] === 'missing' && $boundary['next_reader_sources'][2] === 'missing',
    'dependencies' => $boundary['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'recovered_committed_prefix');
    assert($summary['reason'] === 'uncommitted_valid_tail_before_corrupt_frame');
    assert($summary['validFrameCount'] === 3);
    assert($summary['committedFrameCount'] === 2);
    assert($summary['firstInvalidFrame'] === 4);
    assert($summary['currentReaderSources'] === ['wal', 'wal', 'missing']);
    assert($summary['nextReaderSources'] === ['wal', 'wal', 'missing']);
    assert($summary['imagesMatchForVisiblePages'] === true);
    assert($summary['draftPageHiddenFromCurrentAndNext'] === true);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
