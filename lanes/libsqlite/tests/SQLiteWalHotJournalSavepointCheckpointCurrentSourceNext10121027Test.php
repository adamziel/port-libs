<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base1011 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1011',
    'database_path' => '/srv/www/wp-content/database/wp-next1012.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1012.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1012.sqlite-wal',
    'source_token' => 'wp-next1012-1027-current-source',
    'database_digest' => $digest('next1012-1027 checkpoint database image'),
    'page_cache_digest' => $digest('next1012-1027 checkpoint page cache image'),
    'commit_generation' => 1012,
    'schema_cookie' => 2012,
    'checkpoint_frame' => 812,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next988_995_next995',
        'seal_after_ready_checkpoint_current_source_next996_1003_next1003',
        'seal_after_ready_checkpoint_current_source_next1004_1011_next1011',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next995',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1011',
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

$chain = static function () use ($base1011, $receiptFor): array {
    $next1012 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1012AfterCurrentCheckpoint($base1011, [$receiptFor($base1011, 'next1012-restart-salt-database-header')]);
    $next1013 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1013AfterCurrentCheckpoint($next1012, [$receiptFor($next1012, 'next1013-reader-release-source-token')]);
    $next1014 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1014AfterCurrentCheckpoint($next1013, [$receiptFor($next1013, 'next1014-page-cache-database-digest')]);
    $next1015 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1015AfterCurrentCheckpoint($next1014, [$receiptFor($next1014, 'next1015-checkpoint-frame-schema-cookie')]);
    $next1016 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1016AfterCurrentCheckpoint($next1015, [$receiptFor($next1015, 'next1016-commit-generation-checkpoint-frame')]);
    $next1017 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1017AfterCurrentCheckpoint($next1016, [$receiptFor($next1016, 'next1017-hot-journal-delete-page-cache')]);
    $next1018 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1018AfterCurrentCheckpoint($next1017, [$receiptFor($next1017, 'next1018-wal-index-reader-release')]);
    $next1019 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1019AfterCurrentCheckpoint($next1018, [$receiptFor($next1018, 'next1019-current-source-seal')]);
    $next1020 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1020AfterCurrentCheckpoint($next1019, [$receiptFor($next1019, 'next1020-restart-salt-database-digest')]);
    $next1021 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1021AfterCurrentCheckpoint($next1020, [$receiptFor($next1020, 'next1021-reader-release-checkpoint-frame')]);
    $next1022 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1022AfterCurrentCheckpoint($next1021, [$receiptFor($next1021, 'next1022-page-cache-source-token')]);
    $next1023 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1023AfterCurrentCheckpoint($next1022, [$receiptFor($next1022, 'next1023-schema-cookie-database-header')]);
    $next1024 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1024AfterCurrentCheckpoint($next1023, [$receiptFor($next1023, 'next1024-commit-generation-wal-index')]);
    $next1025 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1025AfterCurrentCheckpoint($next1024, [$receiptFor($next1024, 'next1025-hot-journal-reader-release')]);
    $next1026 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1026AfterCurrentCheckpoint($next1025, [$receiptFor($next1025, 'next1026-wal-index-page-cache')]);
    $next1027 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1027AfterCurrentCheckpoint($next1026, [$receiptFor($next1026, 'next1027-current-source-seal')]);

    return [$next1012, $next1013, $next1014, $next1015, $next1016, $next1017, $next1018, $next1019, $next1020, $next1021, $next1022, $next1023, $next1024, $next1025, $next1026, $next1027];
};

$tests['wal hot journal savepoint checkpoint current source next1012-1027 chains from next1011'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        1012 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        1013 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        1014 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        1015 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        1016 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        1017 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        1018 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        1019 => 'seal_after_ready_checkpoint_current_source_next1012_1019_complete',
        1020 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        1021 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        1022 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        1023 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        1024 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        1025 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        1026 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        1027 => 'seal_after_ready_checkpoint_current_source_next1020_1027_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 1012];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next1012 = $chainRows[0];
    $next1027 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next1011', $next1012['base_status']);
    $t->same(['next1027-current-source-seal'], $next1027['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next996_1003_next1003', implode(',', $next1027['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1004_1011_next1011', implode(',', $next1027['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1012_1019_next1019', implode(',', $next1027['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1011', implode(',', $next1027['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1027', implode(',', $next1027['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next1012 rejects missing next1011 handoff'] = static function (TestRunner $t) use ($base1011, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1012AfterCurrentCheckpoint(
        array_replace($base1011, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1010']),
        [$receiptFor($base1011, 'next1012-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next1017 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next1016] = $chain();
    $receipt = $receiptFor($next1016, 'next1017-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1017AfterCurrentCheckpoint($next1016, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1017', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1022 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next1021] = $chain();
    $receipt = $receiptFor($next1021, 'next1022-source-token-mismatch');
    $receipt['source_token'] = 'wp-next1022-stale-current-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1022AfterCurrentCheckpoint($next1021, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1022', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1027 blocks duplicate receipt names'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next1026] = $chain();
    $receipt = $receiptFor($next1026, 'next1027-duplicate-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1027AfterCurrentCheckpoint($next1026, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1027', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next1027-duplicate-seal'], $record['blocked_reasons']);
};

return $tests;
