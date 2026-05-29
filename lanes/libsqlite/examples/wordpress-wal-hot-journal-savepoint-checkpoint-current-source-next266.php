<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next265',
    'source_token' => 'wp-next266-current-source',
    'database_digest' => $digest('wp next266 checkpoint'),
    'page_cache_digest' => $digest('wp next266 cache'),
    'commit_generation' => 266,
    'schema_cookie' => 1266,
    'checkpoint_frame' => 56,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next266AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next266-reader-marks',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
