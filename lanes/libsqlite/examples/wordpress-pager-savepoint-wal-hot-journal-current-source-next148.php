<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLitePagerSavepointHotJournalCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next148.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirty = [
    1 => $page('wp next148 dirty sqlite header'),
    2 => $page('wp next148 dirty wp_options root'),
    3 => $page('wp next148 dirty active_plugins'),
    4 => $page('wp next148 dirty autoload index'),
];
$clean = [
    2 => $page('wp next148 clean wp_options root'),
    4 => $page('wp next148 clean autoload index'),
];
$databaseBytes = implode('', $dirty);

$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
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

$currentWalBytes = $makeWalBytes([
    [2, 4, 'wp next148 current reader wp_options commit'],
    [3, 0, 'wp next148 current reader active_plugins draft'],
], 148, 0x14814801, 0x14814802);
$nextWalBytes = $makeWalBytes([
    [2, 0, 'wp next148 next retry wp_options draft'],
    [4, 4, 'wp next148 next retry autoload commit'],
], 149, 0x14914901, 0x14914902);

$plan = SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan::plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'wp_plugin_batch_next148',
    $clean,
    [2 => $dirty[2], 3 => $dirty[3], 4 => $dirty[4]],
    [2 => $page('wp next148 current savepoint wp_options')],
    [3 => $page('wp next148 next savepoint active_plugins')],
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    SQLiteWal::parse($nextWalBytes, $pageSize, true),
    $nextWalBytes,
    [2, 3, 4],
    2,
    148,
    false,
    true,
    true,
);

$summary = [
    'scenario' => 'wordpress-pager-savepoint-wal-hot-journal-current-source-next148',
    'wordpressUse' => 'Copied wp_options imports can recover a hot rollback journal before retrying a savepoint while an existing WAL reader remains pinned to the recovered current source and later savepoint writes use a distinct WAL generation.',
    'status' => $plan['status'],
    'readerEndFrame' => $plan['reader_end_frame'],
    'currentSources' => $plan['current_sources'],
    'retrySources' => $plan['retry_sources'],
    'nextSources' => $plan['next_sources'],
    'separatedPages' => $plan['next_separated_page_numbers'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    assert($summary['status'] === 'pager-savepoint-wal-hot-journal-current-source-next148');
    assert($summary['currentSources'] === ['wal', 'wal', 'database']);
    assert($summary['retrySources'] === ['wal', 'wal', 'database']);
    assert($summary['nextSources'] === ['wal', 'database', 'wal']);
    echo "wordpress-pager-savepoint-wal-hot-journal-current-source-next148 self-test passed\n";
}

return $summary;
