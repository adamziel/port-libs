<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp-base-page-1') . $page('wp-base-page-2') . $page('wp-base-page-3');

$salt1 = 0x10203040;
$salt2 = 0x50607080;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 23, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

foreach ([
    [2, 0, $page('wp-option-import-page-2')],
    [3, 3, $page('wp-option-import-commit')],
    [2, 0, $page('wp-option-settings-page-2')],
    [4, 4, $page('wp-autoload-index-commit')],
    [1, 0, $page('wp-uncommitted-tail-page')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, null, true);
$reader = $wal->readerSnapshotPageImage($databaseBytes, 2, 2);
$visibility = $wal->checkpointReaderVisibility($databaseBytes, [2, 3], 'passive', 2);
$checkpoint = $wal->checkpointModeResult($databaseBytes, 'truncate');
$readMarks = $wal->readMarkPlan([0, 2, 4, null]);
$corruptTail = SQLiteWal::checksumRecoveryBoundary(substr($walBytes, 0, 32 + (4 * (24 + $pageSize))) . substr($walBytes, -12), $databaseBytes);

echo json_encode([
    'scenario' => 'application-wal-checkpoint-snapshot-corpus',
    'applicationUse' => 'Inspect copied wp_options WAL snapshots while a reader pins an older frame, then plan checkpoint visibility and corrupt-tail recovery without requiring ext/sqlite.',
    'readerPage2' => [
        'source' => $reader['source'],
        'frame' => $reader['frame_index'],
        'label' => rtrim(substr($reader['image'], 0, 25), '.'),
    ],
    'checkpoint' => [
        'mode' => $visibility['mode'],
        'busy' => $visibility['checkpoint_busy'],
        'stable' => $visibility['stable'],
        'walAction' => $visibility['wal_action'],
        'truncateActionWithTail' => $checkpoint['wal_action'],
    ],
    'readMarks' => [
        'pinnedFrame' => $readMarks['checkpoint_pinned_frame'],
        'recommendedSlot' => $readMarks['recommended_reader_slot'],
        'resetBlocked' => $readMarks['reset_blocked'],
    ],
    'corruptTailRecovery' => [
        'status' => $corruptTail['status'],
        'validFrameCount' => $corruptTail['valid_frame_count'],
        'canCheckpoint' => $corruptTail['can_checkpoint'],
    ],
], JSON_PRETTY_PRINT) . "\n";
