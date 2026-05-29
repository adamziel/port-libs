<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base851 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next851',
    'database_path' => '/srv/www/wp-content/database/wp-next852.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next852.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next852.sqlite-wal',
    'source_token' => 'wp-next852-867-current-source',
    'database_digest' => $digest('next852-867 checkpoint database image'),
    'page_cache_digest' => $digest('next852-867 checkpoint page cache image'),
    'commit_generation' => 852,
    'schema_cookie' => 1852,
    'checkpoint_frame' => 652,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next820_827_next827',
        'seal_after_ready_checkpoint_current_source_next828_835_next835',
        'seal_after_ready_checkpoint_current_source_next836_843_next843',
        'seal_after_ready_checkpoint_current_source_next844_851_next851',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next835',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next851',
    ],
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

$chain = static function () use ($base851, $receiptFor): array {
    $next852 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next852AfterCurrentCheckpoint($base851, [$receiptFor($base851, 'next852-restart-salt-database-digest')]);
    $next853 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next853AfterCurrentCheckpoint($next852, [$receiptFor($next852, 'next853-reader-release-checkpoint-frame')]);
    $next854 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next854AfterCurrentCheckpoint($next853, [$receiptFor($next853, 'next854-page-cache-source-token')]);
    $next855 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next855AfterCurrentCheckpoint($next854, [$receiptFor($next854, 'next855-schema-cookie-database-header')]);
    $next856 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next856AfterCurrentCheckpoint($next855, [$receiptFor($next855, 'next856-commit-generation-wal-index')]);
    $next857 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next857AfterCurrentCheckpoint($next856, [$receiptFor($next856, 'next857-hot-journal-reader-release')]);
    $next858 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next858AfterCurrentCheckpoint($next857, [$receiptFor($next857, 'next858-wal-index-page-cache')]);
    $next859 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next859AfterCurrentCheckpoint($next858, [$receiptFor($next858, 'next859-current-source-seal')]);
    $next860 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next860AfterCurrentCheckpoint($next859, [$receiptFor($next859, 'next860-restart-salt-database-header')]);
    $next861 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next861AfterCurrentCheckpoint($next860, [$receiptFor($next860, 'next861-reader-release-source-token')]);
    $next862 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next862AfterCurrentCheckpoint($next861, [$receiptFor($next861, 'next862-page-cache-database-digest')]);
    $next863 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next863AfterCurrentCheckpoint($next862, [$receiptFor($next862, 'next863-checkpoint-frame-schema-cookie')]);
    $next864 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next864AfterCurrentCheckpoint($next863, [$receiptFor($next863, 'next864-commit-generation-checkpoint-frame')]);
    $next865 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next865AfterCurrentCheckpoint($next864, [$receiptFor($next864, 'next865-hot-journal-page-cache')]);
    $next866 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next866AfterCurrentCheckpoint($next865, [$receiptFor($next865, 'next866-wal-index-reader-release')]);
    $next867 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next867AfterCurrentCheckpoint($next866, [$receiptFor($next866, 'next867-current-source-seal')]);

    return [$next852, $next853, $next854, $next855, $next856, $next857, $next858, $next859, $next860, $next861, $next862, $next863, $next864, $next865, $next866, $next867];
};

$tests['wal hot journal savepoint checkpoint current source next852-867 receives checkpoint handoff from next851'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        852 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        853 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        854 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        855 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        856 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        857 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        858 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        859 => 'seal_after_ready_checkpoint_current_source_next852_859_complete',
        860 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        861 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        862 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        863 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        864 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        865 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        866 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        867 => 'seal_after_ready_checkpoint_current_source_next860_867_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 852];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next852 = $chainRows[0];
    $next867 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next851', $next852['base_status']);
    $t->same(['next867-current-source-seal'], $next867['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next820_827_next827', implode(',', $next867['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next828_835_next835', implode(',', $next867['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next836_843_next843', implode(',', $next867['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next844_851_next851', implode(',', $next867['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next852_859_next859', implode(',', $next867['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next851', implode(',', $next867['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next867', implode(',', $next867['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next852 rejects missing next851 handoff'] = static function (TestRunner $t) use ($base851, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next852AfterCurrentCheckpoint(
        array_replace($base851, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next850']),
        [$receiptFor($base851, 'next852-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next854 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next853] = $chain();
    $receipt = $receiptFor($next853, 'next854-source-token-mismatch');
    $receipt['source_token'] = 'wp-next854-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next854AfterCurrentCheckpoint($next853, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next854', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next857 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next856] = $chain();
    $receipt = $receiptFor($next856, 'next857-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next857AfterCurrentCheckpoint($next856, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next857', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next859 rejects missing next858 base'] = static function (TestRunner $t) use ($base851, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next859AfterCurrentCheckpoint(
        array_replace($base851, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next857']),
        [$receiptFor($base851, 'next859-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next863 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next862] = $chain();
    $receipt = $receiptFor($next862, 'next863-checkpoint-frame-mismatch');
    $receipt['checkpoint_frame']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next863AfterCurrentCheckpoint($next862, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next863', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next867 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next866] = $chain();
    $receipt = $receiptFor($next866, 'next867-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next867AfterCurrentCheckpoint($next866, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next867', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next867-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
