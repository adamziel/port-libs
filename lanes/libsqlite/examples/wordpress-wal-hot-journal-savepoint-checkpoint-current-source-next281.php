<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next280',
    'source_token' => 'wp-next281-current-source',
    'database_digest' => $digest('wp next281 checkpoint'),
    'page_cache_digest' => $digest('wp next281 cache'),
    'commit_generation' => 281,
    'schema_cookie' => 1281,
    'checkpoint_frame' => 281,
];

return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next281AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next281-schema-cookie-fence',
] + $base + [
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);
