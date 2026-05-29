<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base next103')
    . $page('wp active plugins base next103')
    . $page('wp plugin settings base next103')
    . $page('wp autoload index base next103');

$salt1 = 0x10310310;
$salt2 = 0x3030103;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 103, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (int $pageNumber, int $commit, string $label) use (&$walBytes, &$seed, $salt1, $salt2, $page): void {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$appendFrame(1, 0, 'wp retained schema draft next103');
$appendFrame(2, 4, 'wp retained active plugins commit next103');
$appendFrame(3, 0, 'wp discarded plugin setting draft next103');
$appendFrame(4, 4, 'wp discarded autoload commit next103');
$appendFrame(2, 4, 'wp discarded active plugins retry next103');

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-retry');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 4, true);
$savepoints->recordWalFrameWrite(5, 2);

$plan = SQLiteWalSavepointCheckpointPlan::savepointRestartAppendReaderCurrentSourceNext(
    $savepoints,
    'plugin-retry',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databaseBytes,
    $databasePath,
    [[
        'pages' => [
            2 => $page('wp retry active plugins committed next103'),
            3 => $page('wp retry plugin settings committed next103'),
            4 => $page('wp retry autoload index committed next103'),
        ],
        'database_page_count' => 4,
        'commit' => true,
    ]],
    [1, 2, 3, 4],
    'restart',
    5
);

$summary = [
    'scenario' => 'wordpress-wal-savepoint-restart-reader-current-source-next103',
    'wordpressUse' => 'Model a failed plugin import savepoint whose retained WAL prefix is verified, checkpoint-restarted after the current reader releases, and followed by a retry append visible only to the next reader.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'retainedFrames' => $plan['retained_frame_count'],
    'discardedFrames' => $plan['discarded_frame_count'],
    'currentSources' => $plan['current_sources'],
    'releasedSources' => $plan['released_next_sources'],
    'nextSources' => $plan['next_sources'],
    'nextCommitFrame' => $plan['append']['last_commit_frame'],
    'sourceDigest' => $plan['source_digest'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'savepoint-restart-append-current-source-next103');
    assert($summary['retainedFrames'] === 2);
    assert($summary['discardedFrames'] === 3);
    assert($summary['currentSources'] === ['wal', 'wal', 'database', 'database']);
    assert($summary['releasedSources'] === ['database', 'database', 'database', 'database']);
    assert($summary['nextSources'] === ['database', 'wal', 'wal', 'wal']);
    assert($summary['nextCommitFrame'] === 3);
    echo "wordpress-wal-savepoint-restart-reader-current-source-next103 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
