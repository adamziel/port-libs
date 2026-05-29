<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('wordpress next232 checkpoint database');
$previousWalDigest = $hash('wordpress next232 previous wal generation');
$currentWalDigest = $hash('wordpress next232 current wal generation');
$schemaCookie = 23277;
$walSalt = '2320abcd2320dcba';
$handlePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next229',
    'current_source_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next232.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next232.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next232.sqlite-wal',
    'source_token' => 'wp-next232-current-source',
    'next_writer_generation' => 232,
    'database_digest' => $databaseDigest,
    'previous_wal_digest' => $previousWalDigest,
    'expected_page_numbers' => [1, 2, 3],
    'covered_page_numbers' => [1, 2, 3],
    'operation_names' => ['admit_checkpoint_current_source_next229'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next229'],
];

$slot = static function (string $name, array $pages) use ($handlePlan, $databaseDigest, $currentWalDigest, $schemaCookie, $walSalt): array {
    return [
        'name' => $name,
        'source_token' => $handlePlan['source_token'],
        'generation' => $handlePlan['next_writer_generation'],
        'database_digest' => $databaseDigest,
        'wal_digest' => $currentWalDigest,
        'schema_cookie' => $schemaCookie,
        'wal_salt' => $walSalt,
        'page_numbers' => $pages,
        'read_mark_frame' => 0,
        'lock_receipt' => true,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots(
    $handlePlan,
    [
        $slot('wp-schema-reader-slot', [1]),
        $slot('wp-options-reader-slot', [2]),
        $slot('wp-autoload-reader-slot', [3]),
    ],
    $schemaCookie,
    $walSalt
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next232',
    'wordpressUse' => 'A copied WordPress import reopens reader slots after hot-journal recovery and savepoint checkpoint publication, then serves checkpoint pages only after schema-cookie, WAL-salt, generation, digest, and lock receipts match.',
    'status' => $plan['status'],
    'readable' => $plan['current_source_readable'],
    'admittedSlots' => $plan['admitted_reader_slot_names'],
    'blockedSlots' => $plan['blocked_reader_slot_names'],
    'schemaCookie' => $plan['expected_schema_cookie'],
    'walSalt' => $plan['expected_wal_salt'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next232'
        || $summary['readable'] !== true
        || $summary['admittedSlots'] !== ['wp-schema-reader-slot', 'wp-options-reader-slot', 'wp-autoload-reader-slot']
        || $summary['blockedSlots'] !== []
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next232 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next232 self-test passed\n";
}

return $summary;
