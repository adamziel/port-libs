<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1154',
    'database_path' => '/srv/www/wp-content/database/wp-next1155.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1155.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1155.sqlite-wal',
    'source_token' => 'wp-next1155-current-source',
    'database_digest' => $digest('wp next1155 checkpoint database image'),
    'page_cache_digest' => $digest('wp next1155 checkpoint page cache image'),
    'commit_generation' => 1155,
    'schema_cookie' => 2155,
    'checkpoint_frame' => 955,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1124_1131_next1131',
        'seal_after_ready_checkpoint_current_source_next1132_1139_next1139',
        'seal_after_ready_checkpoint_current_source_next1140_1147_next1147',
        'verify_after_ready_checkpoint_wal_index_salt_page_cache_next1154',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1139'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointVerification($base, [[
    'name' => 'wp-next1155-current-source-seal',
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
]], 1155, 'seal_after_ready_checkpoint_current_source_next1148_1155');

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1155');
    assert($plan['base_status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next1154');
    assert($plan['accepted_checkpoint_receipt_names'] === ['wp-next1155-current-source-seal']);
    assert(in_array('seal_after_ready_checkpoint_current_source_next1132_1139_next1139', $plan['operation_names'], true));
    assert(in_array('seal_after_ready_checkpoint_current_source_next1140_1147_next1147', $plan['operation_names'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1139', $plan['dependencies'], true));
    assert(in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1155', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next1155 self-test passed\n";
}

return $plan;
