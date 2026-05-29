<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp108 schema before checkpoint')
    . $page('wp108 active_plugins base')
    . $page('wp108 autoload base')
    . $page('wp108 transient base')
    . $page('wp108 cron base');

$salt1 = 0x10810801;
$salt2 = 0x10810802;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 108, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$append = static function (int $pageNumber, int $commitPageCount, string $label) use (&$walBytes, &$seed, $salt1, $salt2, $page): void {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$append(2, 0, 'wp108 active_plugins old reader draft');
$append(3, 5, 'wp108 autoload first committed import');
$append(2, 0, 'wp108 active_plugins latest draft');
$append(4, 5, 'wp108 transient committed import');
$append(5, 0, 'wp108 cron tail before final commit');
$append(2, 5, 'wp108 active_plugins latest committed');

$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$plan = $wal->checkpointSnapshotCurrentSourceNext($walBytes, $databaseBytes, [1, 2, 3, 4, 5], 2);

$summary = [
    'status' => $plan['status'],
    'currentReaderEndFrame' => $plan['current_reader_end_frame'],
    'nextReaderEndFrame' => $plan['next_reader_end_frame'],
    'limitedPassiveReason' => $plan['limited_passive_checkpoint']['reason'],
    'limitedFullReason' => $plan['limited_full_checkpoint']['reason'],
    'releasedFullReason' => $plan['released_full_checkpoint']['reason'],
    'currentSources' => $plan['current_sources'],
    'nextSources' => $plan['next_sources'],
    'releasedDatabaseSources' => $plan['released_database_sources'],
    'currentStableAfterLimitedCheckpoint' => $plan['current_stable_after_limited_checkpoint'],
    'nextMatchesReleasedCheckpointDatabase' => $plan['next_matches_released_checkpoint_database'],
    'activePluginsCurrentLabel' => rtrim(substr($plan['current_reader'][1]['image'], 0, 96), ".\0"),
    'activePluginsNextLabel' => rtrim(substr($plan['next_reader'][1]['image'], 0, 96), ".\0"),
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'reader-checkpoint-snapshot-current-source-next108');
    assert($summary['limitedPassiveReason'] === 'reader_limited_passive_checkpoint');
    assert($summary['limitedFullReason'] === 'reader_blocks_checkpoint_completion');
    assert($summary['releasedFullReason'] === 'full_checkpoint_complete');
    assert($summary['currentSources'] === ['database', 'wal', 'wal', 'database', 'database']);
    assert($summary['nextSources'] === ['database', 'wal', 'wal', 'wal', 'wal']);
    assert($summary['currentStableAfterLimitedCheckpoint'] === true);
    assert($summary['nextMatchesReleasedCheckpointDatabase'] === true);
    assert(str_contains($summary['activePluginsCurrentLabel'], 'old reader draft'));
    assert(str_contains($summary['activePluginsNextLabel'], 'latest committed'));
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
