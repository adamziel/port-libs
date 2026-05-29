<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$databasePath = '/srv/www/wp-content/database/wp-next195.sqlite';
$token = ['id' => 'wordpress-next195-current-source', 'epoch' => 195];
$checkpoint = [
    'database_path' => $databasePath,
    'wal_path' => $databasePath . '-wal',
    'journal_path' => $databasePath . '-journal',
    'current_source_token' => $token,
    'checkpoint_cookie' => 19501,
    'schema_cookie' => 44,
    'wal_salt' => 'wordpress-next195-wal-salt',
    'hot_journal_generation' => 3,
    'savepoint_generation' => 9,
    'journal_removed' => true,
    'checkpoint_published' => true,
    'operation_names' => ['publish_hot_journal_checkpoint_current_source_next188'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next188'],
];
$readers = [
    ['name' => 'wp-options-current-reader', 'page' => 2, 'source_id' => $token['id'], 'epoch' => 195, 'observed_checkpoint_cookie' => 19501, 'observed_schema_cookie' => 44, 'observed_wal_salt' => 'wordpress-next195-wal-salt', 'observed_hot_journal_generation' => 3, 'observed_savepoint_generation' => 9],
    ['name' => 'wp-options-retry-before-hot-journal-delete', 'page' => 3, 'source_id' => $token['id'], 'epoch' => 195, 'observed_checkpoint_cookie' => 19501, 'observed_schema_cookie' => 44, 'observed_wal_salt' => 'wordpress-next195-wal-salt', 'observed_hot_journal_generation' => 2, 'observed_savepoint_generation' => 9],
    ['name' => 'wp-options-retry-before-savepoint', 'page' => 4, 'source_id' => $token['id'], 'epoch' => 195, 'observed_checkpoint_cookie' => 19501, 'observed_schema_cookie' => 44, 'observed_wal_salt' => 'wordpress-next195-wal-salt', 'observed_hot_journal_generation' => 3, 'observed_savepoint_generation' => 8],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next195Plan($checkpoint, $readers);
$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next195',
    'status' => $plan['status'],
    'admittedReaders' => $plan['admitted_reader_names'],
    'reopenReaders' => $plan['reopen_reader_names'],
    'readerDigest' => $plan['reader_digest'],
    'wordpressUse' => 'After a copied WordPress SQLite import recovers a hot journal, rolls back a failed savepoint, and checkpoints the current WAL source, retry readers opened before the final journal/checkpoint generation must reopen instead of reusing stale page images.',
    'dependencyClosure' => $plan['dependency_closure'],
];

if (
    $summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next195'
    || $summary['admittedReaders'] !== ['wp-options-current-reader']
    || $summary['reopenReaders'] !== ['wp-options-retry-before-hot-journal-delete', 'wp-options-retry-before-savepoint']
) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next195 self-test failed\n");
    exit(1);
}

echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next195 self-test passed\n";
