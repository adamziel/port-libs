<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base next117')
    . $page('wp option base next117')
    . $page('wp plugin base next117')
    . $page('wp autoload base next117')
    . $page('wp transient base next117');

$salt1 = 0x11711701;
$salt2 = 0x11711702;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 117, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$append = static function (int $pageNumber, int $commitPageCount, string $label) use (&$walBytes, &$seed, $page, $salt1, $salt2): void {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$append(1, 0, 'wp schema retained next117');
$append(2, 5, 'wp option retained next117');
$append(3, 0, 'wp plugin stale reader next117');
$append(4, 0, 'wp autoload stale reader next117');
$append(4, 5, 'wp autoload stale reader commit next117');
$append(5, 5, 'wp transient stale reader commit next117');
$append(2, 5, 'wp option stale reader tail next117');

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next117');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings-next117');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4);
$stack->recordWalFrameWrite(5, 4, true);
$stack->recordWalFrameWrite(6, 5, true);
$stack->recordWalFrameWrite(7, 2, true);

$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$plan = SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan::plan(
    $stack,
    'plugin-settings-next117',
    $wal,
    $walBytes,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    'restart',
    7
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'reader-stale-source-checkpoint-current-prefix-next117');
    assert($plan['stale_reader_tail_pages'] === [2, 3, 4, 5]);
    assert($plan['released_reader_uses_checkpoint_database'] === true);
    assert(in_array('sqlite-wal-savepoint-reader-checkpoint-current-source-next117', $plan['dependencies'], true));
    echo "application-wal-savepoint-reader-checkpoint-current-source-next117 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-wal-savepoint-reader-checkpoint-current-source-next117',
    'status' => $plan['status'],
    'staleReaderTailPages' => $plan['stale_reader_tail_pages'],
    'sourceTransitions' => $plan['source_transitions'],
    'applicationUse' => 'Copied wp_options import retries can ignore a stale reader WAL source after ROLLBACK TO, checkpoint the retained current WAL prefix, and let the next reader use the checkpointed database without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
