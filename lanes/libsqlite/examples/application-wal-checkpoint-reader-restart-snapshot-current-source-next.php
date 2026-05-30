<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointReaderRestartSnapshotCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema before reader restart')
    . $page('wp active_plugins before reader restart')
    . $page('wp autoload before reader restart')
    . $page('wp cron before reader restart')
    . $page('wp transient before reader restart');
$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x12412401;
    $salt2 = 0x12412402;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 124, $salt1, $salt2);
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

$walBytes = $makeWal([
    [2, 0, 'wp active_plugins reader snapshot'],
    [3, 5, 'wp autoload reader commit'],
    [2, 0, 'wp active_plugins later draft'],
    [4, 5, 'wp cron checkpoint commit'],
    [2, 5, 'wp active_plugins checkpoint tail'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$plan = SQLiteWalCheckpointReaderRestartSnapshotCurrentSourceNextPlan::plan(
    $databasePath,
    $wal,
    $walBytes,
    $databaseBytes,
    [[
        'pages' => [
            2 => $page('wp active_plugins restarted generation'),
            5 => $page('wp transient restarted generation'),
        ],
        'database_page_count' => 5,
    ]],
    [1, 2, 3, 4, 5],
    2
);

$summary = [
    'scenario' => 'application-wal-checkpoint-reader-restart-snapshot-current-source-next124',
    'applicationUse' => 'A Application import reader keeps an old WAL snapshot while checkpoint restart is possible only after release; the next writer appends to the restarted generation without changing the pinned current source.',
    'status' => $plan['status'],
    'readerEndFrame' => $plan['reader_end_frame'],
    'pinnedCheckpointBusy' => $plan['pinned_checkpoint']['busy'],
    'releasedWalAction' => $plan['released_checkpoint']['wal_action'],
    'nextUsesRestartedGeneration' => $plan['next_uses_restarted_wal_generation'],
    'currentSources' => $plan['current_sources'],
    'nextSources' => $plan['next_sources'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'wal-checkpoint-reader-restart-snapshot-current-source-next124');
    assert($summary['pinnedCheckpointBusy'] === true);
    assert($summary['releasedWalAction'] === 'restart_wal');
    assert($summary['nextUsesRestartedGeneration'] === true);
    assert($summary['currentSources'] === ['database', 'wal', 'wal', 'database', 'database']);
    assert($summary['nextSources'] === ['database', 'wal', 'database', 'database', 'wal']);
    echo "application-wal-checkpoint-reader-restart-snapshot-current-source-next124 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
