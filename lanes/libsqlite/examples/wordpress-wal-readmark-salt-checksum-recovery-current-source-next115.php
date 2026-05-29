<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp next115 base schema')
    . $page('wp next115 base active plugins')
    . $page('wp next115 base autoload')
    . $page('wp next115 base settings')
    . $page('wp next115 base cron');

$makeWal = static function (int $checkpoint, int $salt1, int $salt2, array $frames) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
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

$makeShm = static function (int $salt1, int $salt2, int $change, int $mxFrame, array $readMarks, array $readLocks, int $backfill, int $attempted) use ($pageSize): SQLiteShmIndex {
    $header = pack(
        'V*',
        3007000,
        $backfill,
        $change,
        (1 << 24) | $pageSize,
        $mxFrame,
        5,
        0x11550001,
        0x11550002,
        $salt1,
        $salt2,
        0x11550003,
        0x11550004
    );
    $marks = array_map(static fn (?int $frame): int => $frame ?? 0xffffffff, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return SQLiteShmIndex::parse($header . $header . $checkpoint);
};

$oldSalt1 = 0x11551011;
$oldSalt2 = 0x11551022;
$newSalt1 = 0x11552011;
$newSalt2 = 0x11552022;
$currentWal = $makeWal(115, $oldSalt1, $oldSalt2, [
    [2, 0, 'wp next115 current active draft'],
    [3, 5, 'wp next115 current autoload commit'],
    [4, 0, 'wp next115 current stale setting tail'],
]);
$nextWal = $makeWal(116, $newSalt1, $newSalt2, [
    [2, 0, 'wp next115 next active draft'],
    [4, 5, 'wp next115 next settings commit'],
    [5, 0, 'wp next115 next cron draft'],
    [2, 5, 'wp next115 next active commit'],
]) . substr($currentWal, 32 + (2 * (24 + $pageSize)));

$plan = SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan::currentSourceNext(
    $currentWal,
    $makeShm($oldSalt1, $oldSalt2, 115, 3, [0, 2, 3, null, null], [false, true, true, false, false], 1, 2),
    $nextWal,
    $makeShm($oldSalt1, $oldSalt2, 115, 3, [0, 2, null, null, null], [false, true, false, false, false], 1, 2),
    $databaseBytes,
    [1, 2, 3, 4, 5],
    $pageSize
);

$summary = [
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'currentReaderEndFrame' => $plan['current_reader_end_frame'],
    'nextReaderEndFrame' => $plan['next_reader_end_frame'],
    'nextRebuiltForSalt' => $plan['next_rebuilt_for_salt'],
    'currentSources' => $plan['current_reader_sources'],
    'nextSources' => $plan['next_reader_sources'],
    'operations' => array_column($plan['operations'], 'reason'),
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['status'] !== 'readmark_salt_rebuilt_next115') {
        throw new RuntimeException('Unexpected WAL readmark salt recovery status');
    }
    if ($summary['nextRebuiltForSalt'] !== true) {
        throw new RuntimeException('Expected stale next-generation SHM salt to rebuild read marks');
    }
    if ($summary['currentSources'] !== ['database', 'wal', 'wal', 'database', 'database']) {
        throw new RuntimeException('Unexpected current reader source map');
    }
    if ($summary['nextSources'] !== ['database', 'wal', 'database', 'wal', 'wal']) {
        throw new RuntimeException('Unexpected next reader source map');
    }

    echo "wordpress-wal-readmark-salt-checksum-recovery-current-source-next115 self-test passed\n";

    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
