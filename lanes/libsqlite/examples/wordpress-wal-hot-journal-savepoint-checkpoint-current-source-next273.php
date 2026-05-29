<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next272',
    'source_token' => 'wp-next273-current-source',
    'database_digest' => $digest('wp next273 checkpoint'),
    'page_cache_digest' => $digest('wp next273 cache'),
    'commit_generation' => 273,
    'schema_cookie' => 1273,
    'checkpoint_frame' => 273,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next273AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next273-after-current-seal',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
