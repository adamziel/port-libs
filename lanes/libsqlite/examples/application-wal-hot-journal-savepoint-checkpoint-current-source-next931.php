<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next930',
    'database_path' => '/srv/www/wp-content/database/wp-next931.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next931.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next931.sqlite-wal',
    'source_token' => 'wp-next931-current-source',
    'database_digest' => $digest('wp next931 checkpoint database image'),
    'page_cache_digest' => $digest('wp next931 checkpoint page cache image'),
    'commit_generation' => 931,
    'schema_cookie' => 1931,
    'checkpoint_frame' => 731,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next900_907_next907',
        'seal_after_ready_checkpoint_current_source_next908_915_next915',
        'seal_after_ready_checkpoint_current_source_next916_923_next923',
        'verify_after_ready_checkpoint_wal_index_salt_reader_release_next930',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next915'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next931AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next931-current-source-seal',
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
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next931');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next930');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next931-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next908_915_next915', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next916_923_next923', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next915', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next931', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next931 self-test passed\n";
}

return $plan;
