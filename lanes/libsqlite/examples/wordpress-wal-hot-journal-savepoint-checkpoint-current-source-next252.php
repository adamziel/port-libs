<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext252Plan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext252Plan.php';

$digest = hash('sha256', 'wordpress next252 post truncate current source');
$truncationPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next248',
    'checkpoint_truncation_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wordpress.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wordpress.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wordpress.sqlite-journal',
    'source_token' => 'wordpress-next252-import-source',
    'writer_generation' => 252,
    'next_source_generation' => 253,
    'database_digest' => $digest,
    'covered_page_numbers' => [1, 2, 3, 5, 8, 13],
    'released_reader_names' => ['front-page', 'plugin-cache', 'wp-options-import'],
    'operation_names' => ['truncate_checkpoint_wal_current_source_next248'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next248'],
];

$baseReceipt = [
    'database_path' => $truncationPlan['database_path'],
    'wal_path' => $truncationPlan['wal_path'],
    'journal_path' => $truncationPlan['journal_path'],
    'source_token' => $truncationPlan['source_token'],
    'writer_generation' => $truncationPlan['writer_generation'],
    'source_generation' => $truncationPlan['next_source_generation'],
    'database_digest' => $digest,
    'reader_names' => $truncationPlan['released_reader_names'],
    'page_numbers' => $truncationPlan['covered_page_numbers'],
    'wal_size_after' => 0,
    'shm_mx_frame_after' => 0,
    'readmarks_after' => [1],
    'journal_exists_after' => false,
    'directory_synced' => true,
    'durable' => true,
    'exclusive_lock_held' => true,
    'pending_savepoint_depth' => 0,
];

$receipts = [
    array_replace($baseReceipt, ['name' => 'wordpress-next252-wal-truncate', 'kind' => 'wal-truncate']),
    array_replace($baseReceipt, ['name' => 'wordpress-next252-shm-reset', 'kind' => 'shm-reset']),
    array_replace($baseReceipt, ['name' => 'wordpress-next252-readmark-reset', 'kind' => 'readmark-reset']),
    array_replace($baseReceipt, ['name' => 'wordpress-next252-journal-unlink', 'kind' => 'journal-unlink']),
    array_replace($baseReceipt, ['name' => 'wordpress-next252-directory-sync', 'kind' => 'directory-sync']),
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext252Plan::sealPostTruncateSource($truncationPlan, $receipts);

echo json_encode([
    'status' => $plan['status'],
    'source_action' => $plan['source_action'],
    'wal_action' => $plan['wal_action'],
    'journal_action' => $plan['journal_action'],
    'shm_action' => $plan['shm_action'],
    'blocked_reasons' => $plan['blocked_reasons'],
    'seal_digest' => $plan['seal_digest'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
