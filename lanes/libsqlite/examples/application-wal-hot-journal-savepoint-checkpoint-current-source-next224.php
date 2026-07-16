<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('application next224 checkpointed wp_options database');
$walDigest = $digest('application next224 wal before truncate');
$zeroDigest = $digest('');

$reset = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next218',
    'mode' => 'truncate',
    'database_path' => '/srv/www/wp-content/database/application.sqlite',
    'journal_path' => '/srv/www/wp-content/database/application.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/application.sqlite-wal',
    'can_reset_wal' => true,
    'database_digest' => $databaseDigest,
    'wal_digest' => $walDigest,
    'next_writer_generation' => 224,
    'operation_names' => ['publish_wal_reset_current_source_next218'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next218'],
];

$sidecars = [
    [
        'name' => 'application-db',
        'type' => 'database',
        'path' => '/srv/www/wp-content/database/application.sqlite',
        'generation' => 224,
        'exists' => true,
        'deleted' => false,
        'size' => 8192,
        'digest' => $databaseDigest,
        'sync_receipt' => true,
    ],
    [
        'name' => 'application-wal',
        'type' => 'wal',
        'path' => '/srv/www/wp-content/database/application.sqlite-wal',
        'generation' => 224,
        'exists' => true,
        'deleted' => false,
        'size' => 0,
        'digest' => $zeroDigest,
        'sync_receipt' => true,
    ],
    [
        'name' => 'application-hot-journal',
        'type' => 'journal',
        'path' => '/srv/www/wp-content/database/application.sqlite-journal',
        'generation' => 0,
        'exists' => false,
        'deleted' => true,
        'size' => 0,
        'digest' => $zeroDigest,
        'sync_receipt' => false,
    ],
    [
        'name' => 'application-shm',
        'type' => 'shm',
        'path' => '/srv/www/wp-content/database/application.sqlite-shm',
        'generation' => 224,
        'exists' => false,
        'deleted' => true,
        'size' => 0,
        'digest' => $zeroDigest,
        'sync_receipt' => false,
    ],
];

$readers = [
    [
        'name' => 'wp-options-reader',
        'source_token' => 'next224:wp-import-current-source',
        'generation' => 224,
        'reopened' => true,
        'invalidated' => false,
        'pinned' => false,
    ],
    [
        'name' => 'wp-settings-cache-reader',
        'source_token' => 'next218:pre-reset-source',
        'generation' => 223,
        'reopened' => false,
        'invalidated' => true,
        'pinned' => false,
    ],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, $sidecars, $readers, 'next224:wp-import-current-source');

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next224');
    assert($plan['publication_allowed'] === true);
    assert($plan['wal_publication_action'] === 'publish_zero_length_wal_generation');
    assert(in_array('application-import-checkpoint-reset-reader-reopen-publication', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next224 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next224',
    'status' => $plan['status'],
    'publicationAllowed' => $plan['publication_allowed'],
    'walPublicationAction' => $plan['wal_publication_action'],
    'admittedReaders' => $plan['admitted_reader_names'],
    'applicationUse' => 'A copied wp_options import publishes a TRUNCATE checkpoint reset only after database/WAL/journal/SHM receipts and reader reopen or invalidation receipts agree on the new current source.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
