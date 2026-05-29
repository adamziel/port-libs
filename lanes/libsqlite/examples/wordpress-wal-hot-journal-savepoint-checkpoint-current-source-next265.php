<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next264',
    'source_token' => 'wp-next265-current-source',
    'database_digest' => $digest('wp next265 checkpoint'),
    'page_cache_digest' => $digest('wp next265 cache'),
    'commit_generation' => 265,
    'schema_cookie' => 1265,
    'checkpoint_frame' => 55,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next265AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next265-wal-index-salt',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
