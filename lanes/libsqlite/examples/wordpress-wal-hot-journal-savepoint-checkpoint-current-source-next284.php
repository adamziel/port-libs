<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next283',
    'source_token' => 'wp-next284-current-source',
    'database_digest' => $digest('wp next284 checkpoint'),
    'page_cache_digest' => $digest('wp next284 cache'),
    'commit_generation' => 284,
    'schema_cookie' => 1284,
    'checkpoint_frame' => 284,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next284AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next284-wal-index-publish',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
