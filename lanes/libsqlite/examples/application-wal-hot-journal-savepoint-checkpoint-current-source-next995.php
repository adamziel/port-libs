<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next994',
    'database_path' => '/srv/www/wp-content/database/wp-next995.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next995.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next995.sqlite-wal',
    'source_token' => 'wp-next995-current-source',
    'database_digest' => $digest('wp next995 checkpoint database image'),
    'page_cache_digest' => $digest('wp next995 checkpoint page cache image'),
    'commit_generation' => 995,
    'schema_cookie' => 1995,
    'checkpoint_frame' => 795,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next964_971_next971',
        'seal_after_ready_checkpoint_current_source_next972_979_next979',
        'seal_after_ready_checkpoint_current_source_next980_987_next987',
        'verify_after_ready_checkpoint_wal_index_salt_database_digest_next994',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next979'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next995AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next995-current-source-seal',
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
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next995');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next994');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next995-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next972_979_next979', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next980_987_next987', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next979', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next995', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next995 self-test passed\n";
}

return $plan;
