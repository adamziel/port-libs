<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next706',
    'database_path' => '/srv/www/wp-content/database/wp-next707.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next707.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next707.sqlite-wal',
    'source_token' => 'wp-next707-current-source',
    'database_digest' => $digest('wp next707 checkpoint database image'),
    'page_cache_digest' => $digest('wp next707 checkpoint page cache image'),
    'commit_generation' => 707,
    'schema_cookie' => 1707,
    'checkpoint_frame' => 507,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next684_691_next691',
        'seal_after_ready_checkpoint_current_source_next692_699_next699',
        'verify_after_ready_checkpoint_wal_index_salt_page_cache_next706',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next691'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next707AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next707-current-source-seal',
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
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next707');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next706');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next707-current-source-seal']);
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next691', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next707', $plan['dependencies'], true));
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next707 self-test passed\n";
}

return $plan;
