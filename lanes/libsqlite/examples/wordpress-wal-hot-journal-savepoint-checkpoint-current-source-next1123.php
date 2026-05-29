<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1122',
    'database_path' => '/srv/www/wp-content/database/wp-next1123.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1123.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1123.sqlite-wal',
    'source_token' => 'wp-next1123-current-source',
    'database_digest' => $digest('wp next1123 checkpoint database image'),
    'page_cache_digest' => $digest('wp next1123 checkpoint page cache image'),
    'commit_generation' => 1123,
    'schema_cookie' => 2123,
    'checkpoint_frame' => 923,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1092_1099_next1099',
        'seal_after_ready_checkpoint_current_source_next1100_1107_next1107',
        'seal_after_ready_checkpoint_current_source_next1108_1115_next1115',
        'verify_after_ready_checkpoint_wal_index_salt_page_cache_next1122',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1107'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1123AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next1123-current-source-seal',
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
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1123');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1122');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next1123-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next1100_1107_next1107', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next1108_1115_next1115', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1107', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1123', $plan['dependencies'], true));
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next1123 self-test passed\n";
}

return $plan;
