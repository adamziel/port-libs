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
$databasePath = '/srv/www/wp-content/database/wp-next196.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (string $bytes): string => hash('sha256', $bytes);
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

$preDatabase = $page('wp next196 dirty schema') . $page('wp next196 dirty options') . $page('wp next196 dirty plugin') . $page('wp next196 dirty cron') . $page('wp next196 clean usermeta');
$checkpointedDatabase = $page('wp next196 current schema') . $page('wp next196 current options') . $page('wp next196 dirty plugin') . $page('wp next196 current cron') . $page('wp next196 clean usermeta');
$currentWalBytes = $makeWal([
    [1, 0, 'wp next196 current schema'],
    [2, 5, 'wp next196 current options'],
    [4, 5, 'wp next196 current cron'],
], 196, 0x19600101, 0x19600102);
$nextWalBytes = $makeWal([[3, 0, 'wp next196 next plugin']], 197, 0x19700101, 0x19700102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next188Plan(
    $databasePath,
    $preDatabase,
    $pageSize,
    'plugin-import-next196',
    [2 => $page('wp next196 hot clean options')],
    [3 => $page('wp next196 before plugin')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next196 current schema'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5],
    [['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1]],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 96, 'root_pages' => [5], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
        ['name' => 'bootstrap-stale', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 96, 'root_pages' => [5], 'observed_checkpoint_sequence' => 195, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1959, 'observed_schema_cookie' => 96],
    ],
    [
        ['name' => 'bootstrap-reader-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
        ['name' => 'bootstrap-reader-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 195, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1959, 'observed_schema_cookie' => 96],
    ],
    96,
    97,
    1960,
    1961,
    null,
    null,
    'restart',
    3,
    196
);
$token = $bootstrap['current_source_token'];
$base188 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next188Plan(
    $databasePath,
    $preDatabase,
    $pageSize,
    'plugin-import-next196',
    [2 => $page('wp next196 hot clean options')],
    [3 => $page('wp next196 before plugin')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next196 current schema'), 'source_id' => $token['id'], 'epoch' => $token['epoch']]],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'base-current-reader', 'source_id' => $token['id'], 'epoch' => $token['epoch']],
        ['name' => 'base-stale-reader', 'source_id' => 'old-token', 'epoch' => $token['epoch']],
    ],
    [
        ['name' => 'select-current', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'schema_cookie' => 96, 'root_pages' => [5], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
        ['name' => 'select-stale-hook', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'schema_cookie' => 96, 'root_pages' => [5], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1959, 'observed_schema_cookie' => 96],
        ['name' => 'select-stale-generation', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'schema_cookie' => 96, 'root_pages' => [5], 'observed_checkpoint_sequence' => 195, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
        ['name' => 'select-hot-root', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'schema_cookie' => 96, 'root_pages' => [2], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
    ],
    [
        ['name' => 'reader-current', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
        ['name' => 'reader-stale-hook', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1959, 'observed_schema_cookie' => 96],
        ['name' => 'reader-stale-generation', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_checkpoint_sequence' => 195, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
        ['name' => 'reader-old-token', 'source_id' => 'old-token', 'epoch' => $token['epoch'], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
    ],
    96,
    97,
    1960,
    1961,
    $token,
    $bootstrap['next_source_token'],
    'restart',
    3,
    196
);
$base192 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next192Plan(
    $base188,
    $preDatabase,
    $checkpointedDatabase,
    $currentWal,
    [1, 2, 4],
    [
        ['name' => 'select-current-pages', 'root_pages' => [1, 2], 'observed_page_digests' => [1 => $digest($page('wp next196 current schema')), 2 => $digest($page('wp next196 current options'))]],
        ['name' => 'select-stale-page', 'root_pages' => [2], 'observed_page_digests' => [2 => $digest($page('wp next196 dirty options'))]],
    ],
    [
        ['name' => 'reader-current-pages', 'pinned_pages' => [1, 4], 'observed_page_digests' => [1 => $digest($page('wp next196 current schema')), 4 => $digest($page('wp next196 current cron'))]],
        ['name' => 'reader-stale-page', 'pinned_pages' => [2], 'observed_page_digests' => [2 => $digest($page('wp next196 dirty options'))]],
    ]
);
$restartWalBytes = (string) $currentWal->durableCheckpointResult($preDatabase, 'restart')['wal_bytes'];
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan(
    $base192,
    $currentWal,
    $currentWalBytes,
    $restartWalBytes,
    'restart',
    [
        ['name' => 'select-wp-options-restarted-sidecar', 'observed_wal_digest' => $digest($restartWalBytes)],
        ['name' => 'select-wp-options-old-sidecar', 'observed_wal_digest' => $digest($currentWalBytes)],
    ],
    [
        ['name' => 'reader-restarted-sidecar', 'observed_wal_digest' => $digest($restartWalBytes)],
        ['name' => 'reader-old-sidecar', 'observed_wal_digest' => $digest($currentWalBytes)],
    ]
);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next196',
    'applicationUse' => 'A copied Application import recovers a hot journal, rolls back a savepoint, checkpoints WAL pages into the database image, and admits wp_options readers only when the persisted WAL sidecar was restarted for the checkpoint generation.',
    'status' => $plan['status'],
    'sidecarReason' => $plan['sidecar']['reason'],
    'admittedStatements' => $plan['admitted_statement_names'],
    'reprepareStatements' => $plan['reprepare_statement_names'],
    'admittedReaders' => $plan['admitted_reader_names'],
    'reopenReaders' => $plan['reopen_reader_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next196'
    || $summary['sidecarReason'] !== 'wal_sidecar_restarted_after_checkpoint'
    || $summary['admittedStatements'] !== ['select-wp-options-restarted-sidecar']
    || $summary['reprepareStatements'] !== ['select-wp-options-old-sidecar']
    || $summary['admittedReaders'] !== ['reader-restarted-sidecar']
    || $summary['reopenReaders'] !== ['reader-old-sidecar']
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next196 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
