<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1058',
    'database_path' => '/srv/www/wp-content/database/wp-next1059.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1059.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1059.sqlite-wal',
    'source_token' => 'wp-next1059-current-source',
    'database_digest' => $digest('wp next1059 checkpoint database image'),
    'page_cache_digest' => $digest('wp next1059 checkpoint page cache image'),
    'commit_generation' => 1059,
    'schema_cookie' => 2059,
    'checkpoint_frame' => 859,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1028_1035_next1035',
        'seal_after_ready_checkpoint_current_source_next1036_1043_next1043',
        'seal_after_ready_checkpoint_current_source_next1044_1051_next1051',
        'verify_after_ready_checkpoint_wal_index_salt_page_cache_next1058',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1043'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1059AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next1059-current-source-seal',
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
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1059');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1058');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next1059-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next1036_1043_next1043', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next1044_1051_next1051', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1043', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1059', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next1059 self-test passed\n";
}

return $plan;
