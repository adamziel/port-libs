<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next722',
    'database_path' => '/srv/www/wp-content/database/wp-next723.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next723.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next723.sqlite-wal',
    'source_token' => 'wp-next723-current-source',
    'database_digest' => $digest('wp next723 checkpoint database image'),
    'page_cache_digest' => $digest('wp next723 checkpoint page cache image'),
    'commit_generation' => 723,
    'schema_cookie' => 1723,
    'checkpoint_frame' => 523,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next700_707_next707',
        'seal_after_ready_checkpoint_current_source_next708_715_next715',
        'verify_after_ready_checkpoint_wal_index_salt_reader_release_next722',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next707'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next723AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next723-current-source-seal',
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
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next723');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next722');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next723-current-source-seal']);
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next707', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next723', $plan['dependencies'], true));
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next723 self-test passed\n";
}

return $plan;
