<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next962',
    'database_path' => '/srv/www/wp-content/database/wp-next963.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next963.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next963.sqlite-wal',
    'source_token' => 'wp-next963-current-source',
    'database_digest' => $digest('wp next963 checkpoint database image'),
    'page_cache_digest' => $digest('wp next963 checkpoint page cache image'),
    'commit_generation' => 963,
    'schema_cookie' => 1963,
    'checkpoint_frame' => 763,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next932_939_next939',
        'seal_after_ready_checkpoint_current_source_next940_947_next947',
        'seal_after_ready_checkpoint_current_source_next948_955_next955',
        'verify_after_ready_checkpoint_wal_index_salt_reader_release_next962',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next947'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next963AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next963-current-source-seal',
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
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next963');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next962');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next963-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next940_947_next947', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next948_955_next955', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next947', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next963', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next963 self-test passed\n";
}

return $plan;
