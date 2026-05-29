<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('wordpress next239 copied wp_options checkpoint image');
$finalizerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next236',
    'next_writer_allowed' => true,
    'database_path' => '/srv/www/wp-content/database/wordpress.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wordpress.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wordpress.sqlite-journal',
    'source_token' => 'wordpress-import-next239',
    'current_writer_generation' => 239,
    'next_writer_generation' => 240,
    'schema_cookie' => 90239,
    'database_digest' => $databaseDigest,
    'finalized_statement_names' => ['select-wp-options', 'select-active-plugins', 'select-theme-mods'],
    'operation_names' => ['admit_next_wal_writer_after_checkpoint_finalizers_next236'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next236'],
];
$receipt = static function (string $name, string $kind, array $statements) use ($finalizerPlan, $databaseDigest): array {
    $path = match ($kind) {
        'database' => $finalizerPlan['database_path'],
        'wal' => $finalizerPlan['wal_path'],
        'journal' => $finalizerPlan['journal_path'],
        'directory' => dirname($finalizerPlan['database_path']),
    };

    return [
        'name' => $name,
        'kind' => $kind,
        'path' => $path,
        'statement_names' => $statements,
        'source_token' => $finalizerPlan['source_token'],
        'current_generation' => $finalizerPlan['current_writer_generation'],
        'next_generation' => $finalizerPlan['next_writer_generation'],
        'schema_cookie' => $finalizerPlan['schema_cookie'],
        'database_digest' => $databaseDigest,
        'exclusive_lock_held' => true,
        'fsync_complete' => true,
        'page_images_written' => $kind === 'database',
        'header_cookie_persisted' => $kind === 'database',
        'mx_frame' => $kind === 'wal' ? 0 : null,
        'readmarks_reset' => $kind === 'wal',
        'hot_journal_deleted' => $kind === 'journal',
        'persisted_paths' => [$finalizerPlan['database_path'], $finalizerPlan['wal_path'], $finalizerPlan['journal_path']],
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next239AdmitAtomicCommitBarrier($finalizerPlan, [
    $receipt('wordpress-next239-database', 'database', ['select-wp-options', 'select-active-plugins']),
    $receipt('wordpress-next239-wal', 'wal', ['select-theme-mods']),
    $receipt('wordpress-next239-journal', 'journal', ['select-wp-options']),
    $receipt('wordpress-next239-directory', 'directory', ['select-wp-options', 'select-active-plugins', 'select-theme-mods']),
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next239');
    assert($plan['current_source_admitted'] === true);
    assert($plan['writer_action'] === 'start_next_writer_generation_240');
    assert(in_array('wordpress-import-atomic-current-source-switch', $plan['dependencies'], true));
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next239 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next239',
    'status' => $plan['status'],
    'currentSourceAdmitted' => $plan['current_source_admitted'],
    'writerAction' => $plan['writer_action'],
    'coveredStatements' => $plan['covered_statement_names'],
    'wordpressUse' => 'A copied wp_options import switches reopened readers to the checkpoint current source only after database, WAL, hot-journal delete, and directory fsync receipts all cover finalized statements under one atomic commit barrier.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
