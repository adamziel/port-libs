<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp next106 base schema')
    . $page('wp next106 base active plugins')
    . $page('wp next106 base plugin settings')
    . $page('wp next106 base transient');

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

$oldSalt1 = 0x10610001;
$oldSalt2 = 0x10620002;
$newSalt1 = 0x10630003;
$newSalt2 = 0x10640004;
$currentWal = $makeWal(106, $oldSalt1, $oldSalt2, [
    [1, 0, 'wp next106 current schema draft'],
    [2, 4, 'wp next106 current active plugins commit'],
    [3, 0, 'wp next106 current plugin settings uncommitted'],
]);
$nextWal = $makeWal(107, $newSalt1, $newSalt2, [
    [2, 0, 'wp next106 restarted active plugins draft'],
    [3, 4, 'wp next106 restarted plugin settings commit'],
]) . substr($currentWal, 32 + (2 * (24 + $pageSize)));

$plan = SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan::currentSourceNext(
    $currentWal,
    $nextWal,
    $databaseBytes,
    [1, 2, 3, 4],
    $pageSize
);

$summary = [
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'currentCommittedFrames' => $plan['current_source']['committed_frame_count'],
    'nextCommittedFrames' => $plan['next_source']['committed_frame_count'],
    'staleSaltTailFrames' => $plan['stale_salt_tail_frame_count'],
    'currentSources' => $plan['current_reader_sources'],
    'nextSources' => $plan['next_reader_sources'],
    'operations' => array_column($plan['operations'], 'reason'),
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['status'] !== 'current_source_salt_recovered_next106') {
        throw new RuntimeException('Unexpected WAL salt recovery status');
    }
    if ($summary['staleSaltTailFrames'] !== 1) {
        throw new RuntimeException('Expected one stale current-source salt tail frame');
    }
    if ($summary['nextSources'] !== ['database', 'wal', 'wal', 'database']) {
        throw new RuntimeException('Unexpected next-reader source map');
    }

    echo "application-wal-checksum-salt-recovery-current-source-next106 self-test passed\n";

    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
