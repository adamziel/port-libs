<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next279',
    'source_token' => 'wp-next280-current-source',
    'database_digest' => $digest('wp next280 checkpoint'),
    'page_cache_digest' => $digest('wp next280 cache'),
    'commit_generation' => 280,
    'schema_cookie' => 1280,
    'checkpoint_frame' => 280,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointVerification($base, [[
    'name' => 'wp-next280-generation-fence',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
