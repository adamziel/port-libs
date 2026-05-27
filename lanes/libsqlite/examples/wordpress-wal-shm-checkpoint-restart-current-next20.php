<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp_options schema page') . $page('wp_options base rows') . $page('autoload index base');
$salt1 = 0x13572468;
$salt2 = 0x24681357;

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 7, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commitPageCount, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('plugin option draft'));
$walBytes = $appendFrame($walBytes, $seed, 3, 3, $page('autoload index commit'));
$walBytes = $appendFrame($walBytes, $seed, 2, 3, $page('plugin option final commit'));
$wal = SQLiteWal::parse($walBytes, null, true);

$shmHeader = pack(
    'V*',
    3007000,
    1,
    88,
    (1 << 24) | $pageSize,
    3,
    3,
    0x01020304,
    0x05060708,
    0x11111111,
    0x22222222,
    0x33333333,
    0x44444444
);
$shmBytes = $shmHeader . $shmHeader
    . pack('V*', 1, 0, 2, 3, 0xffffffff, 9)
    . "\x00\x01\x01\x00\x00\x00\x00\x00"
    . pack('V*', 2, 0);
$shmPlan = SQLiteShmIndex::parse($shmBytes)->checkpointPlan();
$restartWithPinnedReader = $wal->durableCheckpointResult($databaseBytes, 'restart', $shmPlan['checkpoint_pinned_frame']);
$restartAfterReadersDrain = $wal->durableCheckpointResult($databaseBytes, 'restart');
$visibility = $wal->checkpointReaderVisibility($databaseBytes, [2, 3], 'restart', $wal->lastCommitFrame()?->index);

echo json_encode([
    'database' => '/srv/www/wp-content/database/.ht.sqlite',
    'wal' => '/srv/www/wp-content/database/.ht.sqlite-wal',
    'shm' => '/srv/www/wp-content/database/.ht.sqlite-shm',
    'pinnedReaderFrame' => $shmPlan['checkpoint_pinned_frame'],
    'restartWithPinnedReader' => [
        'busy' => $restartWithPinnedReader['busy'],
        'reason' => $restartWithPinnedReader['reason'],
        'walAction' => $restartWithPinnedReader['wal_action'],
        'checkpointedFrames' => $restartWithPinnedReader['checkpointed_frame_count'],
    ],
    'restartAfterReadersDrain' => [
        'busy' => $restartAfterReadersDrain['busy'],
        'reason' => $restartAfterReadersDrain['reason'],
        'walAction' => $restartAfterReadersDrain['wal_action'],
        'walBytesLength' => $restartAfterReadersDrain['wal_bytes_length'],
        'nextCheckpointSequence' => $restartAfterReadersDrain['wal_header']['checkpoint_sequence'] ?? null,
    ],
    'currentReaderVisibilityStable' => $visibility['stable'],
    'dependencies' => array_values(array_unique(array_merge($shmPlan['dependencies'], $restartAfterReadersDrain['dependencies'], $visibility['dependencies']))),
], JSON_PRETTY_PRINT) . "\n";
