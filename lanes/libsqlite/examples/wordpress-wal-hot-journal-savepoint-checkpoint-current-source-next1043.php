<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1042',
    'database_path' => '/srv/www/wp-content/database/wp-next1043.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1043.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1043.sqlite-wal',
    'source_token' => 'wp-next1043-current-source',
    'database_digest' => $digest('wp next1043 checkpoint database image'),
    'page_cache_digest' => $digest('wp next1043 checkpoint page cache image'),
    'commit_generation' => 1043,
    'schema_cookie' => 2043,
    'checkpoint_frame' => 843,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1012_1019_next1019',
        'seal_after_ready_checkpoint_current_source_next1020_1027_next1027',
        'seal_after_ready_checkpoint_current_source_next1028_1035_next1035',
        'verify_after_ready_checkpoint_wal_index_salt_page_cache_next1042',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1027'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1043AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next1043-current-source-seal',
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
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1043');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1042');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next1043-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next1020_1027_next1027', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next1028_1035_next1035', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1027', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1043', $plan['dependencies'], true));
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next1043 self-test passed\n";
}

return $plan;
