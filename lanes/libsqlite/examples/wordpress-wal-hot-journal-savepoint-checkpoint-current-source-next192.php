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
$databasePath = '/srv/www/wp-content/database/wp-next192.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (string $image): string => hash('sha256', $image);
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

$preDatabase = $page('wp next192 dirty schema')
    . $page('wp next192 dirty options')
    . $page('wp next192 dirty plugin')
    . $page('wp next192 dirty cron')
    . $page('wp next192 clean usermeta');
$checkpointedDatabase = $page('wp next192 current schema')
    . $page('wp next192 current options')
    . $page('wp next192 dirty plugin')
    . $page('wp next192 current cron')
    . $page('wp next192 clean usermeta');
$currentWalBytes = $makeWal([
    [1, 0, 'wp next192 current schema'],
    [2, 5, 'wp next192 current options'],
    [4, 5, 'wp next192 current cron'],
], 192, 0x19200101, 0x19200102);
$nextWalBytes = $makeWal([
    [3, 0, 'wp next192 next plugin'],
    [4, 5, 'wp next192 next cron'],
], 193, 0x19300101, 0x19300102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next188Plan(
    $databasePath,
    $preDatabase,
    $pageSize,
    'plugin-import-next192',
    [2 => $page('wp next192 hot clean options')],
    [3 => $page('wp next192 before plugin')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next192 current schema'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5],
    [['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1]],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 92, 'root_pages' => [5], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
        ['name' => 'bootstrap-stale', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 92, 'root_pages' => [5], 'observed_checkpoint_sequence' => 191, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 919, 'observed_schema_cookie' => 92],
    ],
    [
        ['name' => 'bootstrap-reader-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
        ['name' => 'bootstrap-reader-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 191, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 919, 'observed_schema_cookie' => 92],
    ],
    92,
    93,
    920,
    921,
    null,
    null,
    'restart',
    3,
    192
);
$token = $bootstrap['current_source_token'];
$base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next188Plan(
    $databasePath,
    $preDatabase,
    $pageSize,
    'plugin-import-next192',
    [2 => $page('wp next192 hot clean options')],
    [3 => $page('wp next192 before plugin')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next192 current schema'), 'source_id' => $token['id'], 'epoch' => $token['epoch']]],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'base-current-reader', 'source_id' => $token['id'], 'epoch' => $token['epoch']],
        ['name' => 'base-stale-reader', 'source_id' => 'old-token', 'epoch' => $token['epoch']],
    ],
    [
        ['name' => 'select-current', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'schema_cookie' => 92, 'root_pages' => [5], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
        ['name' => 'select-stale-hook', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'schema_cookie' => 92, 'root_pages' => [5], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 919, 'observed_schema_cookie' => 92],
        ['name' => 'select-stale-generation', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'schema_cookie' => 92, 'root_pages' => [5], 'observed_checkpoint_sequence' => 191, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
        ['name' => 'select-hot-root', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'schema_cookie' => 92, 'root_pages' => [2], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
    ],
    [
        ['name' => 'reader-current', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
        ['name' => 'reader-stale-hook', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 919, 'observed_schema_cookie' => 92],
        ['name' => 'reader-stale-generation', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_checkpoint_sequence' => 191, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
        ['name' => 'reader-stale-token', 'source_id' => 'old-token', 'epoch' => $token['epoch'], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
    ],
    92,
    93,
    920,
    921,
    $token,
    $bootstrap['next_source_token'],
    'restart',
    3,
    192
);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next192Plan(
    $base,
    $preDatabase,
    $checkpointedDatabase,
    $currentWal,
    [1, 2, 4],
    [
        ['name' => 'select-wp-options-current-pages', 'root_pages' => [1, 2], 'observed_page_digests' => [1 => $digest($page('wp next192 current schema')), 2 => $digest($page('wp next192 current options'))]],
        ['name' => 'select-wp-options-stale-page', 'root_pages' => [2], 'observed_page_digests' => [2 => $digest($page('wp next192 dirty options'))]],
    ],
    [
        ['name' => 'reader-current-pages', 'pinned_pages' => [1, 4], 'observed_page_digests' => [1 => $digest($page('wp next192 current schema')), 4 => $digest($page('wp next192 current cron'))]],
        ['name' => 'reader-stale-options', 'pinned_pages' => [2], 'observed_page_digests' => [2 => $digest($page('wp next192 dirty options'))]],
    ]
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next192',
    'wordpressUse' => 'A copied WordPress import recovers a hot journal, rolls back a savepoint, checkpoints WAL pages into the database image, and reuses only prepared readers whose observed page digests match the materialized checkpoint pages.',
    'status' => $plan['status'],
    'checkpointPages' => $plan['checkpoint_pages'],
    'admittedStatements' => $plan['admitted_statement_names'],
    'reprepareStatements' => $plan['reprepare_statement_names'],
    'admittedReaders' => $plan['admitted_reader_names'],
    'reopenReaders' => $plan['reopen_reader_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next192'
    || $summary['admittedStatements'] !== ['select-wp-options-current-pages']
    || $summary['reprepareStatements'] !== ['select-wp-options-stale-page']
    || $summary['admittedReaders'] !== ['reader-current-pages']
    || $summary['reopenReaders'] !== ['reader-stale-options']
) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next192 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
