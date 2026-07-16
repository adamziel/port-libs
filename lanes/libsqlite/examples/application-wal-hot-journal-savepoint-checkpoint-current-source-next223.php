<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('application next223 checkpointed wp_options database');
$walDigest = $digest('application next223 reset wal generation');
$writerDigest = $digest('application next223 writer generation');

$resetPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next218',
    'mode' => 'restart',
    'database_path' => '/srv/www/wp-content/database/application.sqlite',
    'journal_path' => '/srv/www/wp-content/database/application.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/application.sqlite-wal',
    'page_size' => 4096,
    'checkpointed_frame' => 223,
    'can_reset_wal' => true,
    'database_digest' => $databaseDigest,
    'wal_digest' => $walDigest,
    'writer_digest' => $writerDigest,
    'next_writer_generation' => 224,
    'wal_action' => 'restart_wal_header_with_new_salt',
    'operation_names' => ['verify_restart_truncate_current_source_next218'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next218'],
];

$receipt = static function (string $name, string $role) use ($databaseDigest, $walDigest, $writerDigest): array {
    return [
        'name' => $name,
        'role' => $role,
        'checkpoint_frame' => 223,
        'writer_generation' => 224,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'sync_receipt' => true,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next223PublishCurrentSource($resetPlan, [
    $receipt('wp-options-database-write', 'database'),
    $receipt('wp-options-wal-reset', 'wal'),
    $receipt('wp-options-hot-journal-delete', 'journal'),
    $receipt('wp-options-reader-cache-reopen', 'reader-cache'),
], 225);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next223');
    assert($plan['publication_allowed'] === true);
    assert($plan['current_source_action'] === 'advance_current_source_epoch_225');
    assert(in_array('application-import-checkpoint-current-source-publication', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next223 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next223',
    'status' => $plan['status'],
    'publicationAllowed' => $plan['publication_allowed'],
    'currentSourceAction' => $plan['current_source_action'],
    'applicationUse' => 'A copied wp_options import advances the visible current source only after database, WAL, hot-journal deletion, and reader-cache reopen receipts all match the checkpoint reset generation.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
