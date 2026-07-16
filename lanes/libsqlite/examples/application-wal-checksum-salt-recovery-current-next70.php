<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp_options base schema') . $page('wp_options base active_plugins') . $page('wp_options base transient');

$makeWal = static function (int $checkpoint, int $salt1, int $salt2, array $frames) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as $frame) {
        $image = $page($frame[2]);
        $framePrefix = pack('N*', $frame[0], $frame[1], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$oldWal = $makeWal(70, 0x70010001, 0x70020002, [
    [1, 0, 'old wal schema for plugin update'],
    [2, 3, 'old wal committed active_plugins'],
    [3, 0, 'old wal uncommitted transient draft'],
]);
$newWal = $makeWal(71, 0x70010002, 0x70030003, [
    [2, 0, 'new wal active_plugins restart draft'],
    [3, 3, 'new wal committed transient cleanup'],
]);

$plan = SQLiteWal::checksumSaltRecoveryCurrentNext(
    $oldWal,
    $newWal . substr($oldWal, 32 + (24 + $pageSize)),
    $databaseBytes,
    [1, 2, 3],
    $pageSize
);

$summary = [
    'scenario' => 'application-wal-checksum-salt-recovery-current-next70',
    'applicationUse' => 'Copied wp_options WAL recovery after a restart uses the new WAL header salt and checksum chain while ignoring stale old-salt frames left past the durable next WAL prefix.',
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'saltChanged' => $plan['salt_changed'],
    'currentSalt' => $plan['current_salt'],
    'nextSalt' => $plan['next_salt'],
    'currentSources' => $plan['current_reader_sources'],
    'nextSources' => $plan['next_reader_sources'],
    'nextDiscardedCorruptTailFrames' => $plan['next_discarded_corrupt_tail_frame_count'],
    'imagesChanged' => $plan['images_changed'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'salt-recovered-current-next');
    assert($summary['reason'] === 'next_wal_restarted_and_ignored_stale_salt_tail');
    assert($summary['saltChanged'] === true);
    assert($summary['nextDiscardedCorruptTailFrames'] === 2);
    assert($summary['nextSources'] === ['database', 'wal', 'wal']);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
