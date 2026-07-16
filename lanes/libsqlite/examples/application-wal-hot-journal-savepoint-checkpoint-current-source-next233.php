<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$hash = static fn (string $value): string => hash('sha256', $value);
$rootPages = [
    1 => $hash('application next233 schema root'),
    2 => $hash('application next233 wp_options root'),
    5 => $hash('application next233 option_name index root'),
];
$databaseDigest = $hash('application next233 checkpoint database');
$handlePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next229',
    'current_source_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/application.sqlite',
    'journal_path' => '/srv/www/wp-content/database/application.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/application.sqlite-wal',
    'source_token' => 'application-next233-current-source',
    'next_writer_generation' => 233,
    'schema_cookie' => 331,
    'database_digest' => $databaseDigest,
    'admitted_handle_names' => ['schema-handle', 'options-handle', 'index-handle'],
    'operation_names' => ['verify_reopened_handles_after_checkpoint_publication_next229'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next229'],
];

$statement = static function (string $name, string $handle, array $pages) use ($handlePlan, $databaseDigest): array {
    return [
        'name' => $name,
        'handle_name' => $handle,
        'source_token' => $handlePlan['source_token'],
        'generation' => $handlePlan['next_writer_generation'],
        'schema_cookie' => $handlePlan['schema_cookie'],
        'database_digest' => $databaseDigest,
        'root_page_digests' => $pages,
        'schema_reparse_receipt' => true,
        'read_lock_receipt' => true,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements($handlePlan, [
    $statement('wp-options-schema-select', 'schema-handle', [1 => $rootPages[1]]),
    $statement('wp-options-autoload-select', 'options-handle', [2 => $rootPages[2]]),
    $statement('wp-options-name-index-select', 'index-handle', [5 => $rootPages[5]]),
], $rootPages);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next233');
    assert($plan['statement_admission_allowed'] === true);
    assert($plan['statement_action'] === 'reuse_prepared_statements_on_checkpoint_current_source');
    assert(in_array('application-import-checkpoint-statement-reuse-after-hot-journal', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next233 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next233',
    'status' => $plan['status'],
    'statementAdmissionAllowed' => $plan['statement_admission_allowed'],
    'statementAction' => $plan['statement_action'],
    'coveredRootPages' => $plan['covered_root_pages'],
    'applicationUse' => 'After copied wp_options WAL checkpoint publication, prepared SELECT statements are reused only when their handles, schema cookie, root-page digests, and read-lock receipts match the new current source.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
