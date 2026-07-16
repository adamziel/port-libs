<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base1027 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1027',
    'database_path' => '/srv/www/wp-content/database/wp-next1028.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1028.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1028.sqlite-wal',
    'source_token' => 'wp-next1028-1043-current-source',
    'database_digest' => $digest('next1028-1043 checkpoint database image'),
    'page_cache_digest' => $digest('next1028-1043 checkpoint page cache image'),
    'commit_generation' => 1028,
    'schema_cookie' => 2028,
    'checkpoint_frame' => 828,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1004_1011_next1011',
        'seal_after_ready_checkpoint_current_source_next1012_1019_next1019',
        'seal_after_ready_checkpoint_current_source_next1020_1027_next1027',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1011',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1027',
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

$chain = static function () use ($base1027, $receiptFor): array {
    $next1028 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1028AfterCurrentCheckpoint($base1027, [$receiptFor($base1027, 'next1028-restart-salt-database-header')]);
    $next1029 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1029AfterCurrentCheckpoint($next1028, [$receiptFor($next1028, 'next1029-reader-release-source-token')]);
    $next1030 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1030AfterCurrentCheckpoint($next1029, [$receiptFor($next1029, 'next1030-page-cache-database-digest')]);
    $next1031 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1031AfterCurrentCheckpoint($next1030, [$receiptFor($next1030, 'next1031-checkpoint-frame-schema-cookie')]);
    $next1032 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1032AfterCurrentCheckpoint($next1031, [$receiptFor($next1031, 'next1032-commit-generation-checkpoint-frame')]);
    $next1033 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1033AfterCurrentCheckpoint($next1032, [$receiptFor($next1032, 'next1033-hot-journal-delete-page-cache')]);
    $next1034 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1034AfterCurrentCheckpoint($next1033, [$receiptFor($next1033, 'next1034-wal-index-reader-release')]);
    $next1035 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1035AfterCurrentCheckpoint($next1034, [$receiptFor($next1034, 'next1035-current-source-seal')]);
    $next1036 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1036AfterCurrentCheckpoint($next1035, [$receiptFor($next1035, 'next1036-restart-salt-database-digest')]);
    $next1037 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1037AfterCurrentCheckpoint($next1036, [$receiptFor($next1036, 'next1037-reader-release-checkpoint-frame')]);
    $next1038 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1038AfterCurrentCheckpoint($next1037, [$receiptFor($next1037, 'next1038-page-cache-source-token')]);
    $next1039 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1039AfterCurrentCheckpoint($next1038, [$receiptFor($next1038, 'next1039-schema-cookie-database-header')]);
    $next1040 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1040AfterCurrentCheckpoint($next1039, [$receiptFor($next1039, 'next1040-commit-generation-wal-index')]);
    $next1041 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1041AfterCurrentCheckpoint($next1040, [$receiptFor($next1040, 'next1041-hot-journal-reader-release')]);
    $next1042 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1042AfterCurrentCheckpoint($next1041, [$receiptFor($next1041, 'next1042-wal-index-page-cache')]);
    $next1043 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1043AfterCurrentCheckpoint($next1042, [$receiptFor($next1042, 'next1043-current-source-seal')]);

    return [$next1028, $next1029, $next1030, $next1031, $next1032, $next1033, $next1034, $next1035, $next1036, $next1037, $next1038, $next1039, $next1040, $next1041, $next1042, $next1043];
};

$tests['wal hot journal savepoint checkpoint current source next1028-1043 chains from next1027'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        1028 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        1029 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        1030 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        1031 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        1032 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        1033 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        1034 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        1035 => 'seal_after_ready_checkpoint_current_source_next1028_1035_complete',
        1036 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        1037 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        1038 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        1039 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        1040 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        1041 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        1042 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        1043 => 'seal_after_ready_checkpoint_current_source_next1036_1043_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 1028];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next1028 = $chainRows[0];
    $next1043 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next1027', $next1028['base_status']);
    $t->same(['next1043-current-source-seal'], $next1043['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next1012_1019_next1019', implode(',', $next1043['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1020_1027_next1027', implode(',', $next1043['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1028_1035_next1035', implode(',', $next1043['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1027', implode(',', $next1043['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1043', implode(',', $next1043['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next1028 rejects missing next1027 handoff'] = static function (TestRunner $t) use ($base1027, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1028AfterCurrentCheckpoint(
        array_replace($base1027, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1026']),
        [$receiptFor($base1027, 'next1028-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next1033 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next1032] = $chain();
    $receipt = $receiptFor($next1032, 'next1033-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1033AfterCurrentCheckpoint($next1032, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1033', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1038 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next1037] = $chain();
    $receipt = $receiptFor($next1037, 'next1038-source-token-mismatch');
    $receipt['source_token'] = 'wp-next1038-stale-current-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1038AfterCurrentCheckpoint($next1037, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1038', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1043 blocks duplicate receipt names'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next1042] = $chain();
    $receipt = $receiptFor($next1042, 'next1043-duplicate-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1043AfterCurrentCheckpoint($next1042, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1043', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next1043-duplicate-seal'], $record['blocked_reasons']);
};

return $tests;
