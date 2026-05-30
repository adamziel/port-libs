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
$databasePath = '/srv/www/wp-content/database/wp-next164.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = implode('', [
    $page('wp next164 dirty schema after import'),
    $page('wp next164 dirty wp_options root after import'),
    $page('wp next164 dirty active_plugins after import'),
    $page('wp next164 dirty autoload index after import'),
    $page('wp next164 dirty cron after import'),
]);
$hot = [
    2 => $page('wp next164 hot journal clean wp_options root'),
    4 => $page('wp next164 hot journal clean autoload index'),
];
$savepointBefore = [
    3 => $page('wp next164 savepoint before active_plugins retry'),
    5 => $page('wp next164 savepoint before cron retry'),
];

$makeWal = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
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

$currentWalBytes = $makeWal([
    [1, 0, 'wp next164 current wal schema draft'],
    [2, 5, 'wp next164 current wal wp_options commit'],
    [4, 0, 'wp next164 current wal autoload draft'],
    [5, 5, 'wp next164 current wal cron commit'],
], 164, 0x16400101, 0x16400102);
$nextWalBytes = $makeWal([
    [3, 0, 'wp next164 next wal active_plugins retry draft'],
    [5, 5, 'wp next164 next wal cron commit'],
], 165, 0x16500101, 0x16500102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerCacheCheckpointPlan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next164',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next164 current wal schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5],
    'restart',
    4,
    164
);
$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next164Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next164',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [
        1 => ['image' => $page('wp next164 current wal schema draft'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'label' => 'schema cache current'],
        2 => ['image' => $page('wp next164 current wal wp_options commit'), 'source_id' => 'old-token', 'epoch' => $currentToken['epoch'], 'label' => 'wp_options stale token'],
        3 => ['image' => $savepointBefore[3], 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'] - 1, 'label' => 'active_plugins stale epoch'],
        4 => ['image' => $page('wp next164 stale autoload cache'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'label' => 'autoload stale image'],
        5 => ['image' => $page('wp next164 current wal cron commit'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'dirty' => true, 'label' => 'cron dirty cache'],
    ],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'wp-current-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'wp-pinned-options', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'pinned' => true],
        ['name' => 'wp-next-reader', 'source_id' => $nextToken['id'], 'epoch' => $nextToken['epoch']],
        ['name' => 'wp-dirty-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'dirty' => true],
    ],
    'restart',
    4,
    164
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next164',
    'wordpressUse' => 'A copied WordPress plugin import recovers hot-journal pages, rolls back a failed option savepoint, publishes a checkpoint current-source token, and reopens readers that cannot safely keep their cached view.',
    'status' => $plan['status'],
    'admittedReaders' => $plan['admitted_reader_names'],
    'reopenReaders' => $plan['reopen_reader_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next164'
    || $summary['admittedReaders'] !== ['wp-current-schema']
    || $summary['reopenReaders'] !== ['wp-pinned-options', 'wp-next-reader', 'wp-dirty-reader']
) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next164 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
