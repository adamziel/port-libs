<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next850',
    'database_path' => '/srv/www/wp-content/database/wp-next851.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next851.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next851.sqlite-wal',
    'source_token' => 'wp-next851-current-source',
    'database_digest' => $digest('wp next851 checkpoint database image'),
    'page_cache_digest' => $digest('wp next851 checkpoint page cache image'),
    'commit_generation' => 851,
    'schema_cookie' => 1851,
    'checkpoint_frame' => 651,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next820_827_next827',
        'seal_after_ready_checkpoint_current_source_next828_835_next835',
        'seal_after_ready_checkpoint_current_source_next836_843_next843',
        'verify_after_ready_checkpoint_wal_index_salt_reader_release_next850',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next835'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next851AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next851-current-source-seal',
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
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next851');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next850');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next851-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next828_835_next835', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next836_843_next843', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next835', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next851', $plan['dependencies'], true));
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next851 self-test passed\n";
}

return $plan;
