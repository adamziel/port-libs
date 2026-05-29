<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointCheckpointPlan.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp next111 base schema')
    . $page('wp next111 base active_plugins')
    . $page('wp next111 base plugin option')
    . $page('wp next111 base autoload option')
    . $page('wp next111 base transient option');

$salt1 = 0x11111101;
$salt2 = 0x11111102;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 111, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$append = static function (int $pageNumber, int $commit, string $label, bool $corrupt = false) use (&$walBytes, &$seed, $salt1, $salt2, $page): void {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $frame = $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    if ($corrupt) {
        $frame = substr_replace($frame, 'Z', 44, 1);
    }
    $walBytes .= $frame;
};

$append(1, 0, 'wp next111 retained schema draft');
$append(2, 5, 'wp next111 retained active_plugins commit');
$append(3, 0, 'wp next111 plugin option savepoint draft');
$append(4, 0, 'wp next111 autoload option savepoint draft');
$append(4, 5, 'wp next111 autoload option savepoint commit');
$append(5, 0, 'wp next111 uncommitted transient tail');
$append(2, 5, 'wp next111 corrupt stale active_plugins tail', true);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wordpress-import-next111');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings-next111');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4);
$stack->recordWalFrameWrite(5, 4, true);
$stack->recordWalFrameWrite(6, 5);

$summary = SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan::plan(
    $stack,
    'plugin-settings-next111',
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    'restart',
    null,
    $pageSize
);

$output = [
    'behavior' => 'wal_savepoint_checkpoint_reader_recovery_current_source_next111',
    'status' => $summary['status'],
    'reason' => $summary['reason'],
    'reader_frames' => [
        'original' => $summary['original_reader_end_frame'],
        'valid' => $summary['valid_reader_end_frame'],
        'recovered' => $summary['recovered_reader_end_frame'],
        'current' => $summary['current_reader_end_frame'],
    ],
    'checkpoint' => [
        'pinned_busy' => $summary['pinned_checkpoint_busy'],
        'pinned_action' => $summary['pinned_wal_action'],
        'released_action' => $summary['released_wal_action'],
        'release_unblocked' => $summary['reader_release_unblocked_checkpoint'],
    ],
    'sources' => [
        'before' => $summary['before_sources'],
        'recovered' => $summary['recovered_sources'],
        'current' => $summary['current_sources'],
        'pinned_next' => $summary['pinned_next_sources'],
        'released_next' => $summary['released_next_sources'],
    ],
    'transitions' => $summary['transitions'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($output['status'] === 'reader-recovered-savepoint-checkpoint-release-unblocks-next111');
    assert($output['reader_frames']['original'] === 7);
    assert($output['reader_frames']['recovered'] === 5);
    assert($output['reader_frames']['current'] === 2);
    assert($output['checkpoint']['release_unblocked'] === true);
    assert($output['sources']['released_next'] === ['database', 'database', 'database', 'database', 'database']);
    echo "wordpress-wal-savepoint-checkpoint-reader-recovery-current-source-next111 self-test passed\n";
    return;
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
