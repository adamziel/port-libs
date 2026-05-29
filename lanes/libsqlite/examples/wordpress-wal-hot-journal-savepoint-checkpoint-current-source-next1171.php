<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1170',
    'database_path' => '/srv/www/wp-content/database/wp-next1171.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1171.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1171.sqlite-wal',
    'source_token' => 'wp-next1171-current-source',
    'database_digest' => $digest('wp next1171 checkpoint database image'),
    'page_cache_digest' => $digest('wp next1171 checkpoint page cache image'),
    'commit_generation' => 1171,
    'schema_cookie' => 2171,
    'checkpoint_frame' => 971,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1140_1147_next1147',
        'seal_after_ready_checkpoint_current_source_next1148_1155_next1155',
        'seal_after_ready_checkpoint_current_source_next1156_1163_next1163',
        'verify_after_ready_checkpoint_wal_index_salt_page_cache_next1170',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1155'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointVerification(
    $base,
    [[
        'name' => 'wp-next1171-current-source-seal',
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
    ]],
    1171,
    'seal_after_ready_checkpoint_current_source_next1164_1171'
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1171');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1170');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next1171-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next1148_1155_next1155', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next1156_1163_next1163', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1155', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1171', $plan['dependencies'], true));
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next1171 self-test passed\n";
}

return $plan;
