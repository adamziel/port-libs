<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('wp91 schema base')
    . $page('wp91 active_plugins base')
    . $page('wp91 autoload index base')
    . $page('wp91 cron transient base');

$salt1 = 0x91000011;
$salt2 = 0x91000022;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 91, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$append = static function (int $pageNumber, int $commit, string $image) use (&$walBytes, &$seed, $salt1, $salt2): void {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$append(1, 4, $page('wp91 schema txn retained'));
$append(2, 4, $page('wp91 parent active_plugins rolled back'));
$append(3, 0, $page('wp91 parent autoload draft rolled back'));
$append(2, 0, $page('wp91 released active_plugins rolled back'));
$append(4, 4, $page('wp91 released cron commit rolled back'));
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import');
$stack->recordWalFrameWrite(1, 1, true);
$stack->savepoint('plugin-parent');
$stack->recordWalFrameWrite(2, 2, true);
$stack->recordWalFrameWrite(3, 3);
$stack->savepoint('released-plugin');
$stack->recordWalFrameWrite(4, 2);
$stack->recordWalFrameWrite(5, 4, true);

$restart = SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext(
    $stack,
    'released-plugin',
    'plugin-parent',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 4],
    'restart'
);

$truncate = SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentSourceNext(
    $stack,
    'released-plugin',
    'plugin-parent',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 4],
    'truncate'
);

echo json_encode([
    'scenario' => 'application-wal-restart-truncate-savepoint-current-source-next91',
    'applicationUse' => 'Model a plugin import savepoint that is released into its parent and then rolled back before a WAL restart/truncate checkpoint, while validating the current WAL source bytes used by copied wp_options repair tooling.',
    'restart' => [
        'status' => $restart['status'],
        'walAction' => $restart['boundary']['wal_action'],
        'currentSourceFrames' => $restart['current_source']['frame_count'],
        'retainedSourceFrames' => $restart['retained_source']['frame_count'],
        'nextSourceKind' => $restart['next_source']['kind'],
        'nextCheckpoint' => $restart['next_source']['checkpoint_sequence'],
        'rolledBackReleasedFrames' => $restart['rolled_back_released_frames'],
        'currentReaderSources' => $restart['current_reader_sources'],
        'nextReaderSources' => $restart['next_reader_sources'],
        'imagesMatch' => $restart['images_match'],
    ],
    'truncate' => [
        'status' => $truncate['status'],
        'walAction' => $truncate['boundary']['wal_action'],
        'nextSourceKind' => $truncate['next_source']['kind'],
        'nextWalBytes' => $truncate['next_source']['wal_bytes_length'],
        'nextReaderSources' => $truncate['next_reader_sources'],
    ],
    'dependencies' => $restart['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
