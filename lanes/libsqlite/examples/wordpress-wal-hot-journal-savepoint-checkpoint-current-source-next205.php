<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$databasePath = '/srv/www/wp-content/database/wp-options-next205.sqlite';
$hash = static fn (string $value): string => hash('sha256', $value);
$checkpoint = [
    'database_path' => $databasePath,
    'wal_path' => $databasePath . '-wal',
    'journal_path' => $databasePath . '-journal',
    'current_source_token' => ['id' => 'wp-options-next205-current', 'epoch' => 205],
    'checkpoint_frame' => 18,
    'checkpoint_cookie' => 4205,
    'schema_cookie' => 77,
    'wal_salt' => 'wp-next205-salt',
    'hot_journal_generation' => 5,
    'savepoint_generation' => 8,
    'cache_generation' => 13,
    'page_digests' => [
        1 => $hash('wp next205 schema page after hot journal recovery'),
        2 => $hash('wp next205 wp_options root after checkpoint'),
        4 => $hash('wp next205 autoload index after checkpoint'),
    ],
    'checkpoint_published' => true,
    'journal_removed' => true,
    'operation_names' => ['admit_current_reader_retry_next195'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next195'],
];
$reader = static function (string $name, int $page, string $digest, array $override = []) use ($checkpoint): array {
    return array_replace([
        'name' => $name,
        'page' => $page,
        'source_id' => $checkpoint['current_source_token']['id'],
        'epoch' => $checkpoint['current_source_token']['epoch'],
        'observed_checkpoint_frame' => $checkpoint['checkpoint_frame'],
        'observed_checkpoint_cookie' => $checkpoint['checkpoint_cookie'],
        'observed_schema_cookie' => $checkpoint['schema_cookie'],
        'observed_wal_salt' => $checkpoint['wal_salt'],
        'observed_hot_journal_generation' => $checkpoint['hot_journal_generation'],
        'observed_savepoint_generation' => $checkpoint['savepoint_generation'],
        'observed_cache_generation' => $checkpoint['cache_generation'],
        'image_sha256' => $digest,
    ], $override);
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointReaderLeasePlan($checkpoint, [
    $reader('wp-options-schema-reader', 1, $checkpoint['page_digests'][1]),
    $reader('wp-options-current-reader', 2, $checkpoint['page_digests'][2]),
    $reader('wp-options-stale-page-reader', 2, $hash('wp next205 stale wp_options root before checkpoint')),
    $reader('wp-options-stale-token-reader', 4, $checkpoint['page_digests'][4], ['source_id' => 'before-hot-journal']),
]);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next205',
    'wordpressUse' => 'After a copied wp_options import recovers a hot rollback journal, releases a savepoint, and publishes a WAL checkpoint, cached page readers are reused only when their current-source token and page image digest match the checkpoint source.',
    'status' => $plan['status'],
    'admittedReaders' => $plan['admitted_reader_names'],
    'reopenReaders' => $plan['reopen_reader_names'],
    'reopenReasons' => $plan['reopen_reasons'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if (
        $summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next205'
        || $summary['admittedReaders'] !== ['wp-options-schema-reader', 'wp-options-current-reader']
        || $summary['reopenReaders'] !== ['wp-options-stale-page-reader', 'wp-options-stale-token-reader']
        || !in_array('page_image', $summary['reopenReasons'], true)
        || !in_array('source_token', $summary['reopenReasons'], true)
    ) {
        fwrite(STDERR, "wordpress WAL hot-journal checkpoint reader cache next205 smoke failed\n");
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
