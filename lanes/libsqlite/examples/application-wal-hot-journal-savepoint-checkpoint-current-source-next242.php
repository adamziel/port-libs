<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('application next242 post checkpoint writer commit');
$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next238',
    'writer_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next242.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next242.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next242.sqlite-journal',
    'source_token' => 'wp-next242-current-source',
    'published_writer_generation' => 242,
    'next_writer_generation' => 243,
    'database_digest' => $databaseDigest,
    'expected_schema_cookie' => 24277,
    'expected_wal_salt' => '2420abcd2420dcba',
    'covered_page_numbers' => [1, 2, 3, 4, 5],
    'operation_names' => ['admit_next_writer_after_restart_checkpoint_next238'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next238'],
];

$receipt = static function (string $name, string $kind, array $overrides = []) use ($admission, $databaseDigest): array {
    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'database_path' => $admission['database_path'],
        'wal_path' => $admission['wal_path'],
        'journal_path' => $admission['journal_path'],
        'source_token' => $admission['source_token'],
        'writer_generation' => $admission['next_writer_generation'],
        'published_generation' => $admission['published_writer_generation'],
        'observed_database_digest' => $databaseDigest,
        'schema_cookie' => $admission['expected_schema_cookie'],
        'wal_salt' => $admission['expected_wal_salt'],
        'first_wal_frame' => 1,
        'last_wal_frame' => 2,
        'page_numbers' => [1, 2],
        'database_backfilled' => true,
        'wal_synced' => true,
        'directory_synced' => true,
        'hot_journal_visible' => false,
        'savepoint_depth' => 0,
        'reader_cache_dirty' => false,
        'reader_generation' => $admission['next_writer_generation'],
    ], $overrides);
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next242AdmitCommittedWriter($admission, [
    $receipt('wp-next242-wal-commit', 'wal-commit'),
    $receipt('wp-next242-database-backfill', 'database-backfill', ['page_numbers' => [1, 2, 3]]),
    $receipt('wp-next242-directory-sync', 'directory-sync', ['page_numbers' => [1], 'first_wal_frame' => 1, 'last_wal_frame' => 1]),
    $receipt('wp-next242-reader-generation', 'reader-generation', ['page_numbers' => [4, 5], 'reader_generation' => 244]),
]);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next242',
    'applicationUse' => 'After a copied Application import restarts WAL from a checkpointed current source, the first writer commit is published only after WAL commit, database backfill, directory sync, and reader-generation receipts agree.',
    'status' => $plan['status'],
    'commitAdmitted' => $plan['commit_admitted'],
    'receiptKinds' => $plan['receipt_kinds'],
    'currentSourceAction' => $plan['current_source_action'],
    'walAction' => $plan['wal_action'],
    'blockedReasons' => $plan['blocked_reasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next242'
        || $summary['commitAdmitted'] !== true
        || $summary['receiptKinds'] !== ['database-backfill', 'directory-sync', 'reader-generation', 'wal-commit']
        || $summary['blockedReasons'] !== []
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next242 self-test failed\n");
        exit(1);
    }

    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next242 self-test passed\n";
}

return $summary;
