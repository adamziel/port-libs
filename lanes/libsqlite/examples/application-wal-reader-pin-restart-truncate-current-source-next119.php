<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('wp119 schema base')
    . $page('wp119 active_plugins base')
    . $page('wp119 autoload index base')
    . $page('wp119 cron base')
    . $page('wp119 transient base');

$salt1 = 0x11911911;
$salt2 = 0x11911922;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 119, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$append = static function (int $pageNumber, int $commit, string $image) use (&$walBytes, &$seed, $salt1, $salt2): void {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$append(2, 0, $page('wp119 active_plugins reader-pinned frame'));
$append(3, 5, $page('wp119 autoload reader-pinned commit'));
$append(2, 0, $page('wp119 active_plugins latest checkpointed frame'));
$append(4, 5, $page('wp119 cron latest checkpointed frame'));

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = $wal->restartTruncateReaderPinCurrentSourceNext(
    $walBytes,
    $databaseBytes,
    [2, 3, 4, 5],
    [0, 2, 4, null]
);

$summary = [
    'database' => 'wp-content/database/.ht.sqlite',
    'behavior' => 'wal_reader_pin_restart_truncate_current_source_next119',
    'status' => $plan['status'],
    'checkpointPinnedFrame' => $plan['checkpoint_pinned_frame'],
    'currentSources' => $plan['current_sources'],
    'pinnedNextSources' => $plan['pinned_next_sources'],
    'restartNextSources' => $plan['restart_next_sources'],
    'truncateNextSources' => $plan['truncate_next_sources'],
    'pinnedRestartAction' => $plan['pinned_restart']['wal_action'],
    'pinnedTruncateAction' => $plan['pinned_truncate']['wal_action'],
    'releasedRestartAction' => $plan['released_restart']['wal_action'],
    'releasedTruncateAction' => $plan['released_truncate']['wal_action'],
    'releasedRestartCheckpointSequence' => $plan['released_restart_generation']['checkpoint_sequence'],
    'releasedTruncateWalBytes' => $plan['released_truncate_generation']['wal_bytes_length'],
    'restartTruncateDatabaseMatch' => $plan['restart_truncate_database_match'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'reader-pin-restart-truncate-current-source-next119');
    assert($summary['checkpointPinnedFrame'] === 2);
    assert($summary['pinnedRestartAction'] === 'preserve_wal');
    assert($summary['pinnedTruncateAction'] === 'preserve_wal');
    assert($summary['releasedRestartAction'] === 'restart_wal');
    assert($summary['releasedTruncateAction'] === 'truncate_wal');
    assert($summary['releasedRestartCheckpointSequence'] === 120);
    assert($summary['releasedTruncateWalBytes'] === 0);
    assert($summary['restartTruncateDatabaseMatch'] === true);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
