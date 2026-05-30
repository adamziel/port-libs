<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = hash('sha256', 'application next236 checkpointed wp_options image');
$statementPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next233',
    'statement_admission_allowed' => true,
    'database_path' => '/srv/www/wp-content/database/application.sqlite',
    'journal_path' => '/srv/www/wp-content/database/application.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/application.sqlite-wal',
    'source_token' => 'application-next236-source',
    'next_writer_generation' => 236,
    'schema_cookie' => 432,
    'database_digest' => $digest,
    'admitted_statement_names' => ['select-active-plugins', 'select-autoload-options', 'select-option-index'],
    'operation_names' => ['admit_prepared_statement_current_source_next233'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next233'],
];

$finalizer = static function (string $name, string $statement) use ($statementPlan, $digest): array {
    return [
        'name' => $name,
        'statement_name' => $statement,
        'source_token' => $statementPlan['source_token'],
        'generation' => $statementPlan['next_writer_generation'],
        'schema_cookie' => $statementPlan['schema_cookie'],
        'database_digest' => $digest,
        'sqlite_done_seen' => true,
        'reset_called' => true,
        'reader_lease_released' => true,
        'wal_hook_receipt' => true,
        'autocheckpoint_receipt' => true,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter($statementPlan, [
    $finalizer('active-plugins-finalizer', 'select-active-plugins'),
    $finalizer('autoload-options-finalizer', 'select-autoload-options'),
    $finalizer('option-index-finalizer', 'select-option-index'),
], 237);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next236');
    assert($plan['next_writer_allowed'] === true);
    assert($plan['writer_action'] === 'open_next_wal_writer_generation_237');
    assert(in_array('application-import-checkpoint-finalizer-before-next-writer', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next236 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next236',
    'status' => $plan['status'],
    'nextWriterAllowed' => $plan['next_writer_allowed'],
    'writerAction' => $plan['writer_action'],
    'applicationUse' => 'A copied wp_options import opens the next WAL writer only after checkpoint-current prepared statements have reached SQLITE_DONE, reset, released reader leases, and produced WAL-hook/autocheckpoint receipts.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
