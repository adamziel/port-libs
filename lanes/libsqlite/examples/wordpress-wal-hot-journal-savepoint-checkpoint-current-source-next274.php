<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next273',
    'source_token' => 'wp-next274-current-source',
    'database_digest' => $digest('wp next274 checkpoint'),
    'page_cache_digest' => $digest('wp next274 cache'),
    'commit_generation' => 274,
    'schema_cookie' => 1274,
    'checkpoint_frame' => 274,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next274AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next274-after-current-seal',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
