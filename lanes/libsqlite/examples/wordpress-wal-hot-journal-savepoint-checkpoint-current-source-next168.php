<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next168.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next168 dirty schema')
    . $page('next168 dirty wp_options')
    . $page('next168 dirty active_plugins')
    . $page('next168 dirty autoload');
$hot = [2 => $page('next168 hot journal clean wp_options')];
$savepointBefore = [3 => $page('next168 savepoint before active_plugins retry')];

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
    [1, 0, 'next168 current schema draft'],
    [2, 4, 'next168 current wp_options commit'],
], 168, 0x16810001, 0x16810002);
$nextWalBytes = $makeWalBytes([
    [3, 4, 'next168 next active_plugins retry'],
], 169, 0x16910001, 0x16910002);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next161Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'wp-plugin-import-next168',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next168 current schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3],
    'restart',
    2,
    168
);

$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next168Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'wp-plugin-import-next168',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [
        1 => ['image' => $page('next168 current schema draft'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        2 => ['image' => $page('next168 current wp_options commit'), 'source_id' => 'old-source', 'epoch' => $currentToken['epoch']],
    ],
    [1, 2, 3],
    [
        ['name' => 'wp-current-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'wp-reopened-reader', 'source_id' => $nextToken['id'], 'epoch' => $nextToken['epoch']],
    ],
    'restart',
    2,
    168
);

echo json_encode([
    'status' => $plan['status'],
    'delete_hot_journal_allowed' => $plan['delete_hot_journal_allowed'],
    'preserve_wal_for_readers' => $plan['preserve_wal_for_readers'],
    'reader_reopen_names' => $plan['reader_reopen_names'],
    'operation_names' => $plan['operation_names'],
], JSON_PRETTY_PRINT) . PHP_EOL;
