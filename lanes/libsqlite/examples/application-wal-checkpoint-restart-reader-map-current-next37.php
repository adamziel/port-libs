<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteShmIndex.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x37373737;
$salt2 = 0x57575757;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = $page('wp_options schema baseline')
    . $page('siteurl option before checkpoint')
    . $page('autoload index before checkpoint')
    . $page('plugin settings before checkpoint');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 37, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commitPageCount, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('siteurl draft visible to current reader'));
$walBytes = $appendFrame($walBytes, $seed, 3, 3, $page('autoload index commit visible to current reader'));
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('siteurl final after current reader'));
$walBytes = $appendFrame($walBytes, $seed, 4, 4, $page('plugin settings commit after current reader'));
$wal = SQLiteWal::parse($walBytes, null, true);

$pageSizeField = (1 << 24) | $pageSize;
$header = pack(
    'V*',
    3007000,
    1,
    141,
    $pageSizeField,
    4,
    4,
    0x11111111,
    0x22222222,
    0x33333333,
    0x44444444,
    0x55555555,
    0x66666666
);
$readMarks = [0, 2, 4, 0xffffffff, 0xffffffff];
$readLocks = ["\x00", "\x01", "\x01", "\x00", "\x00", "\x00", "\x00", "\x00"];
$checkpointInfo = pack('V*', 1, $readMarks[0], $readMarks[1], $readMarks[2], $readMarks[3], $readMarks[4])
    . implode('', $readLocks)
    . pack('V*', 3, 0);
$shm = SQLiteShmIndex::parse($header . $header . $checkpointInfo);

$plan = $wal->restartReadMarkReaderMapTransition($database, $shm, [2, 3, 4], 'restart');

$report = [
    'scenario' => 'application-wal-checkpoint-restart-reader-map-current-next37',
    'applicationUse' => 'During a copied wp_options import checkpoint restart, preserve the current reader map pinned by SHM read marks while mapping the next reader against checkpointed database bytes.',
    'status' => $plan['status'],
    'checkpointAction' => $plan['transition']['checkpoint']['wal_action'],
    'currentReaderEndFrame' => $plan['current_reader_end_frame'],
    'nextReaderEndFrame' => $plan['next_reader_end_frame'],
    'currentReaderSources' => $plan['current_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'currentReaderFrames' => $plan['current_reader_frame_indexes'],
    'nextReaderFrames' => $plan['next_reader_frame_indexes'],
    'nextReadMarks' => $plan['transition']['next_read_marks'],
    'currentReaderKeptSnapshot' => $plan['current_reader_kept_snapshot'],
    'dependencies' => $plan['dependencies'],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
