<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1106',
    'database_path' => '/srv/www/wp-content/database/wp-next1107.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1107.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1107.sqlite-wal',
    'source_token' => 'wp-next1107-current-source',
    'database_digest' => $digest('wp next1107 checkpoint database image'),
    'page_cache_digest' => $digest('wp next1107 checkpoint page cache image'),
    'commit_generation' => 1107,
    'schema_cookie' => 2107,
    'checkpoint_frame' => 907,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1076_1083_next1083',
        'seal_after_ready_checkpoint_current_source_next1084_1091_next1091',
        'seal_after_ready_checkpoint_current_source_next1092_1099_next1099',
        'verify_after_ready_checkpoint_wal_index_salt_page_cache_next1106',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1091'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1107AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next1107-current-source-seal',
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
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1107');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1106');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next1107-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next1084_1091_next1091', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next1092_1099_next1099', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1091', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1107', $plan['dependencies'], true));
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next1107 self-test passed\n";
}

return $plan;
