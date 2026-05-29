<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base363 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next363',
    'database_path' => '/srv/www/wp-content/database/wp-next364.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next364.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next364.sqlite-wal',
    'source_token' => 'wp-next364-371-current-source',
    'database_digest' => $digest('next364-371 checkpoint database image'),
    'page_cache_digest' => $digest('next364-371 checkpoint page cache image'),
    'commit_generation' => 371,
    'schema_cookie' => 1371,
    'checkpoint_frame' => 171,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next356_363_next363'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next363'],
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

$chain = static function () use ($base363, $receiptFor): array {
    $next364 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next364AfterCurrentCheckpoint(
        $base363,
        [$receiptFor($base363, 'next364-restart-salt-source-receipt')]
    );
    $next365 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next365AfterCurrentCheckpoint(
        $next364,
        [$receiptFor($next364, 'next365-reader-epoch-source-receipt')]
    );
    $next366 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next366AfterCurrentCheckpoint(
        $next365,
        [$receiptFor($next365, 'next366-page-cache-epoch-receipt')]
    );
    $next367 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next367AfterCurrentCheckpoint(
        $next366,
        [$receiptFor($next366, 'next367-schema-cookie-epoch-receipt')]
    );
    $next368 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next368AfterCurrentCheckpoint(
        $next367,
        [$receiptFor($next367, 'next368-commit-generation-epoch-receipt')]
    );
    $next369 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next369AfterCurrentCheckpoint(
        $next368,
        [$receiptFor($next368, 'next369-hot-journal-absence-source-receipt')]
    );
    $next370 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next370AfterCurrentCheckpoint(
        $next369,
        [$receiptFor($next369, 'next370-wal-index-salt-epoch-receipt')]
    );
    $next371 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next371AfterCurrentCheckpoint(
        $next370,
        [$receiptFor($next370, 'next371-current-source-seal')]
    );

    return [$next364, $next365, $next366, $next367, $next368, $next369, $next370, $next371];
};

$tests['wal hot journal savepoint checkpoint current source next364-371 chains after merged next356-363'] = static function (TestRunner $t) use ($chain): void {
    [$next364, $next365, $next366, $next367, $next368, $next369, $next370, $next371] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next364', $next364['status']);
    $t->same('verify_after_ready_checkpoint_restart_salt_source_receipt_complete', $next364['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next365', $next365['status']);
    $t->same('verify_after_ready_checkpoint_reader_epoch_source_receipt_complete', $next365['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next366', $next366['status']);
    $t->same('verify_after_ready_checkpoint_page_cache_epoch_receipt_complete', $next366['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next367', $next367['status']);
    $t->same('verify_after_ready_checkpoint_schema_cookie_epoch_receipt_complete', $next367['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next368', $next368['status']);
    $t->same('verify_after_ready_checkpoint_commit_generation_epoch_receipt_complete', $next368['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next369', $next369['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_absence_source_receipt_complete', $next369['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next370', $next370['status']);
    $t->same('verify_after_ready_checkpoint_wal_index_salt_epoch_receipt_complete', $next370['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next371', $next371['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next364_371_complete', $next371['reason']);
    $t->same(['next371-current-source-seal'], $next371['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_restart_salt_source_receipt_next364', implode(',', $next371['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next363', implode(',', $next371['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next371', implode(',', $next371['dependencies']));
    $t->same(true, $next371['after_current_checkpoint_admitted']);
};

$tests['wal hot journal savepoint checkpoint current source next364 blocks database header not synced'] = static function (TestRunner $t) use ($base363, $receiptFor): void {
    $receipt = $receiptFor($base363, 'next364-unsynced-database-header');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next364AfterCurrentCheckpoint($base363, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next364', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next365 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [$next364] = $chain();
    $receipt = $receiptFor($next364, 'next365-stale-source-token');
    $receipt['source_token'] = 'wp-next365-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next365AfterCurrentCheckpoint($next364, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next365', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next366 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor, $digest): void {
    [, $next365] = $chain();
    $receipt = $receiptFor($next365, 'next366-stale-page-cache');
    $receipt['page_cache_digest'] = $digest('stale page cache for next366');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next366AfterCurrentCheckpoint($next365, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next366', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next369 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next368] = $chain();
    $receipt = $receiptFor($next368, 'next369-visible-hot-journal');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next369AfterCurrentCheckpoint($next368, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next369', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next370 blocks frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , $next369] = $chain();
    $receipt = $receiptFor($next369, 'next370-stale-frame');
    $receipt['checkpoint_frame'] = 170;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next370AfterCurrentCheckpoint($next369, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next370', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next371 rejects missing next370 base'] = static function (TestRunner $t) use ($base363, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next371AfterCurrentCheckpoint(
        array_replace($base363, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next369']),
        [$receiptFor($base363, 'next371-current-source-seal')]
    ));
};

return $tests;
