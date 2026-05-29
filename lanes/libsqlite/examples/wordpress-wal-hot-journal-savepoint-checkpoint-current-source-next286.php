<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next285',
    'source_token' => 'wp-next286-current-source',
    'database_digest' => $digest('wp next286 checkpoint'),
    'page_cache_digest' => $digest('wp next286 cache'),
    'commit_generation' => 286,
    'schema_cookie' => 1286,
    'checkpoint_frame' => 286,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next286AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next286-page-cache-digest-carry',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
