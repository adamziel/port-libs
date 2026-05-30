<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next172.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = [
    1 => $page('next172 dirty schema page after plugin import'),
    2 => $page('next172 dirty wp_options root page'),
    3 => $page('next172 dirty active_plugins payload'),
    4 => $page('next172 dirty autoload index page'),
    5 => $page('next172 dirty cron option page'),
    6 => $page('next172 dirty transient timeout page'),
];
$hot = [
    2 => $page('next172 hot journal clean wp_options root'),
    4 => $page('next172 hot journal clean autoload index'),
];
$before = [
    3 => $page('next172 savepoint before active_plugins retry'),
    5 => $page('next172 savepoint before cron retry'),
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
    [1, 0, 'next172 current wal schema draft'],
    [2, 6, 'next172 current wal wp_options commit'],
    [4, 0, 'next172 current wal autoload draft'],
    [5, 6, 'next172 current wal cron commit'],
    [6, 6, 'next172 current wal transient timeout commit'],
], 172, 0x17200101, 0x17200102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next172 next wal active_plugins retry draft'],
    [5, 6, 'next172 next wal cron commit'],
    [6, 6, 'next172 next wal transient timeout commit'],
], 173, 0x17300101, 0x17300102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$rolledBack = $database;
$rolledBack[2] = $hot[2];
$rolledBack[4] = $hot[4];
$rolledBack[3] = $before[3];
$rolledBack[5] = $before[5];
ksort($rolledBack, SORT_NUMERIC);
$rolledBackBytes = implode('', $rolledBack);
$sourceId = 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-inner-next172|restart|5|' . $currentWalBytes . '|' . $rolledBackBytes), 0, 24);
$cache = [
    1 => ['image' => $page('next172 current wal schema draft'), 'source_id' => $sourceId, 'epoch' => 173, 'label' => 'schema cache current'],
    2 => ['image' => $page('next172 current wal wp_options commit'), 'source_id' => 'old-source-token', 'epoch' => 173, 'label' => 'wp_options stale token'],
    3 => ['image' => $before[3], 'source_id' => $sourceId, 'epoch' => 172, 'label' => 'active_plugins stale epoch'],
    4 => ['image' => $page('next172 stale autoload cache image'), 'source_id' => $sourceId, 'epoch' => 173, 'label' => 'autoload stale image'],
    5 => ['image' => $page('next172 current wal cron commit'), 'source_id' => $sourceId, 'epoch' => 173, 'dirty' => true, 'label' => 'cron dirty failed savepoint'],
    6 => ['image' => $page('next172 current wal transient timeout commit'), 'source_id' => $sourceId, 'epoch' => 173, 'label' => 'transient timeout current'],
];
$checkpointPages = [1, 2, 3, 4, 5, 6];
$release = ['plugin-import-inner-next172' => [3, 5]];

$base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next166Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-inner-next172',
    'plugin-import-outer-next172',
    $hot,
    $before,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    $cache,
    $checkpointPages,
    $release,
    'restart',
    5,
    172,
);

$receipts = [];
foreach ($base['rows'] as $row) {
    $pageNumber = (int) $row['page_number'];
    $receipts[] = [
        'page_number' => $pageNumber,
        'image' => $page((string) $row['checkpoint_label']),
        'source_id' => $base['current_source_token']['id'],
        'epoch' => $base['current_source_token']['epoch'],
        'synced' => true,
        'label' => 'checkpoint write page ' . $pageNumber,
    ];
}

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next172Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-inner-next172',
    'plugin-import-outer-next172',
    $hot,
    $before,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    $cache,
    $checkpointPages,
    $release,
    $receipts,
    [
        'path' => $databasePath . '-wal',
        'source_id' => $base['next_source_token']['id'],
        'epoch' => $base['next_source_token']['epoch'],
        'wal_digest' => hash('sha256', $nextWalBytes),
        'synced' => true,
        'label' => 'next WAL sidecar sync',
    ],
    'restart',
    5,
    172,
);

$summary = [
    'status' => $plan['status'],
    'publish_ready' => $plan['publish_ready_next172'],
    'database_write_admitted' => $plan['database_write_admitted'],
    'wal_sidecar_admitted' => $plan['wal_sidecar_admitted'],
    'receipt_pages' => $plan['database_write_receipt_page_numbers'],
    'invalidated_cache_pages' => $plan['invalidated_cache_page_numbers'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-publish-next172') {
        throw new RuntimeException('Unexpected WAL hot-journal checkpoint publish status');
    }
    if ($summary['receipt_pages'] !== [1, 2, 3, 4, 5, 6]) {
        throw new RuntimeException('Unexpected checkpoint write receipt page set');
    }
    if ($summary['invalidated_cache_pages'] !== [2, 3, 4, 5]) {
        throw new RuntimeException('Unexpected invalidated reader cache pages');
    }

    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next172 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
