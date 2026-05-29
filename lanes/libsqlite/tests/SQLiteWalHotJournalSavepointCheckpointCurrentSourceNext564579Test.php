<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base563 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next563',
    'database_path' => '/srv/www/wp-content/database/wp-next564.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next564.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next564.sqlite-wal',
    'source_token' => 'wp-next564-579-current-source',
    'database_digest' => $digest('next564-579 checkpoint database image'),
    'page_cache_digest' => $digest('next564-579 checkpoint page cache image'),
    'commit_generation' => 563,
    'schema_cookie' => 1563,
    'checkpoint_frame' => 363,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next548_555_next555',
        'seal_after_ready_checkpoint_current_source_next556_563_next563',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next563'],
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

$chain = static function () use ($base563, $receiptFor): array {
    $next564 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next564AfterCurrentCheckpoint($base563, [$receiptFor($base563, 'next564-restart-salt-reader-release')]);
    $next565 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next565AfterCurrentCheckpoint($next564, [$receiptFor($next564, 'next565-source-token-page-cache')]);
    $next566 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next566AfterCurrentCheckpoint($next565, [$receiptFor($next565, 'next566-database-digest-schema-cookie')]);
    $next567 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next567AfterCurrentCheckpoint($next566, [$receiptFor($next566, 'next567-checkpoint-frame-database-header')]);
    $next568 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next568AfterCurrentCheckpoint($next567, [$receiptFor($next567, 'next568-commit-generation-wal-index')]);
    $next569 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next569AfterCurrentCheckpoint($next568, [$receiptFor($next568, 'next569-hot-journal-reader-release')]);
    $next570 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next570AfterCurrentCheckpoint($next569, [$receiptFor($next569, 'next570-page-cache-wal-index')]);
    $next571 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next571AfterCurrentCheckpoint($next570, [$receiptFor($next570, 'next571-current-source-seal')]);
    $next572 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next572AfterCurrentCheckpoint($next571, [$receiptFor($next571, 'next572-restart-salt-database-header')]);
    $next573 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next573AfterCurrentCheckpoint($next572, [$receiptFor($next572, 'next573-reader-mark-checkpoint-frame')]);
    $next574 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next574AfterCurrentCheckpoint($next573, [$receiptFor($next573, 'next574-database-digest-commit-generation')]);
    $next575 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next575AfterCurrentCheckpoint($next574, [$receiptFor($next574, 'next575-schema-cookie-reader-release')]);
    $next576 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next576AfterCurrentCheckpoint($next575, [$receiptFor($next575, 'next576-page-cache-source-token')]);
    $next577 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next577AfterCurrentCheckpoint($next576, [$receiptFor($next576, 'next577-hot-journal-database-digest')]);
    $next578 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next578AfterCurrentCheckpoint($next577, [$receiptFor($next577, 'next578-wal-index-checkpoint-frame')]);
    $next579 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next579AfterCurrentCheckpoint($next578, [$receiptFor($next578, 'next579-current-source-seal')]);

    return [$next564, $next565, $next566, $next567, $next568, $next569, $next570, $next571, $next572, $next573, $next574, $next575, $next576, $next577, $next578, $next579];
};

$tests['wal hot journal savepoint checkpoint current source next564-579 chains after merged next548-563'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        564 => 'verify_after_ready_checkpoint_restart_salt_reader_release_complete',
        565 => 'verify_after_ready_checkpoint_source_token_page_cache_digest_complete',
        566 => 'verify_after_ready_checkpoint_database_digest_schema_cookie_complete',
        567 => 'verify_after_ready_checkpoint_checkpoint_frame_database_header_complete',
        568 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        569 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        570 => 'verify_after_ready_checkpoint_page_cache_digest_wal_index_salt_complete',
        571 => 'seal_after_ready_checkpoint_current_source_next564_571_complete',
        572 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        573 => 'verify_after_ready_checkpoint_reader_mark_checkpoint_frame_complete',
        574 => 'verify_after_ready_checkpoint_database_digest_commit_generation_complete',
        575 => 'verify_after_ready_checkpoint_schema_cookie_reader_release_complete',
        576 => 'verify_after_ready_checkpoint_page_cache_digest_source_token_complete',
        577 => 'verify_after_ready_checkpoint_hot_journal_delete_database_digest_complete',
        578 => 'verify_after_ready_checkpoint_wal_index_salt_checkpoint_frame_complete',
        579 => 'seal_after_ready_checkpoint_current_source_next572_579_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 564];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next579 = $chainRows[15];
    $t->same(['next579-current-source-seal'], $next579['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next556_563_next563', implode(',', $next579['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next564_571_next571', implode(',', $next579['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next563', implode(',', $next579['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next579', implode(',', $next579['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next564 blocks reader marks not released'] = static function (TestRunner $t) use ($base563, $receiptFor): void {
    $receipt = $receiptFor($base563, 'next564-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next564AfterCurrentCheckpoint($base563, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next564', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next566 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next565] = $chain();
    $receipt = $receiptFor($next565, 'next566-stale-schema-cookie');
    $receipt['schema_cookie'] = 1562;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next566AfterCurrentCheckpoint($next565, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next566', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next571 rejects missing next570 base'] = static function (TestRunner $t) use ($base563, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next571AfterCurrentCheckpoint(
        array_replace($base563, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next569']),
        [$receiptFor($base563, 'next571-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next572 blocks unsynced wal index salt'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , $next571] = $chain();
    $receipt = $receiptFor($next571, 'next572-wal-index-not-synced');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next572AfterCurrentCheckpoint($next571, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next572', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next577 blocks database digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , $next576] = $chain();
    $receipt = $receiptFor($next576, 'next577-stale-database-digest');
    $receipt['database_digest'] = hash('sha256', 'stale database image');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next577AfterCurrentCheckpoint($next576, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next577', $record['status']);
    $t->same(['checkpoint_database_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next579 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next578] = $chain();
    $receipt = $receiptFor($next578, 'next579-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next579AfterCurrentCheckpoint($next578, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next579', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next579-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
