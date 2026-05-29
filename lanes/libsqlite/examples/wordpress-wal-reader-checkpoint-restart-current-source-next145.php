<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteShmIndex.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalAppendPlan.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointCheckpointPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$salt1 = 0x14514501;
$salt2 = 0x14514502;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp next145 base schema')
    . $page('wp next145 base active_plugins')
    . $page('wp next145 base plugin settings')
    . $page('wp next145 base cron option');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 145, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp next145 retained schema draft'],
    [2, 4, 'wp next145 retained active_plugins commit'],
    [3, 0, 'wp next145 rolled plugin settings draft'],
    [3, 4, 'wp next145 rolled plugin settings commit'],
    [4, 4, 'wp next145 rolled cron option commit'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 5) use ($pageSize, $salt1, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 145, $pageSizeField, $mxFrame, 4, 1, 2, $salt1, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next145');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings-next145');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 3, true);
$stack->recordWalFrameWrite(5, 4, true);

$plan = SQLiteWalSavepointCheckpointPlan::readerCheckpointRestartSavepointCurrentSourceNext(
    $stack,
    'plugin-settings-next145',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databaseBytes,
    SQLiteShmIndex::parse($makeShm([0, 4], [false, true], 2, 3)),
    SQLiteShmIndex::parse($makeShm([0, null], [false, false], 5, 5)),
    '/srv/www/wp-content/database/wp-next145.sqlite',
    [[
        'pages' => [
            3 => $page('wp next145 retried plugin settings restarted generation'),
            4 => $page('wp next145 retried cron option restarted generation'),
        ],
        'database_page_count' => 4,
        'commit' => true,
    ]],
    [1, 2, 3, 4]
);

$summary = [
    'scenario' => 'wordpress-wal-reader-checkpoint-restart-current-source-next145',
    'wordpressUse' => 'A copied WordPress options import rolls back a plugin-setting savepoint while an existing reader pins the current WAL source; RESTART checkpoint waits for that reader, then preserves a fresh WAL header for the retry write.',
    'status' => $plan['status'],
    'activeReaderFrame' => $plan['active_reader_end_frame'],
    'writerCurrentFrame' => $plan['writer_current_reader_end_frame'],
    'activeWalAction' => $plan['active_wal_action'],
    'releasedWalAction' => $plan['released_wal_action'],
    'releasedWalBytes' => $plan['released_wal_bytes_length'],
    'appendStartFrame' => $plan['append_start_frame'],
    'appendEndFrame' => $plan['append_end_frame'],
    'sourceTransitions' => $plan['source_transitions'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($summary['status'] !== 'reader-checkpoint-restart-savepoint-current-source-next145'
        || $summary['activeWalAction'] !== 'preserve_wal'
        || $summary['releasedWalAction'] !== 'restart_wal'
        || $summary['releasedWalBytes'] !== 32
        || $summary['appendStartFrame'] !== 1
    ) {
        fwrite(STDERR, "wordpress-wal-reader-checkpoint-restart-current-source-next145 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-reader-checkpoint-restart-current-source-next145 self-test passed\n";
}

return $summary;
