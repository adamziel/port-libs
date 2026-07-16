<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next266',
    'source_token' => 'wp-next267-current-source',
    'database_digest' => $digest('wp next267 checkpoint'),
    'page_cache_digest' => $digest('wp next267 cache'),
    'commit_generation' => 267,
    'schema_cookie' => 1267,
    'checkpoint_frame' => 57,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointVerification($base, [[
    'name' => 'wp-next267-after-current-seal',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
