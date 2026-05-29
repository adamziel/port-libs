<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = 'wp-content/database/wp-next173.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp next173 dirty schema') . $page('wp next173 dirty option root') . $page('wp next173 dirty plugin option');
$journalBytes = 'wp-next173-hot-journal:' . $page('wp next173 clean option root');

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
    [1, 0, 'wp next173 retained schema'],
    [2, 3, 'wp next173 retained active_plugins'],
    [3, 3, 'wp next173 retained autoload'],
], 173, 0x17310101, 0x17310102);
$nextWalBytes = $makeWalBytes([
    [2, 0, 'wp next173 retry active_plugins draft'],
    [3, 3, 'wp next173 retry autoload commit'],
], 174, 0x17410101, 0x17410102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next167Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'wp-import-next173',
    [2 => $page('wp next173 hot journal clean root')],
    [3 => $page('wp next173 savepoint before plugin retry')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next173 retained schema'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3],
    [
        ['name' => 'current-reader', 'source_id' => 'bootstrap', 'epoch' => 1],
        ['name' => 'stale-reader', 'source_id' => 'old-bootstrap', 'epoch' => 1],
    ],
    null,
    null,
    null,
    'restart',
    3,
    173
);
$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];
$prepared = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next167Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'wp-import-next173',
    [2 => $page('wp next173 hot journal clean root')],
    [3 => $page('wp next173 savepoint before plugin retry')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next173 retained schema'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]],
    [1, 2, 3],
    [
        ['name' => 'current-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'reopened-reader', 'source_id' => 'old-bootstrap', 'epoch' => 1],
    ],
    $currentToken,
    $nextToken,
    null,
    'restart',
    3,
    173
);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next173Plan(
    $prepared,
    $databaseBytes,
    $journalBytes,
    $currentWalBytes,
    hash('sha256', $databaseBytes),
    hash('sha256', $journalBytes),
    hash('sha256', $currentWalBytes)
);
$stale = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next173Plan(
    $prepared,
    $databaseBytes,
    $journalBytes,
    $currentWalBytes . 'stale',
    hash('sha256', $databaseBytes),
    hash('sha256', $journalBytes),
    hash('sha256', $currentWalBytes)
);

echo json_encode([
    'status' => $plan['status'],
    'canPublish' => $plan['can_publish'],
    'operationNames' => $plan['operation_names'],
    'durableOperations' => $plan['durable_operation_count'],
    'staleWalBlocked' => $stale['stale_source_names'] === ['wal'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT) . PHP_EOL;
