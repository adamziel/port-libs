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
$databasePath = '/srv/www/wp-content/database/wp-next185.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
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
    [1, 0, 'wp next185 current schema'],
    [2, 5, 'wp next185 current wp_options'],
    [4, 5, 'wp next185 current cron'],
], 185, 0x18500101, 0x18500102);
$nextWalBytes = $makeWal([
    [3, 0, 'wp next185 next active_plugins'],
    [4, 5, 'wp next185 next cron'],
], 186, 0x18600101, 0x18600102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];
$nextSalt = [$nextWal->header->salt1, $nextWal->header->salt2];

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next185Plan(
    $databasePath,
    $page('wp next185 dirty schema') . $page('wp next185 dirty options') . $page('wp next185 dirty plugin') . $page('wp next185 dirty cron') . $page('wp next185 usermeta'),
    $pageSize,
    'plugin-import-next185',
    [2 => $page('wp next185 hot clean options')],
    [3 => $page('wp next185 before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next185 current schema'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5],
    [['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1]],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt],
        ['name' => 'bootstrap-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1, 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 184, 'observed_salt' => $currentSalt],
    ],
    [
        ['name' => 'bootstrap-reader-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt],
        ['name' => 'bootstrap-reader-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 184, 'observed_salt' => $currentSalt],
    ],
    44,
    45,
    null,
    null,
    'restart',
    3,
    185
);
$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next185Plan(
    $databasePath,
    $page('wp next185 dirty schema') . $page('wp next185 dirty options') . $page('wp next185 dirty plugin') . $page('wp next185 dirty cron') . $page('wp next185 usermeta'),
    $pageSize,
    'plugin-import-next185',
    [2 => $page('wp next185 hot clean options')],
    [3 => $page('wp next185 before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next185 current schema'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'base-current-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'base-stale-reader', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ],
    [
        ['name' => 'select-usermeta-current-generation', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt],
        ['name' => 'select-options-stale-sequence', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 184, 'observed_salt' => $currentSalt],
        ['name' => 'select-next-generation', 'source_id' => $nextToken['id'], 'epoch' => $nextToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 186, 'observed_salt' => $nextSalt],
    ],
    [
        ['name' => 'reader-current-generation', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt],
        ['name' => 'reader-stale-generation', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 184, 'observed_salt' => $currentSalt],
    ],
    44,
    45,
    $currentToken,
    $nextToken,
    'restart',
    3,
    185
);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next185',
    'applicationUse' => 'A copied Application import recovers a hot journal and rolls back a savepoint, then admits only prepared wp_options/usermeta readers that observed the current WAL checkpoint sequence and salt.',
    'status' => $plan['status'],
    'admittedStatements' => $plan['admitted_statement_names'],
    'reprepareStatements' => $plan['reprepare_statement_names'],
    'admittedReaders' => $plan['admitted_reader_names'],
    'reopenReaders' => $plan['reopen_reader_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next185'
    || $summary['admittedStatements'] !== ['select-usermeta-current-generation']
    || $summary['reprepareStatements'] !== ['select-options-stale-sequence', 'select-next-generation']
    || $summary['admittedReaders'] !== ['reader-current-generation']
    || $summary['reopenReaders'] !== ['reader-stale-generation']
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next185 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
