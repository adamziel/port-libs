<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);

$writerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next209',
    'database_path' => '/srv/www/wp-content/database/wp.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp.sqlite-wal',
    'page_size' => 512,
    'minimum_statement_generation' => 212,
    'next_writer_generation' => 215,
    'checkpointed_database_digest' => $digest('checkpointed wp_options database'),
    'expected_wal_digest' => $digest('retained wp_options wal'),
    'writer_digest' => $digest('post checkpoint writer fence'),
    'admitted_writer_names' => ['wp_options_importer'],
    'reopen_writer_names' => ['stale_plugin_cache_writer'],
    'operation_names' => ['verify_post_checkpoint_writer_generation_current_source_next209'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next209'],
];

$passive = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint(
    $writerPlan,
    [
        [
            'name' => 'theme-preview-reader',
            'reader_end_frame' => 213,
            'reader_generation' => 215,
            'observed_database_digest' => $writerPlan['checkpointed_database_digest'],
            'observed_wal_digest' => $writerPlan['expected_wal_digest'],
            'observed_writer_digest' => $writerPlan['writer_digest'],
        ],
        [
            'name' => 'stale-plugin-reader',
            'reader_end_frame' => 211,
            'reader_generation' => 214,
            'observed_database_digest' => $digest('old database image'),
            'observed_wal_digest' => $digest('old wal image'),
            'observed_writer_digest' => $digest('old writer image'),
        ],
    ],
    215
);

$drained = $passive;
$drained['active_reader_names'] = [];
$drained['checkpointed_frame'] = 215;
$drained['busy'] = false;

$restart = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartOrTruncateCheckpointAfterReaderReopen(
    $drained,
    [
        [
            'name' => 'stale-plugin-reader',
            'reader_end_frame' => 215,
            'reader_generation' => 215,
            'observed_database_digest' => $writerPlan['checkpointed_database_digest'],
            'observed_wal_digest' => $writerPlan['expected_wal_digest'],
            'observed_writer_digest' => $writerPlan['writer_digest'],
            'reopened' => true,
        ],
    ],
    'restart'
);

echo json_encode([
    'status' => $restart['status'],
    'wal_action' => $restart['wal_action'],
    'database_action' => $restart['database_action'],
    'new_current_source_epoch' => $restart['new_current_source_epoch'],
    'admitted_reopen_reader_names' => $restart['admitted_reopen_reader_names'],
    'dependency_closure' => $restart['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
