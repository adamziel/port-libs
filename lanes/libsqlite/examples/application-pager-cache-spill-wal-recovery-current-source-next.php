<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLitePagerDirtyPageCacheSpillPlan.php';
require_once __DIR__ . '/../src/SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next135.sqlite';
$salt1 = 0x13572468;
$salt2 = 0x24681357;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x02";
$firstPage[19] = "\x02";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 5), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$databaseBytes = $firstPage
    . $page('next135 db wp_options root before wal')
    . $page('next135 db active_plugins before wal')
    . $page('next135 db autoload index before wal')
    . $page('next135 db transient row before wal');

$walHeaderPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 135, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($walHeaderPrefix, false);
$walBytes = $walHeaderPrefix . pack('N*', $seed[0], $seed[1]);
$append = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $append($walBytes, $seed, 2, 0, $page('next135 wal committed wp_options root'));
$walBytes = $append($walBytes, $seed, 3, 5, $page('next135 wal committed active_plugins'));
$walBytes = $append($walBytes, $seed, 5, 0, $page('next135 wal uncommitted transient tail'));

$plan = SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan::plan(
    $databasePath,
    $databaseBytes,
    $walBytes,
    [
        ['page' => 2, 'image' => $page('next135 cache retry wp_options root')],
        ['page' => 3, 'image' => $page('next135 cache retry active_plugins')],
    ],
    6,
    3,
    2,
    2
);

$summary = [
    'scenario' => 'application-pager-cache-spill-wal-recovery-current-source-next135',
    'status' => $plan['status'],
    'recoveryReason' => $plan['recovery']['reason'],
    'spilledPages' => $plan['spilled_page_numbers'],
    'walResetBlocked' => $plan['wal_reset_blocked'],
    'checkpointHasCommittedOptionRoot' => str_contains($plan['checkpoint_database_bytes'], 'next135 wal committed wp_options root'),
    'checkpointExcludesUncommittedTail' => !str_contains($plan['checkpoint_database_bytes'], 'next135 wal uncommitted transient tail'),
    'applicationUse' => 'Copied wp_options retry imports in WAL mode must recover only the committed WAL prefix before cache-spill frames are appended, while reader-pinned or corrupt tails prevent premature WAL reset.',
    'dependencyClosure' => 'no new support component needed; this composes native WAL transaction recovery with the existing WAL-mode pager cache-spill planner',
];

if ($summary['status'] !== 'pager_cache_spill_wal_recovery_current_source_next135'
    || $summary['spilledPages'] !== [2, 3]
    || !$summary['checkpointHasCommittedOptionRoot']
    || !$summary['checkpointExcludesUncommittedTail']
) {
    fwrite(STDERR, "application-pager-cache-spill-wal-recovery-current-source-next135 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
