<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$salt1 = 0x50505050;
$salt2 = 0x90909090;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('base schema page for wp_options')
    . $page('base active_plugins option row')
    . $page('base autoload option index')
    . $page('base plugin settings page');

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 50, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (int $pageNumber, int $commitPageCount, string $image) use (&$walBytes, &$seed, $salt1, $salt2): void {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$appendFrame(2, 0, $page('frame 1 retained active_plugins import'));
$appendFrame(3, 0, $page('frame 2 retained autoload index draft'));
$appendFrame(3, 4, $page('frame 3 retained autoload index commit'));
$appendFrame(2, 0, $page('frame 4 rolled back plugin draft'));
$appendFrame(4, 4, $page('frame 5 rolled back plugin commit'));

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import');
$stack->recordWalFrameWrite(1, 2);
$stack->recordWalFrameWrite(2, 3);
$stack->recordWalFrameWrite(3, 3, true);
$stack->savepoint('plugin-settings');
$stack->recordWalFrameWrite(4, 2);
$stack->recordWalFrameWrite(5, 4, true);

$plan = SQLiteWalSavepointCheckpointPlan::readerRestartCurrentNextAfterRollbackTo(
    $stack,
    'plugin-settings',
    SQLiteWal::parse($walBytes, null, true),
    $walBytes,
    $databaseBytes,
    [2, 3, 4],
    'restart'
);

echo json_encode([
    'database' => '/srv/www/wp-content/database/.ht.sqlite',
    'wal' => '/srv/www/wp-content/database/.ht.sqlite-wal',
    'savepoint' => $plan['savepoint'],
    'retainedFrames' => $plan['retained_frame_count'],
    'discardedFrames' => $plan['discarded_frame_count'],
    'walAction' => $plan['wal_action'],
    'currentReaderFrames' => $plan['current_reader_frame_indexes'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'restartedCheckpointSequence' => $plan['restarted_checkpoint_sequence'],
    'rolledBackPluginPageVisibleNext' => str_contains(implode('', $plan['next_reader_images']), 'rolled back plugin'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . "\n";
