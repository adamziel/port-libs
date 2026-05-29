<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$makeJournal = static function (array $pages) use ($pageSize, $sectorSize): string {
    $nonce = 0x12200001;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};
$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x12212201;
    $salt2 = 0x12212202;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 122, $salt1, $salt2);
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

$cleanPages = [
    1 => $page('wp clean schema before interrupted import'),
    2 => $page('wp clean siteurl before interrupted import'),
    3 => $page('wp clean autoload index before interrupted import'),
    4 => $page('wp clean transients before interrupted import'),
];
$databaseBytes = $page('wp dirty schema from interrupted import')
    . $page('wp dirty siteurl from interrupted import')
    . $page('wp dirty autoload index from interrupted import')
    . $page('wp dirty transients from interrupted import');
$journalBytes = $makeJournal($cleanPages);
$walBytes = $makeWal([
    [2, 4, 'wp wal committed siteurl after hot recovery'],
    [3, 0, 'wp wal draft plugin autoload'],
    [4, 4, 'wp wal committed transient cleanup'],
    [2, 4, 'wp wal committed option retry tail'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$plan = SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::next122Plan(
    $databasePath,
    $databaseBytes,
    $journalBytes,
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    'restart',
    2
);

$summary = [
    'scenario' => 'wordpress-wal-checkpoint-hot-journal-reader-current-source-next122',
    'wordpressUse' => 'A copied WordPress SQLite database starts with a hot rollback journal from an interrupted import, then a WAL reader pins the recovered current source while checkpoint restart is attempted.',
    'status' => $plan['status'],
    'hotRecovered' => $plan['hot_recovered'],
    'readerEndFrame' => $plan['reader_end_frame'],
    'hotRestoredPages' => $plan['hot_restored_page_numbers'],
    'readerSources' => $plan['reader_sources'],
    'pinnedCheckpointBusy' => $plan['pinned_checkpoint_busy'],
    'releasedWalAction' => $plan['released_wal_action'],
    'readerUsesHotCurrentSource' => $plan['reader_uses_hot_current_source'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'wal-checkpoint-hot-journal-reader-current-source-next122');
    assert($summary['hotRecovered'] === true);
    assert($summary['readerSources'] === ['database', 'wal', 'database', 'database']);
    assert($summary['pinnedCheckpointBusy'] === true);
    assert($summary['releasedWalAction'] === 'restart_wal');
    echo "wordpress-wal-checkpoint-hot-journal-reader-current-source-next122 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
