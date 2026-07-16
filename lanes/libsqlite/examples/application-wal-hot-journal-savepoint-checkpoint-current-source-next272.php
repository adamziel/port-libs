<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next271',
    'source_token' => 'wp-next272-current-source',
    'database_digest' => $digest('wp next272 checkpoint'),
    'page_cache_digest' => $digest('wp next272 cache'),
    'commit_generation' => 272,
    'schema_cookie' => 1272,
    'checkpoint_frame' => 272,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointVerification($base, [[
    'name' => 'wp-next272-after-current-seal',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
