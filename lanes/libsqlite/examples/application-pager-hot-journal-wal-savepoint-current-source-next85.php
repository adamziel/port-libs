<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointCheckpointPlan.php';
require_once __DIR__ . '/../src/SQLitePagerHotJournalWalRecoveryPlan.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalWalRecoveryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/wp.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = $page('dirty wp_options schema page after failed plugin import')
    . $page('dirty active_plugins page after failed plugin import')
    . $page('dirty transient page after failed plugin import');

$cleanPages = [
    1 => $page('clean wp_options schema page before failed plugin import'),
    2 => $page('clean active_plugins page before failed plugin import'),
    3 => $page('clean transient page before failed plugin import'),
];
$nonce = 0x85010001;
$journalBytes = str_pad(SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, 3, $sectorSize, $pageSize), $sectorSize, "\0");
foreach ($cleanPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}

$salt1 = 0x85850101;
$salt2 = 0x58580101;
$walPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 85, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($walPrefix, false);
$walBytes = $walPrefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'retained schema draft in WAL after hot journal'],
    [2, 3, 'retained active_plugins commit in WAL'],
    [2, 0, 'rolled back active_plugins draft in savepoint'],
    [3, 3, 'rolled back transient commit in savepoint'],
] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('application-import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordWalFrameWrite(3, 2);
$savepoints->recordWalFrameWrite(4, 3, true);

$plan = SQLitePagerHotJournalWalRecoveryPlan::savepointWalRecoveryCurrentSourceNext(
    SQLiteRollbackJournal::parse($journalBytes, true),
    $databaseBytes,
    $journalBytes,
    $walBytes,
    $databasePath,
    $savepoints,
    'plugin-settings',
    [1, 2, 3],
    'restart',
    $pageSize,
    false,
    true,
    true
);

$summary = [
    'status' => $plan['status'],
    'hotRecovered' => $plan['hot_recovered'],
    'journalAction' => $plan['journal_action'],
    'retainedWalFrames' => $plan['retained_frame_count'],
    'discardedSavepointWalFrames' => $plan['discarded_frame_count'],
    'beforeSources' => $plan['before_reader_sources'],
    'currentSources' => $plan['current_reader_sources'],
    'nextSources' => $plan['next_reader_sources'],
    'nextUsesCheckpointDatabase' => $plan['next_uses_checkpoint_database'],
    'currentMatchesNext' => $plan['current_to_next_images_match'],
    'dependency' => in_array('sqlite-pager-hot-journal-wal-savepoint-current-source-next85', $plan['dependencies'], true),
];

if (in_array('--self-test', $argv, true)) {
    foreach ([
        $summary['status'] === 'ready',
        $summary['hotRecovered'] === true,
        $summary['retainedWalFrames'] === 2,
        $summary['discardedSavepointWalFrames'] === 2,
        $summary['currentSources'] === ['wal', 'wal', 'database'],
        $summary['nextSources'] === ['database', 'database', 'database'],
        $summary['currentMatchesNext'] === true,
    ] as $passed) {
        if (!$passed) {
            throw new RuntimeException('Application pager hot-journal WAL savepoint current-source next85 smoke failed');
        }
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
