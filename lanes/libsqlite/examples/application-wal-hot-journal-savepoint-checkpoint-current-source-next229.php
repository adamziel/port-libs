<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$hash = static fn (string $value): string => hash('sha256', $value);
$pageDigests = [
    1 => $hash('wp schema after plugin checkpoint'),
    2 => $hash('wp_options after plugin checkpoint'),
    3 => $hash('autoload index after plugin checkpoint'),
];
$publication = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next224',
    'publication_allowed' => true,
    'checkpoint_reset_visible' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next229.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next229.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next229.sqlite-wal',
    'source_token' => 'wp-next229-current-source',
    'next_writer_generation' => 229,
    'database_digest' => $hash('application checkpoint database'),
    'previous_wal_digest' => $hash('application previous wal before checkpoint reset'),
    'operation_names' => ['publish_checkpoint_reset_current_source_next224'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next224'],
];
$handle = static function (string $name, array $pages) use ($publication): array {
    return [
        'name' => $name,
        'source_token' => $publication['source_token'],
        'generation' => $publication['next_writer_generation'],
        'database_digest' => $publication['database_digest'],
        'wal_digest' => hash('sha256', 'application restarted wal after checkpoint reset'),
        'page_digests' => $pages,
        'lock_receipt' => true,
        'sync_receipt' => true,
    ];
};
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next229Verify($publication, [
    $handle('wp-schema-reader-reopened', [1 => $pageDigests[1]]),
    $handle('wp-options-reader-reopened', [2 => $pageDigests[2]]),
    $handle('wp-autoload-index-reopened', [3 => $pageDigests[3]]),
], $pageDigests);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next229',
    'applicationUse' => 'A copied Application import can serve the checkpointed SQLite source only after reopened readers prove clean page images after hot-journal and savepoint cleanup.',
    'status' => $plan['status'],
    'currentSourceAdmitted' => $plan['current_source_admitted'],
    'admittedHandles' => $plan['admitted_handle_names'],
    'coveredPages' => $plan['covered_page_numbers'],
    'readerAction' => $plan['reader_action'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next229'
    || $summary['currentSourceAdmitted'] !== true
    || $summary['admittedHandles'] !== ['wp-schema-reader-reopened', 'wp-options-reader-reopened', 'wp-autoload-index-reopened']
    || $summary['coveredPages'] !== [1, 2, 3]
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next229 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
