<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next370',
    'database_path' => '/srv/www/wp-content/database/wp-next371.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next371.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next371.sqlite-wal',
    'source_token' => 'wp-next371-current-source',
    'database_digest' => $digest('wp next371 checkpoint database image'),
    'page_cache_digest' => $digest('wp next371 checkpoint page cache image'),
    'commit_generation' => 371,
    'schema_cookie' => 1371,
    'checkpoint_frame' => 171,
    'operation_names' => ['verify_after_ready_checkpoint_wal_index_salt_epoch_receipt_next370'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next370'],
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next371AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next371-current-source-seal',
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
