<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$databasePath = '/srv/www/wp-content/database/wp-next211.sqlite';
$hash = static fn (string $value): string => hash('sha256', $value);
$token = ['id' => 'wp-next211-current-source', 'epoch' => 211];
$checkpoint = [
    'database_path' => $databasePath,
    'wal_path' => $databasePath . '-wal',
    'journal_path' => $databasePath . '-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 37,
    'checkpoint_cookie' => 21177,
    'schema_cookie' => 21143,
    'wal_salt' => 'next211-wal-salt',
    'hot_journal_generation' => 27,
    'savepoint_generation' => 29,
    'cache_generation' => 31,
    'page_digests' => [
        1 => $hash('schema after copied plugin checkpoint'),
        2 => $hash('wp_options after copied plugin checkpoint'),
        3 => $hash('autoload index after copied plugin checkpoint'),
    ],
    'checkpoint_published' => true,
    'journal_removed' => true,
];
$reader = static function (string $name, int $page, array $override = []) use ($token, $checkpoint): array {
    return array_replace([
        'name' => $name,
        'page' => $page,
        'source_id' => $token['id'],
        'epoch' => $token['epoch'],
        'observed_checkpoint_frame' => $checkpoint['checkpoint_frame'],
        'observed_checkpoint_cookie' => $checkpoint['checkpoint_cookie'],
        'observed_schema_cookie' => $checkpoint['schema_cookie'],
        'observed_wal_salt' => $checkpoint['wal_salt'],
        'observed_hot_journal_generation' => $checkpoint['hot_journal_generation'],
        'observed_savepoint_generation' => $checkpoint['savepoint_generation'],
        'observed_cache_generation' => $checkpoint['cache_generation'],
        'image_sha256' => $checkpoint['page_digests'][$page],
    ], $override);
};
$readerPlan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointReaderLeasePlan($checkpoint, [
    $reader('wp-schema-reader', 1),
    $reader('wp-options-reader', 2),
    $reader('wp-old-plugin-reader', 2, ['source_id' => 'before-hot-journal-checkpoint']),
    $reader('wp-dirty-index-reader', 3, ['dirty' => true]),
]);
$acks = [];
foreach ($readerPlan['reader_rows'] as $row) {
    $acks[$row['name']] = [
        'source_id' => $readerPlan['current_source_token']['id'],
        'epoch' => $readerPlan['current_source_token']['epoch'],
        'checkpoint_frame' => $readerPlan['checkpoint_frame'],
        'checkpoint_cookie' => $readerPlan['checkpoint_cookie'],
        'schema_cookie' => $readerPlan['schema_cookie'],
        'image_sha256' => $row['observed_image_sha256'],
        'acknowledged' => $row['admitted'],
        'reopen_fenced' => !$row['admitted'],
    ];
}
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerAcknowledgementFencePlan($readerPlan, $acks);
$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next211',
    'applicationUse' => 'A copied Application plugin import publishes a hot-journal checkpoint only after current readers acknowledge matching page images and stale readers are fenced for reopen.',
    'status' => $plan['status'],
    'checkpointAdmitted' => $plan['checkpoint_admitted'],
    'admittedReaders' => $plan['admitted_reader_names'],
    'reopenReaders' => $plan['reopen_reader_names'],
    'nextSourceEpoch' => $plan['next_source_epoch'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next211'
    || $summary['checkpointAdmitted'] !== true
    || $summary['admittedReaders'] !== ['wp-schema-reader', 'wp-options-reader']
    || $summary['reopenReaders'] !== ['wp-old-plugin-reader', 'wp-dirty-index-reader']
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next211 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
