<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('wordpress next235 checkpoint database image');
$walDigest = $hash('wordpress next235 restarted wal sidecar');
$readerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next232',
    'current_source_readable' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next235.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next235.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next235.sqlite-journal',
    'source_token' => 'wp-next235-current-source',
    'next_writer_generation' => 235,
    'database_digest' => $databaseDigest,
    'expected_schema_cookie' => 23577,
    'expected_wal_salt' => '2350abcd2350dcba',
    'covered_page_numbers' => [1, 2, 3, 4],
    'operation_names' => ['admit_reopened_checkpoint_reader_slots_next232'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next232'],
];

$receipt = static function (string $name, string $kind, array $overrides = []) use ($readerPlan, $databaseDigest, $walDigest): array {
    $path = match ($kind) {
        'database' => $readerPlan['database_path'],
        'wal' => $readerPlan['wal_path'],
        'journal' => $readerPlan['journal_path'],
        'directory' => dirname($readerPlan['database_path']),
    };

    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'path' => $path,
        'source_token' => $readerPlan['source_token'],
        'generation' => $readerPlan['next_writer_generation'],
        'schema_cookie' => $readerPlan['expected_schema_cookie'],
        'wal_salt' => $readerPlan['expected_wal_salt'],
        'digest' => $kind === 'database' ? $databaseDigest : $walDigest,
        'page_numbers' => [1, 2, 3, 4],
        'lock_receipt' => true,
        'synced' => in_array($kind, ['database', 'wal'], true),
        'truncated' => $kind === 'database',
        'deleted' => $kind === 'journal',
        'hot_journal_visible' => false,
        'read_mark_frame' => $kind === 'wal' ? 0 : null,
        'checkpoint_backfill_complete' => $kind === 'wal',
        'directory_synced' => $kind === 'directory',
        'persisted_paths' => [$readerPlan['database_path'], $readerPlan['wal_path'], $readerPlan['journal_path']],
    ], $overrides);
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next235AdmitDurablePublication($readerPlan, [
    $receipt('wp-next235-database', 'database'),
    $receipt('wp-next235-wal', 'wal'),
    $receipt('wp-next235-journal', 'journal'),
    $receipt('wp-next235-directory', 'directory'),
]);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next235',
    'wordpressUse' => 'A copied WordPress import keeps reopened wp_options readers on the current source only after the checkpoint database, restarted WAL, deleted hot journal, and containing directory have matching durable publication receipts.',
    'status' => $plan['status'],
    'publicationAdmitted' => $plan['publication_admitted'],
    'receiptKinds' => $plan['receipt_kinds'],
    'blockedReasons' => $plan['blocked_reasons'],
    'readerAction' => $plan['reader_action'],
    'walAction' => $plan['wal_action'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next235'
        || $summary['publicationAdmitted'] !== true
        || $summary['receiptKinds'] !== ['database', 'directory', 'journal', 'wal']
        || $summary['blockedReasons'] !== []
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next235 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next235 self-test passed\n";
}

return $summary;
