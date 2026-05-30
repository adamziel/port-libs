<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteShmIndex.php';

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x12345678;
$salt2 = 0x9abcdef0;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 70, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([[2, 0, $page('wp-option-draft')], [3, 3, $page('wp-index-commit')], [2, 4, $page('wp-option-final')]] as $frame) {
    [$pageNumber, $commitPageCount, $image] = $frame;
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, null, true);
$pageSizeField = (1 << 24) | $pageSize;
$header = pack('V*', 3007000, 1, 70, $pageSizeField, 99, 4, 0, 0, $salt1, $salt2, 0, 0);
$marks = [0, 2, 3, 99, 0xffffffff];
$checkpoint = pack('V*', 1, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
    . "\x00\x01\x01\x01\x00\x00\x00\x00"
    . pack('V*', 1, 0);
$shm = SQLiteShmIndex::parse($header . $header . $checkpoint);
$plan = $shm->recoverReadMarksFromWal($wal);

$summary = [
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'lastCommitFrame' => $plan['last_commit_frame'],
    'preservedSlots' => $plan['preserved_slots'],
    'discardedSlots' => $plan['discarded_slots'],
    'nextReadMarks' => $plan['next_read_marks'],
    'checkpointPinnedFrame' => $plan['next_checkpoint_plan']['checkpoint_pinned_frame'],
    'nextReaderFrame' => $plan['next_reader_frame'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'recovered-with-readers');
    assert($summary['lastCommitFrame'] === 3);
    assert($summary['preservedSlots'] === [1, 2]);
    assert($summary['discardedSlots'] === [0, 3]);
    assert($summary['nextReadMarks'] === [null, 2, 3, null, null]);
    assert($summary['checkpointPinnedFrame'] === 2);
    assert($summary['nextReaderFrame'] === 3);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
