<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base next104')
    . $page('wp option base next104')
    . $page('wp plugin base next104')
    . $page('wp autoload base next104')
    . $page('wp transient base next104');

$salt1 = 0x10420401;
$salt2 = 0x10420402;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 104, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);

$append = static function (int $pageNumber, int $commitPageCount, string $label) use (&$walBytes, &$seed, $salt1, $salt2, $page): void {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$append(1, 0, 'wp schema retained next104');
$append(2, 5, 'wp option retained next104');
$append(3, 0, 'wp plugin rollback next104');
$append(4, 0, 'wp autoload rollback next104');
$append(4, 5, 'wp autoload rollback commit next104');
$append(5, 5, 'wp transient rollback commit next104');
$append(2, 5, 'wp option tail rollback next104');

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next104');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings-next104');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4);
$stack->recordWalFrameWrite(5, 4, true);
$stack->recordWalFrameWrite(6, 5, true);
$stack->recordWalFrameWrite(7, 2, true);

$plan = SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan::plan(
    $stack,
    'plugin-settings-next104',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    'restart',
    7
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'reader-savepoint-current-source-release-unblocks-checkpoint-next104');
    assert($plan['reader_rewound_pages'] === [2, 3, 4, 5]);
    assert($plan['pinned_checkpoint_busy'] === true);
    assert($plan['released_reader_uses_checkpoint_database'] === true);
    assert(in_array('sqlite-wal-checkpoint-reader-savepoint-current-source-next104', $plan['dependencies'], true));
    echo "wordpress-wal-checkpoint-reader-savepoint-current-source-next104 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-wal-checkpoint-reader-savepoint-current-source-next104',
    'wordpressUse' => 'Copied wp_options import rollback can checkpoint the retained WAL prefix while a current reader originally pinned inside rolled-back savepoint frames is rewound to the current source and a next reader after release uses the checkpointed database, without ext/sqlite.',
    'status' => $plan['status'],
    'pinnedAction' => $plan['pinned_wal_action'],
    'releasedAction' => $plan['released_wal_action'],
    'readerRewoundPages' => $plan['reader_rewound_pages'],
    'currentSources' => $plan['current_sources'],
    'pinnedNextSources' => $plan['pinned_next_sources'],
    'releasedNextSources' => $plan['released_next_sources'],
    'sourceDigest' => $plan['source_digest'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
