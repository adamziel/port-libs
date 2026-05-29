<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLitePagerHotJournalWalRecoveryPlan.php';
require_once __DIR__ . '/../src/SQLitePagerHotJournalWalSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalWalSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next124.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next124 clean schema before plugin import'),
    2 => $page('wp next124 clean wp_options before plugin import'),
    3 => $page('wp next124 clean active_plugins before savepoint'),
    4 => $page('wp next124 clean option_name index before savepoint'),
];
$dirtyDatabase = $page('wp next124 dirty schema after crashed import')
    . $page('wp next124 dirty wp_options after crashed import')
    . $page('wp next124 dirty active_plugins after crashed import')
    . $page('wp next124 dirty option_name index after crashed import');

$makeJournal = static function (array $pages) use ($sectorSize, $pageSize): string {
    $nonce = 0x12400001;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames) use ($page, $pageSize): string {
    $salt1 = 0x20260528;
    $salt2 = 0x12400002;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 124, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$journalBytes = $makeJournal($cleanPages);
$walBytes = $makeWal([
    [1, 0, 'wp next124 wal schema retained'],
    [2, 4, 'wp next124 wal wp_options retained commit'],
    [3, 0, 'wp next124 wal active_plugins savepoint draft'],
    [4, 4, 'wp next124 wal option index savepoint commit'],
    [2, 0, 'wp next124 wal uncommitted stale tail ignored'],
]);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4, true);

$plan = SQLitePagerHotJournalWalSavepointCurrentSourceNextPlan::plan(
    SQLiteRollbackJournal::parse($journalBytes, true),
    $dirtyDatabase,
    $journalBytes,
    $stack,
    'plugin-settings',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databasePath,
    [1, 2, 3, 4],
    $pageSize
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-hot-journal-wal-savepoint-current-source-next124');
    assert($plan['current_sources'] === ['wal', 'wal', 'database', 'database']);
    assert(str_contains($plan['rows'][2]['current_label'], 'clean active_plugins before savepoint'));
    assert(in_array('sqlite-pager-hot-journal-wal-savepoint-current-source-next124', $plan['dependencies'], true));
    echo "wordpress-pager-hot-journal-wal-savepoint-current-source-next124 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'database' => $plan['database_path'],
    'savepoint' => $plan['savepoint'],
    'current_sources' => $plan['current_sources'],
    'retained_frame_count' => $plan['retained_frame_count'],
    'discarded_frames' => $plan['savepoint_discarded_frame_count'],
], JSON_PRETTY_PRINT) . "\n";
