<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next386',
    'database_path' => '/srv/www/wp-content/database/wp-next387.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next387.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next387.sqlite-wal',
    'source_token' => 'wp-next387-current-source',
    'database_digest' => $digest('wp next387 checkpoint database image'),
    'page_cache_digest' => $digest('wp next387 checkpoint page cache image'),
    'commit_generation' => 387,
    'schema_cookie' => 1387,
    'checkpoint_frame' => 187,
    'operation_names' => ['verify_after_ready_checkpoint_wal_index_salt_source_epoch_next386'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next386'],
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointSourceEpochStage($base, [[
    'name' => 'wp-next387-current-source-seal',
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
]], 'current-source-seal');
