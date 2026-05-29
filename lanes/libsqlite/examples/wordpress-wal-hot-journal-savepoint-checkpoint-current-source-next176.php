<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next176.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = [
    1 => $page('wp next176 dirty schema page'),
    2 => $page('wp next176 dirty options root'),
    3 => $page('wp next176 dirty active_plugins'),
    4 => $page('wp next176 dirty autoload index'),
];
$hot = [
    2 => $page('wp next176 hot clean options root'),
    4 => $page('wp next176 hot clean autoload index'),
];
$before = [
    3 => $page('wp next176 savepoint before active_plugins'),
];
$databaseBytes = implode('', $database);

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
    [1, 0, 'wp next176 current schema'],
    [2, 4, 'wp next176 current options commit'],
    [4, 4, 'wp next176 current autoload commit'],
], 176, 0x17610101, 0x17610102);
$nextWalBytes = $makeWalBytes([
    [3, 4, 'wp next176 next active_plugins retry'],
], 177, 0x17710101, 0x17710102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$rolledBack = $database;
$rolledBack[2] = $hot[2];
$rolledBack[4] = $hot[4];
$rolledBack[3] = $before[3];
ksort($rolledBack, SORT_NUMERIC);
$sourceId = 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-inner-next176|restart|3|' . $currentWalBytes . '|' . implode('', $rolledBack)), 0, 24);
$cache = [
    1 => ['image' => $page('wp next176 current schema'), 'source_id' => $sourceId, 'epoch' => 177],
    2 => ['image' => $page('wp next176 stale options'), 'source_id' => 'old-source', 'epoch' => 177],
    3 => ['image' => $before[3], 'source_id' => $sourceId, 'epoch' => 176],
    4 => ['image' => $page('wp next176 current autoload commit'), 'source_id' => $sourceId, 'epoch' => 177],
];
$release = ['plugin-import-inner-next176' => [3]];

$base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next166Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-inner-next176',
    'plugin-import-outer-next176',
    $hot,
    $before,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    $cache,
    [1, 2, 3, 4],
    $release,
    'restart',
    3,
    176,
);

$receipts = [];
foreach ($base['rows'] as $row) {
    $receipts[] = [
        'page_number' => (int) $row['page_number'],
        'image' => $page((string) $row['checkpoint_label']),
        'source_id' => $base['current_source_token']['id'],
        'epoch' => $base['current_source_token']['epoch'],
        'synced' => true,
    ];
}

$journalDigest = (static function (array $pages): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
})($hot);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next176Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-inner-next176',
    'plugin-import-outer-next176',
    $hot,
    $before,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    $cache,
    [1, 2, 3, 4],
    $release,
    $receipts,
    [
        'path' => $databasePath . '-wal',
        'source_id' => $base['next_source_token']['id'],
        'epoch' => $base['next_source_token']['epoch'],
        'wal_digest' => hash('sha256', $nextWalBytes),
        'synced' => true,
    ],
    [
        'path' => $databasePath . '-journal',
        'journal_digest' => $journalDigest,
        'source_id' => $base['current_source_token']['id'],
        'epoch' => $base['current_source_token']['epoch'],
        'deleted' => true,
        'synced' => true,
    ],
    [
        [
            'reader_id' => 'wp-admin-options-reader',
            'source_id' => $base['next_source_token']['id'],
            'epoch' => $base['next_source_token']['epoch'],
            'wal_digest' => hash('sha256', $nextWalBytes),
            'hot_journal_digest' => null,
            'savepoint_closed' => true,
            'reopened' => true,
        ],
    ],
    'restart',
    3,
    176,
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-reader-admit-next176') {
        throw new RuntimeException('unexpected next176 status');
    }
    if ($plan['blocked_reader_ids_next176'] !== []) {
        throw new RuntimeException('reader reopen should be admitted');
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next176 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'hot_journal_deleted' => $plan['hot_journal_delete_admitted_next176'],
    'reader_reopen_admitted' => $plan['reader_reopen_admitted_next176'],
    'blocked_reader_ids' => $plan['blocked_reader_ids_next176'],
    'retained_cache_pages' => $plan['retained_cache_page_numbers'],
], JSON_PRETTY_PRINT) . "\n";
