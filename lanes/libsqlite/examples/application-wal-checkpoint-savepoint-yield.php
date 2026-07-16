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
$databaseBytes = $page('wp schema base') . $page('active_plugins base') . $page('autoload index base');
$salt1 = 0x48484848;
$salt2 = 0x98989898;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 48, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

$append = static function (int $pageNumber, int $commit, string $label) use (&$walBytes, &$seed, $page, $salt1, $salt2): void {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$append(1, 0, 'schema draft before plugin savepoint');
$append(2, 3, 'active_plugins committed before plugin savepoint');
$append(3, 0, 'autoload draft inside plugin savepoint');
$append(2, 3, 'active_plugins rolled back by ROLLBACK TO');

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 2, true);

$plan = SQLiteWalSavepointCheckpointPlan::yieldReaderSavepointCurrentNext(
    $savepoints,
    'plugin-settings',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databaseBytes,
    [1, 2, 3],
    'truncate'
);

$summary = [
    'scenario' => 'application-wal-checkpoint-savepoint-yield',
    'applicationUse' => 'Expose copied wp_options import reader visibility before ROLLBACK TO, after savepoint WAL truncation, and after checkpoint reset/truncate without requiring ext/sqlite.',
    'status' => $plan['status'],
    'checkpointReason' => $plan['checkpoint_reason'],
    'walAction' => $plan['wal_action'],
    'rolledBackFrames' => $plan['rolled_back_frame_indexes'],
    'beforeSources' => $plan['before_reader_sources'],
    'currentSources' => $plan['current_reader_sources'],
    'nextSources' => $plan['next_reader_sources'],
    'stageFrames' => array_column($plan['stages'], 'end_frame'),
    'yieldCount' => $plan['yield_count'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'busy');
    assert($summary['walAction'] === 'preserve_wal');
    assert($summary['rolledBackFrames'] === [3, 4]);
    assert($summary['beforeSources'] === ['wal', 'wal', 'database']);
    assert($summary['currentSources'] === ['wal', 'wal', 'database']);
    assert($summary['nextSources'] === ['wal', 'wal', 'database']);
    fwrite(STDOUT, "application-wal-checkpoint-savepoint-yield self-test passed\n");
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
