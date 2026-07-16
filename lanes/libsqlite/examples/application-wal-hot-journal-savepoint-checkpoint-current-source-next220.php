<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$next219 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next219',
    'database_path' => '/srv/www/wp-content/database/application.sqlite',
    'wal_path' => '/srv/www/wp-content/database/application.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/application.sqlite-journal',
    'current_source_token' => ['id' => 'application-next220-checkpoint-source', 'epoch' => 219],
    'checkpoint_frame' => 220,
    'checkpoint_cookie' => 220220,
    'schema_cookie' => 22017,
    'admitted_reader_names' => ['wp-options-reader', 'wp-schema-reader'],
    'reopen_reader_names' => ['wp-plugin-cache-reader'],
    'checkpoint_next_source_published' => true,
    'next_source_epoch' => 220,
    'operation_names' => ['publish_checkpoint_next_source_after_savepoint_finalization_next219'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next219'],
];

$receipt = static fn (string $name): array => [
    'name' => $name,
    'source_id' => 'application-next220-checkpoint-source',
    'observed_epoch' => 220,
    'checkpoint_frame' => 220,
    'checkpoint_cookie' => 220220,
    'schema_cookie' => 22017,
    'cache_reopened' => true,
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next220ReaderReopenPlan($next219, [
    $receipt('wp-options-reader'),
    $receipt('wp-schema-reader'),
    $receipt('wp-plugin-cache-reader'),
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next220');
    assert($plan['reader_reopen_allowed'] === true);
    assert($plan['reader_cache_action'] === 'install_reopened_checkpoint_reader_cache_next220');
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next220 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next220',
    'status' => $plan['status'],
    'readerReopenAllowed' => $plan['reader_reopen_allowed'],
    'readerCacheAction' => $plan['reader_cache_action'],
    'applicationUse' => 'A copied wp_options import reopens plugin readers only after the checkpoint source, schema cookie, and hot-journal savepoint generation are visible.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
