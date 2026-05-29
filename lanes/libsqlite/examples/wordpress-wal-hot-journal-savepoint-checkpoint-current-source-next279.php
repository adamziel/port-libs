<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next278',
    'source_token' => 'wp-next279-current-source',
    'database_digest' => $digest('wp next279 checkpoint'),
    'page_cache_digest' => $digest('wp next279 cache'),
    'commit_generation' => 279,
    'schema_cookie' => 1279,
    'checkpoint_frame' => 279,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next279AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next279-after-current-seal',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
