<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointCheckpointPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp_options schema before plugin release')
    . $page('active_plugins before plugin release')
    . $page('autoload index before plugin release')
    . $page('transient cache before plugin release');

$salt1 = 0x51515151;
$salt2 = 0x91919191;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 51, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$append = static function (int $pageNumber, int $commit, string $label) use (&$walBytes, &$seed, $salt1, $salt2, $page): void {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $image = $page($label);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$append(1, 0, 'schema draft before plugin release');
$append(2, 4, 'active_plugins committed before plugin release');
$append(3, 0, 'plugin settings draft inside released savepoint');
$append(3, 4, 'plugin settings committed by RELEASE SAVEPOINT');
$append(4, 0, 'transient draft inside nested released savepoint');
$append(4, 4, 'transient committed by nested RELEASE SAVEPOINT');

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 3, true);
$savepoints->savepoint('transient-refresh');
$savepoints->recordWalFrameWrite(5, 4);
$savepoints->recordWalFrameWrite(6, 4, true);

$plan = SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentNext(
    $savepoints,
    'plugin-settings',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $databaseBytes,
    [2, 3, 4],
    'restart'
);

$summary = [
    'scenario' => 'application-wal-checkpoint-savepoint-release-current-next51',
    'applicationUse' => 'Expose copied wp_options import reader visibility after RELEASE SAVEPOINT merges plugin frames, then after checkpoint restart materializes those frames without requiring ext/sqlite.',
    'status' => $plan['status'],
    'mode' => $plan['mode'],
    'walAction' => $plan['wal_action'],
    'releasedFrames' => $plan['released_frame_names'],
    'mergedPages' => $plan['merged_page_numbers'],
    'beforeSources' => $plan['before_reader_sources'],
    'afterReleaseSources' => $plan['after_release_reader_sources'],
    'nextSources' => $plan['next_reader_sources'],
    'releaseToNextMatch' => $plan['release_to_next_images_match'],
    'yieldCount' => $plan['yield_count'],
    'dependencies' => $plan['dependencies'],
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (
    $summary['status'] === 'ready'
    && $summary['walAction'] === 'restart_wal'
    && $summary['releasedFrames'] === ['plugin-settings', 'transient-refresh']
    && $summary['nextSources'] === ['database', 'database', 'database']
    && $summary['releaseToNextMatch'] === true
) {
    fwrite(STDOUT, "application-wal-checkpoint-savepoint-release-current-next51 self-test passed\n");
    exit(0);
}

fwrite(STDERR, "application-wal-checkpoint-savepoint-release-current-next51 self-test failed\n");
exit(1);
