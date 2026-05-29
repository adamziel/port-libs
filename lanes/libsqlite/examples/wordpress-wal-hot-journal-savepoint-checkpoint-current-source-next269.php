<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next268',
    'source_token' => 'wp-next269-current-source',
    'database_digest' => $digest('wp next269 checkpoint'),
    'page_cache_digest' => $digest('wp next269 cache'),
    'commit_generation' => 269,
    'schema_cookie' => 1269,
    'checkpoint_frame' => 269,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next269AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next269-after-current-seal',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
