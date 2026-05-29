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
$databasePath = '/srv/www/wp-content/database/wp-next188.sqlite';
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
    [1, 0, 'wp next188 current schema'],
    [2, 5, 'wp next188 current wp_options'],
    [4, 5, 'wp next188 current cron'],
], 188, 0x18800101, 0x18800102);
$nextWalBytes = $makeWal([
    [3, 0, 'wp next188 next active_plugins'],
    [4, 5, 'wp next188 next cron'],
], 189, 0x18900101, 0x18900102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];
$nextSalt = [$nextWal->header->salt1, $nextWal->header->salt2];

$databaseBytes = $page('wp next188 dirty schema')
    . $page('wp next188 dirty options')
    . $page('wp next188 dirty plugin')
    . $page('wp next188 dirty cron')
    . $page('wp next188 usermeta');

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next188Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next188',
    [2 => $page('wp next188 hot clean options')],
    [3 => $page('wp next188 before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next188 current schema'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5],
    [['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1]],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
        ['name' => 'bootstrap-stale', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 187, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 699, 'observed_schema_cookie' => 44],
    ],
    [
        ['name' => 'bootstrap-reader-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
        ['name' => 'bootstrap-reader-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 187, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 699, 'observed_schema_cookie' => 44],
    ],
    44,
    45,
    700,
    701,
    null,
    null,
    'restart',
    3,
    188
);
$currentToken = $bootstrap['current_source_token'];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next188Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next188',
    [2 => $page('wp next188 hot clean options')],
    [3 => $page('wp next188 before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next188 current schema'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'base-current-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'base-stale-reader', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ],
    [
        ['name' => 'select-usermeta-current-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
        ['name' => 'select-options-stale-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 699, 'observed_schema_cookie' => 44],
        ['name' => 'select-next-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 701, 'observed_schema_cookie' => 45],
        ['name' => 'select-stale-generation', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 187, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
    ],
    [
        ['name' => 'reader-current-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
        ['name' => 'reader-stale-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 699, 'observed_schema_cookie' => 44],
        ['name' => 'reader-stale-generation', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 187, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
    ],
    44,
    45,
    700,
    701,
    $currentToken,
    $bootstrap['next_source_token'],
    'restart',
    3,
    188
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next188',
    'wordpressUse' => 'A copied WordPress import recovers a hot journal and rolls back a savepoint, then keeps only prepared wp_usermeta readers whose WAL generation, schema cookie, and commit-hook counter still match the current source.',
    'status' => $plan['status'],
    'admittedStatements' => $plan['admitted_statement_names'],
    'reprepareStatements' => $plan['reprepare_statement_names'],
    'admittedReaders' => $plan['admitted_reader_names'],
    'reopenReaders' => $plan['reopen_reader_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next188'
    || $summary['admittedStatements'] !== ['select-usermeta-current-hook']
    || $summary['reprepareStatements'] !== ['select-options-stale-hook', 'select-next-hook', 'select-stale-generation']
    || $summary['admittedReaders'] !== ['reader-current-hook']
    || $summary['reopenReaders'] !== ['reader-stale-hook', 'reader-stale-generation']
) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next188 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
