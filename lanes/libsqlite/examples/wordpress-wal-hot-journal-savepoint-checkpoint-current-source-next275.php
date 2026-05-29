<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next274',
    'source_token' => 'wp-next275-current-source',
    'database_digest' => $digest('wp next275 checkpoint'),
    'page_cache_digest' => $digest('wp next275 cache'),
    'commit_generation' => 275,
    'schema_cookie' => 1275,
    'checkpoint_frame' => 275,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next275AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next275-after-current-seal',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
