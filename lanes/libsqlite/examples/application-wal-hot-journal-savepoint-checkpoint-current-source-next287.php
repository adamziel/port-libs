<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next286',
    'source_token' => 'wp-next287-current-source',
    'database_digest' => $digest('wp next287 checkpoint'),
    'page_cache_digest' => $digest('wp next287 cache'),
    'commit_generation' => 287,
    'schema_cookie' => 1287,
    'checkpoint_frame' => 287,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointVerification($base, [[
    'name' => 'wp-next287-current-source-window-seal',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
