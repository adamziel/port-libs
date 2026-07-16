<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next626',
    'database_path' => '/srv/www/wp-content/database/wp-next627.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next627.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next627.sqlite-wal',
    'source_token' => 'wp-next627-current-source',
    'database_digest' => $digest('wp next627 checkpoint database image'),
    'page_cache_digest' => $digest('wp next627 checkpoint page cache image'),
    'commit_generation' => 627,
    'schema_cookie' => 1627,
    'checkpoint_frame' => 427,
    'operation_names' => ['verify_after_ready_checkpoint_wal_index_salt_page_cache_next626'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next626'],
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base, [[
    'name' => 'wp-next627-current-source-seal',
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
]], 627);