<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next263',
    'source_token' => 'wp-next264-current-source',
    'database_digest' => $digest('wp next264 checkpoint'),
    'page_cache_digest' => $digest('wp next264 cache'),
    'commit_generation' => 264,
    'schema_cookie' => 1264,
    'checkpoint_frame' => 54,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointVerification($base, [[
    'name' => 'wp-next264-db-header',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
