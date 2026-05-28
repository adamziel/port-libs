<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext259Plan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext259Plan;

$databaseDigest = hash('sha256', 'wordpress next259 post truncate writer generation');
$sealedPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next252',
    'post_truncate_source_sealed' => true,
    'database_path' => '/var/www/html/wp-content/database/.ht.sqlite',
    'wal_path' => '/var/www/html/wp-content/database/.ht.sqlite-wal',
    'journal_path' => '/var/www/html/wp-content/database/.ht.sqlite-journal',
    'source_token' => 'wordpress-next259-current-source',
    'writer_generation' => 259,
    'next_source_generation' => 260,
    'database_digest' => $databaseDigest,
    'released_reader_names' => ['front-page', 'plugin-cache', 'import-worker'],
    'covered_page_numbers' => [1, 2, 3, 5, 8, 13],
    'operation_names' => ['verify_post_truncate_source_seal_current_source_next252'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next252'],
];

$base = [
    'database_path' => $sealedPlan['database_path'],
    'wal_path' => $sealedPlan['wal_path'],
    'journal_path' => $sealedPlan['journal_path'],
    'source_token' => $sealedPlan['source_token'],
    'writer_generation' => $sealedPlan['writer_generation'],
    'source_generation' => $sealedPlan['next_source_generation'],
    'database_digest' => $databaseDigest,
    'wal_salt' => ['wp259saltA', 'wp259saltB'],
    'schema_cookie_after' => 260,
    'shm_mx_frame_after' => 0,
    'readmarks_after' => [1],
    'wal_size_before_append' => 32,
    'hot_journal_exists' => false,
    'exclusive_lock_held_until_release' => true,
    'writer_lock_released' => false,
    'durable' => true,
    'io_error' => null,
];

$receipts = [
    array_replace($base, ['name' => 'publish-wal-header', 'kind' => 'wal-header-publish']),
    array_replace($base, ['name' => 'publish-shm-generation', 'kind' => 'shm-generation-publish']),
    array_replace($base, ['name' => 'reset-readmarks', 'kind' => 'reader-generation-reset']),
    array_replace($base, ['name' => 'advance-schema-cookie', 'kind' => 'database-header-cookie']),
    array_replace($base, ['name' => 'release-writer-lock', 'kind' => 'writer-lock-release', 'writer_lock_released' => true]),
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext259Plan::admitWriterAfterPostTruncateSeal($sealedPlan, $receipts);

echo json_encode([
    'status' => $plan['status'],
    'writer_generation_admitted' => $plan['writer_generation_admitted'],
    'writer_action' => $plan['writer_action'],
    'wal_action' => $plan['wal_action'],
    'guard_names' => $plan['guard_names'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
