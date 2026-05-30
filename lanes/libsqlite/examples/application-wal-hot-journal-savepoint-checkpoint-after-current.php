<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1010',
    'database_path' => '/srv/www/wp-content/database/wp-next1011.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1011.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1011.sqlite-wal',
    'source_token' => 'wp-next1011-current-source',
    'database_digest' => $digest('wp next1011 checkpoint database image'),
    'page_cache_digest' => $digest('wp next1011 checkpoint page cache image'),
    'commit_generation' => 1011,
    'schema_cookie' => 2011,
    'checkpoint_frame' => 811,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next980_987_next987',
        'seal_after_ready_checkpoint_current_source_next988_995_next995',
        'seal_after_ready_checkpoint_current_source_next996_1003_next1003',
        'verify_after_ready_checkpoint_wal_index_salt_page_cache_next1010',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next995'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointVerification($base, [[
    'name' => 'wp-next1011-current-source-seal',
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
]], 1011, 'seal_after_ready_checkpoint_current_source_next1004_1011');

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1011');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1010');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next1011-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next988_995_next995', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next996_1003_next1003', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next995', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1011', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-after-current self-test passed\n";
}

return $plan;
