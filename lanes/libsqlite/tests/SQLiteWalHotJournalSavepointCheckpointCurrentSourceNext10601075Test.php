<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base1059 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1059',
    'database_path' => '/srv/www/wp-content/database/wp-next1060.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1060.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1060.sqlite-wal',
    'source_token' => 'wp-next1060-1075-current-source',
    'database_digest' => $digest('next1060-1075 checkpoint database image'),
    'page_cache_digest' => $digest('next1060-1075 checkpoint page cache image'),
    'commit_generation' => 1060,
    'schema_cookie' => 2060,
    'checkpoint_frame' => 860,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1036_1043_next1043',
        'seal_after_ready_checkpoint_current_source_next1044_1051_next1051',
        'seal_after_ready_checkpoint_current_source_next1052_1059_next1059',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1043',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1059',
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

$chain = static function () use ($base1059, $receiptFor): array {
    $next1060 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1060AfterCurrentCheckpoint($base1059, [$receiptFor($base1059, 'next1060-restart-salt-database-header')]);
    $next1061 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1061AfterCurrentCheckpoint($next1060, [$receiptFor($next1060, 'next1061-reader-release-source-token')]);
    $next1062 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1062AfterCurrentCheckpoint($next1061, [$receiptFor($next1061, 'next1062-page-cache-database-digest')]);
    $next1063 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1063AfterCurrentCheckpoint($next1062, [$receiptFor($next1062, 'next1063-checkpoint-frame-schema-cookie')]);
    $next1064 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1064AfterCurrentCheckpoint($next1063, [$receiptFor($next1063, 'next1064-commit-generation-checkpoint-frame')]);
    $next1065 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1065AfterCurrentCheckpoint($next1064, [$receiptFor($next1064, 'next1065-hot-journal-delete-page-cache')]);
    $next1066 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1066AfterCurrentCheckpoint($next1065, [$receiptFor($next1065, 'next1066-wal-index-reader-release')]);
    $next1067 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1067AfterCurrentCheckpoint($next1066, [$receiptFor($next1066, 'next1067-current-source-seal')]);
    $next1068 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1068AfterCurrentCheckpoint($next1067, [$receiptFor($next1067, 'next1068-restart-salt-database-digest')]);
    $next1069 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1069AfterCurrentCheckpoint($next1068, [$receiptFor($next1068, 'next1069-reader-release-checkpoint-frame')]);
    $next1070 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1070AfterCurrentCheckpoint($next1069, [$receiptFor($next1069, 'next1070-page-cache-source-token')]);
    $next1071 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1071AfterCurrentCheckpoint($next1070, [$receiptFor($next1070, 'next1071-schema-cookie-database-header')]);
    $next1072 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1072AfterCurrentCheckpoint($next1071, [$receiptFor($next1071, 'next1072-commit-generation-wal-index')]);
    $next1073 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1073AfterCurrentCheckpoint($next1072, [$receiptFor($next1072, 'next1073-hot-journal-reader-release')]);
    $next1074 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1074AfterCurrentCheckpoint($next1073, [$receiptFor($next1073, 'next1074-wal-index-page-cache')]);
    $next1075 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1075AfterCurrentCheckpoint($next1074, [$receiptFor($next1074, 'next1075-current-source-seal')]);

    return [$next1060, $next1061, $next1062, $next1063, $next1064, $next1065, $next1066, $next1067, $next1068, $next1069, $next1070, $next1071, $next1072, $next1073, $next1074, $next1075];
};

$tests['wal hot journal savepoint checkpoint current source next1060-1075 chains from next1059'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        1060 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        1061 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        1062 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        1063 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        1064 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        1065 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        1066 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        1067 => 'seal_after_ready_checkpoint_current_source_next1060_1067_complete',
        1068 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        1069 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        1070 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        1071 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        1072 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        1073 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        1074 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        1075 => 'seal_after_ready_checkpoint_current_source_next1068_1075_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 1060];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next1060 = $chainRows[0];
    $next1075 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next1059', $next1060['base_status']);
    $t->same(['next1075-current-source-seal'], $next1075['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next1044_1051_next1051', implode(',', $next1075['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1052_1059_next1059', implode(',', $next1075['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1060_1067_next1067', implode(',', $next1075['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1059', implode(',', $next1075['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1075', implode(',', $next1075['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next1060 rejects missing next1059 handoff'] = static function (TestRunner $t) use ($base1059, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1060AfterCurrentCheckpoint(
        array_replace($base1059, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1058']),
        [$receiptFor($base1059, 'next1060-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next1065 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next1064] = $chain();
    $receipt = $receiptFor($next1064, 'next1065-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1065AfterCurrentCheckpoint($next1064, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1065', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1070 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next1069] = $chain();
    $receipt = $receiptFor($next1069, 'next1070-source-token-mismatch');
    $receipt['source_token'] = 'wp-next1070-stale-current-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1070AfterCurrentCheckpoint($next1069, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1070', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1075 blocks duplicate receipt names'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next1074] = $chain();
    $receipt = $receiptFor($next1074, 'next1075-duplicate-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1075AfterCurrentCheckpoint($next1074, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1075', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next1075-duplicate-seal'], $record['blocked_reasons']);
};

return $tests;
