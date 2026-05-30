<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$common = [
    'source_token' => 'wp-next263-current-source',
    'database_digest' => $digest('application next263 checkpoint database image'),
    'page_cache_digest' => $digest('application next263 clean page cache'),
    'commit_generation' => 263,
    'schema_cookie' => 1263,
    'checkpoint_frame' => 53,
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next263SealRetryReadReceipts([
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next262',
    'reader_cache_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next263.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next263.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next263.sqlite-wal',
    'admitted_retry_names' => ['retry-options-page', 'retry-autoload-page'],
    'retry_pages' => [1, 4],
    'accepted_reader_names' => ['wp-options-reader', 'autoload-index-reader'],
    'operation_names' => ['fence_reader_cache_after_hot_journal_checkpoint_current_source_next262'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next262'],
] + $common, [
    [
        'name' => 'close-options-retry',
        'retry_name' => 'retry-options-page',
        'reader_name' => 'wp-options-reader',
        'page_number' => 1,
        'cursor_closed' => true,
        'snapshot_released' => true,
        'hot_journal_visible' => false,
        'stale_wal_visible' => false,
    ] + $common,
    [
        'name' => 'close-autoload-retry',
        'retry_name' => 'retry-autoload-page',
        'reader_name' => 'autoload-index-reader',
        'page_number' => 4,
        'cursor_closed' => true,
        'snapshot_released' => true,
        'hot_journal_visible' => false,
        'stale_wal_visible' => false,
    ] + $common,
]);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next263');
    assert($plan['retry_read_receipts_sealed'] === true);
    assert($plan['missing_retry_names'] === []);
    assert($plan['missing_retry_pages'] === []);
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next263 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next263',
    'applicationUse' => 'A copied Application import closes retry readers only after the next262 cache fence proves no hot-journal or stale WAL generation is visible.',
    'status' => $plan['status'],
    'sealed' => $plan['retry_read_receipts_sealed'],
    'retryAction' => $plan['retry_action'],
    'coveredRetryNames' => $plan['covered_retry_names'],
    'coveredRetryPages' => $plan['covered_retry_pages'],
    'blockedGuards' => $plan['blocked_guard_names'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
