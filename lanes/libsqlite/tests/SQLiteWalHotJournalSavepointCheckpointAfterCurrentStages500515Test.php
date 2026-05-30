<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base499 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next499',
    'database_path' => '/srv/www/wp-content/database/wp-next500.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next500.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next500.sqlite-wal',
    'source_token' => 'wp-next500-515-current-source',
    'database_digest' => $digest('next500-515 checkpoint database image'),
    'page_cache_digest' => $digest('next500-515 checkpoint page cache image'),
    'commit_generation' => 515,
    'schema_cookie' => 1515,
    'checkpoint_frame' => 315,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next492_499_next499'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next499'],
];

$receiptFor = static function (array $base, string $name): array {
    return [
        'name' => $name,
        'source_token' => $base['source_token'],
        'database_digest' => $base['database_digest'],
        'page_cache_digest' => $base['page_cache_digest'],
        'commit_generation' => $base['commit_generation'],
        'schema_cookie' => $base['schema_cookie'],
        'checkpoint_frame' => $base['checkpoint_frame'],
        'database_header_synced' => true,
        'wal_index_salt_synced' => true,
        'reader_marks_released' => true,
        'hot_journal_visible' => false,
    ];
};

$chain = static function () use ($base499, $receiptFor): array {
    $next500 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next500AfterCurrentCheckpoint($base499, [$receiptFor($base499, 'next500-restart-salt-database-header')]);
    $next501 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next501AfterCurrentCheckpoint($next500, [$receiptFor($next500, 'next501-reader-mark-wal-index-salt')]);
    $next502 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next502AfterCurrentCheckpoint($next501, [$receiptFor($next501, 'next502-page-cache-reader-release')]);
    $next503 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next503AfterCurrentCheckpoint($next502, [$receiptFor($next502, 'next503-schema-cookie-hot-journal')]);
    $next504 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next504AfterCurrentCheckpoint($next503, [$receiptFor($next503, 'next504-generation-database-header')]);
    $next505 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next505AfterCurrentCheckpoint($next504, [$receiptFor($next504, 'next505-hot-journal-wal-index-salt')]);
    $next506 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next506AfterCurrentCheckpoint($next505, [$receiptFor($next505, 'next506-wal-index-reader-release')]);
    $next507 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next507AfterCurrentCheckpoint($next506, [$receiptFor($next506, 'next507-current-source-seal')]);
    $next508 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next508AfterCurrentCheckpoint($next507, [$receiptFor($next507, 'next508-restart-salt-source-frame')]);
    $next509 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next509AfterCurrentCheckpoint($next508, [$receiptFor($next508, 'next509-reader-mark-source-frame')]);
    $next510 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next510AfterCurrentCheckpoint($next509, [$receiptFor($next509, 'next510-page-cache-database-header')]);
    $next511 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next511AfterCurrentCheckpoint($next510, [$receiptFor($next510, 'next511-schema-cookie-wal-index-salt')]);
    $next512 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next512AfterCurrentCheckpoint($next511, [$receiptFor($next511, 'next512-generation-reader-release')]);
    $next513 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next513AfterCurrentCheckpoint($next512, [$receiptFor($next512, 'next513-hot-journal-database-header')]);
    $next514 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next513, [$receiptFor($next513, 'next514-wal-index-page-cache')], 514);
    $next515 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next514, [$receiptFor($next514, 'next515-current-source-seal')], 515);

    return [$next500, $next501, $next502, $next503, $next504, $next505, $next506, $next507, $next508, $next509, $next510, $next511, $next512, $next513, $next514, $next515];
};

$tests['wal hot journal savepoint checkpoint current source next500-515 chains after merged next484-499'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        500 => 'verify_after_ready_checkpoint_restart_salt_receipt_database_header_complete',
        501 => 'verify_after_ready_checkpoint_reader_mark_release_wal_index_salt_complete',
        502 => 'verify_after_ready_checkpoint_page_cache_digest_reader_release_complete',
        503 => 'verify_after_ready_checkpoint_schema_cookie_hot_journal_absence_complete',
        504 => 'verify_after_ready_checkpoint_commit_generation_database_header_complete',
        505 => 'verify_after_ready_checkpoint_hot_journal_delete_wal_index_salt_complete',
        506 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        507 => 'seal_after_ready_checkpoint_current_source_next500_507_complete',
        508 => 'verify_after_ready_checkpoint_restart_salt_receipt_source_frame_complete',
        509 => 'verify_after_ready_checkpoint_reader_mark_release_source_frame_complete',
        510 => 'verify_after_ready_checkpoint_page_cache_digest_database_header_complete',
        511 => 'verify_after_ready_checkpoint_schema_cookie_wal_index_salt_complete',
        512 => 'verify_after_ready_checkpoint_commit_generation_reader_release_complete',
        513 => 'verify_after_ready_checkpoint_hot_journal_absence_database_header_complete',
        514 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_digest_complete',
        515 => 'seal_after_ready_checkpoint_current_source_next508_515_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 500];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next515 = $chainRows[15];
    $t->same(['next515-current-source-seal'], $next515['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next492_499_next499', implode(',', $next515['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next500_507_next507', implode(',', $next515['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next499', implode(',', $next515['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next515', implode(',', $next515['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next500 blocks source token mismatch'] = static function (TestRunner $t) use ($base499, $receiptFor): void {
    $receipt = $receiptFor($base499, 'next500-stale-source-token');
    $receipt['source_token'] = 'wp-next500-515-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next500AfterCurrentCheckpoint($base499, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next500', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next502 blocks reader marks not released'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next501] = $chain();
    $receipt = $receiptFor($next501, 'next502-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next502AfterCurrentCheckpoint($next501, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next502', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next507 rejects missing next506 base'] = static function (TestRunner $t) use ($base499, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next507AfterCurrentCheckpoint(
        array_replace($base499, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next505']),
        [$receiptFor($base499, 'next507-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next510 blocks unsynced database header'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next509] = $chain();
    $receipt = $receiptFor($next509, 'next510-database-header-not-synced');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next510AfterCurrentCheckpoint($next509, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next510', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next511 blocks wal index salt unsynced'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next510] = $chain();
    $receipt = $receiptFor($next510, 'next511-wal-index-salt-not-synced');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next511AfterCurrentCheckpoint($next510, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next511', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next513 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , $next512] = $chain();
    $receipt = $receiptFor($next512, 'next513-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next513AfterCurrentCheckpoint($next512, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next513', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next515 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next514] = $chain();
    $receipt = $receiptFor($next514, 'next515-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next514, [$receipt, $receipt], 515);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next515', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next515-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
