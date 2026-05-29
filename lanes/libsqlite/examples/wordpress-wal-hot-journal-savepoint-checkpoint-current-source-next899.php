<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next898',
    'database_path' => '/srv/www/wp-content/database/wp-next899.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next899.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next899.sqlite-wal',
    'source_token' => 'wp-next899-current-source',
    'database_digest' => $digest('wp next899 checkpoint database image'),
    'page_cache_digest' => $digest('wp next899 checkpoint page cache image'),
    'commit_generation' => 899,
    'schema_cookie' => 1899,
    'checkpoint_frame' => 699,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next868_875_next875',
        'seal_after_ready_checkpoint_current_source_next876_883_next883',
        'seal_after_ready_checkpoint_current_source_next884_891_next891',
        'verify_after_ready_checkpoint_wal_index_salt_reader_release_next898',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next883'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next899AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next899-current-source-seal',
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
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next899');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next898');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next899-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next876_883_next883', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next884_891_next891', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next883', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next899', $plan['dependencies'], true));
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next899 self-test passed\n";
}

return $plan;
