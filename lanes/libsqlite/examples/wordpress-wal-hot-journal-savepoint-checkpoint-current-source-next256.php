<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext256Plan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext256Plan.php';

$digest = hash('sha256', 'wordpress next256 sealed checkpoint source');
$sealedPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next252',
    'post_truncate_source_sealed' => true,
    'database_path' => '/srv/www/wp-content/database/wordpress.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wordpress.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wordpress.sqlite-journal',
    'source_token' => 'wordpress-next256-sealed-source',
    'next_source_generation' => 256,
    'database_digest' => $digest,
    'released_reader_names' => ['front-page', 'plugin-cache', 'wp-options-import'],
    'covered_page_numbers' => [1, 2, 3, 5, 8, 13],
    'operation_names' => ['advance_checkpoint_current_source_next252'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next252'],
];

$baseReceipt = [
    'database_path' => $sealedPlan['database_path'],
    'wal_path' => $sealedPlan['wal_path'],
    'journal_path' => $sealedPlan['journal_path'],
    'source_token' => $sealedPlan['source_token'],
    'source_generation' => $sealedPlan['next_source_generation'],
    'database_digest' => $digest,
    'checkpoint_sequence' => 25601,
    'database_change_counter' => 51201,
    'schema_cookie' => 91,
    'wal_size' => 0,
    'shm_mx_frame' => 0,
    'hot_journal_exists' => false,
    'pending_savepoint_depth' => 0,
    'database_synced' => true,
    'directory_synced' => true,
    'read_transaction_open' => true,
    'io_error' => null,
];

$receipts = [
    array_replace($baseReceipt, ['name' => 'wordpress-next256-front-page', 'reader_name' => 'front-page', 'readmark_slot' => 1, 'page_numbers' => [1, 2, 3]]),
    array_replace($baseReceipt, ['name' => 'wordpress-next256-plugin-cache', 'reader_name' => 'plugin-cache', 'readmark_slot' => 2, 'page_numbers' => [3, 5, 8]]),
    array_replace($baseReceipt, ['name' => 'wordpress-next256-options-import', 'reader_name' => 'wp-options-import', 'readmark_slot' => 3, 'page_numbers' => [8, 13]]),
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext256Plan::admitReopenedReaders($sealedPlan, $receipts);

echo json_encode([
    'status' => $plan['status'],
    'reader_action' => $plan['reader_action'],
    'wal_action' => $plan['wal_action'],
    'journal_action' => $plan['journal_action'],
    'covered_readers' => $plan['covered_reader_names'],
    'covered_pages' => $plan['covered_page_numbers'],
    'blocked_reasons' => $plan['blocked_reasons'],
    'current_source_digest' => $plan['current_source_digest'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
