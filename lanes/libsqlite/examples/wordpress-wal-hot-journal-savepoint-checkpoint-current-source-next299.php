<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next298',
    'database_path' => '/srv/www/wp-content/database/wp-next299.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next299.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next299.sqlite-wal',
    'source_token' => 'wp-next299-current-source',
    'database_digest' => $digest('wp next299 checkpoint database image'),
    'page_cache_digest' => $digest('wp next299 checkpoint page cache image'),
    'commit_generation' => 299,
    'schema_cookie' => 1299,
    'checkpoint_frame' => 99,
    'operation_names' => ['verify_after_ready_checkpoint_hot_journal_delete_receipt_next298'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next298'],
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next299AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next299-current-source-seal',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => $base['commit_generation'],
    'schema_cookie' => $base['schema_cookie'],
    'checkpoint_frame' => $base['checkpoint_frame'],
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
