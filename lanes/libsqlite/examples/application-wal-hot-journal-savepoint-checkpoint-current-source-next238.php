<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('application next238 checkpoint database image');
$publication = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next235',
    'publication_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next238.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next238.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next238.sqlite-journal',
    'source_token' => 'wp-next238-current-source',
    'next_writer_generation' => 238,
    'database_digest' => $databaseDigest,
    'expected_schema_cookie' => 23877,
    'expected_wal_salt' => '2380abcd2380dcba',
    'covered_page_numbers' => [1, 2, 3, 4, 5],
    'operation_names' => ['admit_durable_reopened_current_source_next235'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next235'],
];

$reader = static function (string $name, array $pages) use ($publication, $databaseDigest): array {
    return [
        'name' => $name,
        'database_path' => $publication['database_path'],
        'wal_path' => $publication['wal_path'],
        'journal_path' => $publication['journal_path'],
        'source_token' => $publication['source_token'],
        'generation' => $publication['next_writer_generation'],
        'observed_database_digest' => $databaseDigest,
        'observed_schema_cookie' => $publication['expected_schema_cookie'],
        'observed_wal_salt' => $publication['expected_wal_salt'],
        'observed_wal_frame' => 0,
        'observed_page_numbers' => $pages,
        'hot_journal_visible' => false,
        'shared_lock' => true,
        'dirty_page_cache' => false,
        'wal_header_restarted' => true,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next238AdmitNextWriter($publication, [
    $reader('wp-next238-options-reader', [1, 2, 3, 4]),
    $reader('wp-next238-schema-reader', [1]),
]);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next238',
    'applicationUse' => 'A Application import admits the next writer only after reopened wp_options readers observe the published database digest, zero-frame restarted WAL, absent hot journal, and shared read locks.',
    'status' => $plan['status'],
    'writerAdmitted' => $plan['writer_admitted'],
    'writerAction' => $plan['writer_action'],
    'readerNames' => $plan['reader_names'],
    'blockedReasons' => $plan['blocked_reasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next238'
        || $summary['writerAdmitted'] !== true
        || $summary['writerAction'] !== 'start_writer_generation_239'
        || $summary['blockedReasons'] !== []
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next238 self-test failed\n");
        exit(1);
    }

    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next238 self-test passed\n";
}

return $summary;
