<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('application next255 checkpointed wp_options image');
$pageCacheDigest = $hash('application next255 clean wp_options cache');
$resetPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next251',
    'wal_reset_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp.sqlite-wal',
    'source_token' => 'wp-next255-current-source',
    'commit_generation' => 255,
    'checkpoint_frame' => 42,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'next_wal_salt' => ['wp-next255-salt-a', 'wp-next255-salt-b'],
    'accepted_reader_names' => ['front-reader', 'import-reader', 'object-cache-reader'],
    'released_reader_names' => ['front-reader', 'import-reader', 'object-cache-reader'],
    'operation_names' => ['admit_wal_sidecar_reset_next251'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next251'],
];

$receipt = static function (string $name, string $readerName, int $slot) use ($resetPlan, $databaseDigest, $pageCacheDigest): array {
    return [
        'name' => $name,
        'reader_name' => $readerName,
        'readmark_slot' => $slot,
        'database_path' => $resetPlan['database_path'],
        'wal_path' => $resetPlan['wal_path'],
        'source_token' => $resetPlan['source_token'],
        'commit_generation' => $resetPlan['commit_generation'],
        'checkpoint_frame' => $resetPlan['checkpoint_frame'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'wal_salt' => $resetPlan['next_wal_salt'],
        'wal_size' => 32,
        'mx_frame' => 0,
        'visible_frame_count' => 0,
        'hot_journal_visible' => false,
        'clean_page_cache' => true,
        'read_transaction_open' => true,
        'io_error' => null,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next255AdmitRestartedWalReaders($resetPlan, [
    $receipt('front-reopen', 'front-reader', 1),
    $receipt('import-reopen', 'import-reader', 2),
    $receipt('object-cache-reopen', 'object-cache-reader', 3),
]);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['restarted_reader_admitted'] === true);
    assert($plan['reader_action'] === 'serve_readers_from_checkpoint_database_with_empty_restarted_wal');
    assert($plan['readmark_slots'] === [1, 2, 3]);
    assert($plan['blocked_reader_reasons'] === []);
    echo "application wal hot journal savepoint checkpoint current source next255 smoke passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
