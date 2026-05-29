<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$salt1 = 0x11011011;
$salt2 = 0x22022022;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 110, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);

$append = static function (int $pageNumber, int $commit, string $image) use (&$walBytes, &$seed, $salt1, $salt2): void {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$append(1, 0, $page('wp schema before release current source next110'));
$append(2, 4, $page('wp active_plugins commit before release next110'));
$append(3, 0, $page('wp plugin settings draft release next110'));
$append(3, 4, $page('wp plugin settings commit release next110'));
$append(4, 0, $page('wp transient draft nested release next110'));
$append(4, 4, $page('wp transient commit nested release next110'));

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wordpress-import');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-batch');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 3, true);
$stack->savepoint('transient-batch');
$stack->recordWalFrameWrite(5, 4);
$stack->recordWalFrameWrite(6, 4, true);

$plan = $stack->releaseCurrentWalSourceAndAppendFrame(
    'plugin-batch',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    5,
    true
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['current_source_verified'] === true);
    assert($plan['release_plan']['released_frame_names'] === ['plugin-batch', 'transient-batch']);
    assert($plan['pending_wal_frame_indexes_after_release'] === [1, 2, 3, 4, 5, 6]);
    assert($plan['next_wal_frame_index'] === 7);
    assert($plan['pending_page_numbers_after_next'] === [1, 2, 3, 4, 5]);
    assert($plan['released_savepoint_active_after'] === false);
    echo "wordpress transaction savepoint WAL release current-source next110 self-test passed\n";
    return;
}

echo json_encode([
    'released_savepoint' => $plan['released_savepoint'],
    'released_frames' => $plan['release_plan']['released_frame_names'],
    'pending_wal_after_release' => $plan['pending_wal_frame_indexes_after_release'],
    'next_wal_frame' => $plan['next_wal_frame_index'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . "\n";
