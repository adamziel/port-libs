<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite-next138';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $initialPageCount, int $nonce = 0x13800001) use ($pageSize, $sectorSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};
$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x13813801;
    $salt2 = 0x13813802;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 138, $salt1, $salt2);
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

$clean = [
    1 => $page('next138 clean schema before interrupted option import'),
    2 => $page('next138 clean wp_options before interrupted option import'),
    3 => $page('next138 clean autoload index before interrupted option import'),
    4 => $page('next138 clean transient page before interrupted option import'),
    5 => $page('next138 clean plugin page before interrupted option import'),
];
$dirtyDatabaseBytes = $page('next138 dirty schema from failed option import')
    . $page('next138 dirty wp_options from failed option import')
    . $page('next138 dirty autoload index from failed option import')
    . $page('next138 dirty transient page from failed option import')
    . $page('next138 dirty plugin page from failed option import');
$journalBytes = $makeJournal($clean, 5);
$walBytes = $makeWal([
    [1, 0, 'next138 retained schema wal draft'],
    [2, 5, 'next138 retained siteurl wal commit'],
    [3, 0, 'next138 discarded autoload wal draft'],
    [4, 5, 'next138 discarded transient wal commit'],
    [2, 5, 'next138 discarded option retry wal tail'],
    [5, 5, 'next138 discarded plugin wal tail'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next138');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-batch-next138');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4, true);
$stack->recordWalFrameWrite(5, 2, true);
$stack->recordWalFrameWrite(6, 5, true);

$plan = SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan(
    $databasePath,
    $dirtyDatabaseBytes,
    $journalBytes,
    $stack,
    'plugin-batch-next138',
    $wal,
    $walBytes,
    [1, 2, 3, 4, 5],
    2
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-checkpoint-hot-journal-truncate-current-source-next138');
    assert($plan['hot_recovered'] === true);
    assert($plan['reader_release_unblocked_truncate'] === true);
    assert($plan['released_wal_action'] === 'truncate_wal');
    assert($plan['current_sources'] === ['wal', 'wal', 'database', 'database', 'database']);
    assert($plan['released_next_sources'] === ['database', 'database', 'database', 'database', 'database']);
    echo "wordpress-wal-checkpoint-hot-journal-truncate-current-source-next138 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-wal-checkpoint-hot-journal-truncate-current-source-next138',
    'wordpressUse' => 'Recover an interrupted copied wp_options rollback journal before applying a savepoint-truncated WAL checkpoint, so the follow-up TRUNCATE checkpoint writes clean recovered database pages and removes the WAL sidecar only after the pinned reader drains.',
    'status' => $plan['status'],
    'hotRecovered' => $plan['hot_recovered'],
    'retainedFrames' => $plan['retained_frame_count'],
    'discardedFrames' => $plan['discarded_frame_count'],
    'pinnedBusy' => $plan['pinned_checkpoint_busy'],
    'releasedWalAction' => $plan['released_wal_action'],
    'currentSources' => $plan['current_sources'],
    'releasedSources' => $plan['released_next_sources'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
