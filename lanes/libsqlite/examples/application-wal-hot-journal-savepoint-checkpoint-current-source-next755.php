<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next754',
    'database_path' => '/srv/www/wp-content/database/wp-next755.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next755.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next755.sqlite-wal',
    'source_token' => 'wp-next755-current-source',
    'database_digest' => $digest('wp next755 checkpoint database image'),
    'page_cache_digest' => $digest('wp next755 checkpoint page cache image'),
    'commit_generation' => 755,
    'schema_cookie' => 1755,
    'checkpoint_frame' => 555,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next732_739_next739',
        'seal_after_ready_checkpoint_current_source_next740_747_next747',
        'verify_after_ready_checkpoint_wal_index_salt_reader_release_next754',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next739'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next755AfterCurrentCheckpoint($base, [[
    'name' => 'wp-next755-current-source-seal',
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
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next755');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next754');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next755-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next732_739_next739', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next740_747_next747', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next739', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next755', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next755 self-test passed\n";
}

return $plan;
