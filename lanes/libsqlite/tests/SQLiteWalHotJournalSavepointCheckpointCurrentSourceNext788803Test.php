<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base787 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next787',
    'database_path' => '/srv/www/wp-content/database/wp-next788.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next788.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next788.sqlite-wal',
    'source_token' => 'wp-next788-803-current-source',
    'database_digest' => $digest('next788-803 checkpoint database image'),
    'page_cache_digest' => $digest('next788-803 checkpoint page cache image'),
    'commit_generation' => 788,
    'schema_cookie' => 1788,
    'checkpoint_frame' => 588,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next772_779_next779',
        'seal_after_ready_checkpoint_current_source_next780_787_next787',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next787'],
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

$chain = static function () use ($base787, $receiptFor): array {
    $next788 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next788AfterCurrentCheckpoint($base787, [$receiptFor($base787, 'next788-restart-salt-database-digest')]);
    $next789 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next789AfterCurrentCheckpoint($next788, [$receiptFor($next788, 'next789-reader-release-checkpoint-frame')]);
    $next790 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next790AfterCurrentCheckpoint($next789, [$receiptFor($next789, 'next790-page-cache-source-token')]);
    $next791 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next791AfterCurrentCheckpoint($next790, [$receiptFor($next790, 'next791-schema-cookie-database-header')]);
    $next792 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next792AfterCurrentCheckpoint($next791, [$receiptFor($next791, 'next792-commit-generation-wal-index')]);
    $next793 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next793AfterCurrentCheckpoint($next792, [$receiptFor($next792, 'next793-hot-journal-reader-release')]);
    $next794 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next794AfterCurrentCheckpoint($next793, [$receiptFor($next793, 'next794-wal-index-page-cache')]);
    $next795 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next795AfterCurrentCheckpoint($next794, [$receiptFor($next794, 'next795-current-source-seal')]);
    $next796 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next796AfterCurrentCheckpoint($next795, [$receiptFor($next795, 'next796-restart-salt-database-header')]);
    $next797 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next797AfterCurrentCheckpoint($next796, [$receiptFor($next796, 'next797-reader-release-source-token')]);
    $next798 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next798AfterCurrentCheckpoint($next797, [$receiptFor($next797, 'next798-page-cache-database-digest')]);
    $next799 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next799AfterCurrentCheckpoint($next798, [$receiptFor($next798, 'next799-checkpoint-frame-schema-cookie')]);
    $next800 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next800AfterCurrentCheckpoint($next799, [$receiptFor($next799, 'next800-commit-generation-checkpoint-frame')]);
    $next801 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next801AfterCurrentCheckpoint($next800, [$receiptFor($next800, 'next801-hot-journal-page-cache')]);
    $next802 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next802AfterCurrentCheckpoint($next801, [$receiptFor($next801, 'next802-wal-index-reader-release')]);
    $next803 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next803AfterCurrentCheckpoint($next802, [$receiptFor($next802, 'next803-current-source-seal')]);

    return [$next788, $next789, $next790, $next791, $next792, $next793, $next794, $next795, $next796, $next797, $next798, $next799, $next800, $next801, $next802, $next803];
};

$tests['wal hot journal savepoint checkpoint current source next788-803 receives checkpoint handoff from next787'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        788 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        789 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        790 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        791 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        792 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        793 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        794 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        795 => 'seal_after_ready_checkpoint_current_source_next788_795_complete',
        796 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        797 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        798 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        799 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        800 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        801 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        802 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        803 => 'seal_after_ready_checkpoint_current_source_next796_803_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 788];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next788 = $chainRows[0];
    $next803 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next787', $next788['base_status']);
    $t->same(['next803-current-source-seal'], $next803['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next780_787_next787', implode(',', $next803['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next788_795_next795', implode(',', $next803['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next787', implode(',', $next803['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next803', implode(',', $next803['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next788 rejects missing next787 handoff'] = static function (TestRunner $t) use ($base787, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next788AfterCurrentCheckpoint(
        array_replace($base787, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next786']),
        [$receiptFor($base787, 'next788-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next790 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next789] = $chain();
    $receipt = $receiptFor($next789, 'next790-source-token-mismatch');
    $receipt['source_token'] = 'wp-next788-803-different-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next790AfterCurrentCheckpoint($next789, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next790', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next793 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next792] = $chain();
    $receipt = $receiptFor($next792, 'next793-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next793AfterCurrentCheckpoint($next792, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next793', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next795 rejects missing next794 base'] = static function (TestRunner $t) use ($base787, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next795AfterCurrentCheckpoint(
        array_replace($base787, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next793']),
        [$receiptFor($base787, 'next795-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next799 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next798] = $chain();
    $receipt = $receiptFor($next798, 'next799-checkpoint-frame-mismatch');
    $receipt['checkpoint_frame']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next799AfterCurrentCheckpoint($next798, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next799', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next803 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next802] = $chain();
    $receipt = $receiptFor($next802, 'next803-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next803AfterCurrentCheckpoint($next802, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next803', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next803-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
