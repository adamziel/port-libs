<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalAppendPlan.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointCheckpointPlan.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next153.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('next153 recovered sqlite header'),
    2 => $page('next153 recovered wp_options root'),
    3 => $page('next153 recovered active_plugins row'),
    4 => $page('next153 recovered autoload index'),
    5 => $page('next153 recovered transient row'),
    6 => $page('next153 recovered rewrite rules'),
];
$dirtyDatabase = $page('next153 dirty sqlite header')
    . $page('next153 dirty wp_options root')
    . $page('next153 dirty active_plugins row')
    . $page('next153 dirty autoload index')
    . $page('next153 dirty transient row')
    . $page('next153 dirty rewrite rules');

$nonce = 0x15315301;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
}

$salt1 = 0x15315301;
$salt2 = 0x15315302;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 153, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, 'next153 retained wp_options draft'],
    [3, 6, 'next153 retained active_plugins commit'],
    [4, 0, 'next153 savepoint autoload draft'],
    [5, 6, 'next153 savepoint transient commit'],
    [2, 6, 'next153 savepoint stale wp_options commit'],
    [6, 6, 'next153 savepoint rewrite commit'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import-next153');
$savepoints->recordWalFrameWrite(1, 2);
$savepoints->recordWalFrameWrite(2, 3, true);
$savepoints->savepoint('plugin-settings-next153');
$savepoints->recordWalFrameWrite(3, 4);
$savepoints->recordWalFrameWrite(4, 5, true);
$savepoints->recordWalFrameWrite(5, 2, true);
$savepoints->recordWalFrameWrite(6, 6, true);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next153Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $savepoints,
    'plugin-settings-next153',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    [1, 2, 3, 4, 5, 6],
    [[
        'pages' => [
            2 => $page('next153 next wp_options retry'),
            5 => $page('next153 next transient retry'),
            6 => $page('next153 next rewrite retry'),
        ],
        'database_page_count' => 6,
    ]],
    6,
    'restart'
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next153',
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'discardedFrames' => $plan['discarded_frame_count'],
    'currentCheckpointBusy' => $plan['current_checkpoint_busy'],
    'releasedWalAction' => $plan['released_wal_action'],
    'nextAppendFrameCount' => $plan['next_append_frame_count'],
    'currentSources' => $plan['current_sources'],
    'nextSources' => $plan['next_sources'],
    'wordpressUse' => 'A copied wp_options import can recover a hot rollback journal, roll back the failed plugin-setting savepoint to the current WAL prefix, keep a still-open reader pinned, and only restart the checkpoint generation after that reader is released.',
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next153'
        || $summary['discardedFrames'] !== 4
        || $summary['currentCheckpointBusy'] !== true
        || $summary['releasedWalAction'] !== 'restart_wal'
        || $summary['nextAppendFrameCount'] !== 3
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next153 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next153 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
