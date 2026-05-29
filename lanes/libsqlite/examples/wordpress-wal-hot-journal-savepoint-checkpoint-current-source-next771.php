<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next770',
    'database_path' => '/srv/www/wp-content/database/wp-next771.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next771.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next771.sqlite-wal',
    'source_token' => 'wp-next771-current-source',
    'database_digest' => $digest('wp next771 checkpoint database image'),
    'page_cache_digest' => $digest('wp next771 checkpoint page cache image'),
    'commit_generation' => 771,
    'schema_cookie' => 1771,
    'checkpoint_frame' => 571,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next748_755_next755',
        'seal_after_ready_checkpoint_current_source_next756_763_next763',
        'verify_after_ready_checkpoint_wal_index_salt_reader_release_next770',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next755'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next771AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next771-current-source-seal',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => $base['commit_generation'],
    'schema_cookie' => $base['schema_cookie'],
    'checkpoint_frame' => $base['checkpoint_frame'],
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
]]);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next771');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next770');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next771-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next748_755_next755', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next756_763_next763', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next755', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next771', $plan['dependencies'], true));
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next771 self-test passed\n";
}

return $plan;
