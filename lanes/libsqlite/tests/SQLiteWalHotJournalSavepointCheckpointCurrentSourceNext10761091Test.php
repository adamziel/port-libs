<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base1075 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1075',
    'database_path' => '/srv/www/wp-content/database/wp-next1076.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1076.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1076.sqlite-wal',
    'source_token' => 'wp-next1076-1091-current-source',
    'database_digest' => $digest('next1076-1091 checkpoint database image'),
    'page_cache_digest' => $digest('next1076-1091 checkpoint page cache image'),
    'commit_generation' => 1076,
    'schema_cookie' => 2076,
    'checkpoint_frame' => 876,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1052_1059_next1059',
        'seal_after_ready_checkpoint_current_source_next1060_1067_next1067',
        'seal_after_ready_checkpoint_current_source_next1068_1075_next1075',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1059',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1075',
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

$chain = static function () use ($base1075, $receiptFor): array {
    $next1076 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1076AfterCurrentCheckpoint($base1075, [$receiptFor($base1075, 'next1076-restart-salt-database-header')]);
    $next1077 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1077AfterCurrentCheckpoint($next1076, [$receiptFor($next1076, 'next1077-reader-release-source-token')]);
    $next1078 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1078AfterCurrentCheckpoint($next1077, [$receiptFor($next1077, 'next1078-page-cache-database-digest')]);
    $next1079 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1079AfterCurrentCheckpoint($next1078, [$receiptFor($next1078, 'next1079-checkpoint-frame-schema-cookie')]);
    $next1080 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1080AfterCurrentCheckpoint($next1079, [$receiptFor($next1079, 'next1080-commit-generation-checkpoint-frame')]);
    $next1081 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1081AfterCurrentCheckpoint($next1080, [$receiptFor($next1080, 'next1081-hot-journal-delete-page-cache')]);
    $next1082 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1082AfterCurrentCheckpoint($next1081, [$receiptFor($next1081, 'next1082-wal-index-reader-release')]);
    $next1083 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1083AfterCurrentCheckpoint($next1082, [$receiptFor($next1082, 'next1083-current-source-seal')]);
    $next1084 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1084AfterCurrentCheckpoint($next1083, [$receiptFor($next1083, 'next1084-restart-salt-database-digest')]);
    $next1085 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1085AfterCurrentCheckpoint($next1084, [$receiptFor($next1084, 'next1085-reader-release-checkpoint-frame')]);
    $next1086 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1086AfterCurrentCheckpoint($next1085, [$receiptFor($next1085, 'next1086-page-cache-source-token')]);
    $next1087 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1087AfterCurrentCheckpoint($next1086, [$receiptFor($next1086, 'next1087-schema-cookie-database-header')]);
    $next1088 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1088AfterCurrentCheckpoint($next1087, [$receiptFor($next1087, 'next1088-commit-generation-wal-index')]);
    $next1089 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1089AfterCurrentCheckpoint($next1088, [$receiptFor($next1088, 'next1089-hot-journal-reader-release')]);
    $next1090 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1090AfterCurrentCheckpoint($next1089, [$receiptFor($next1089, 'next1090-wal-index-page-cache')]);
    $next1091 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1091AfterCurrentCheckpoint($next1090, [$receiptFor($next1090, 'next1091-current-source-seal')]);

    return [$next1076, $next1077, $next1078, $next1079, $next1080, $next1081, $next1082, $next1083, $next1084, $next1085, $next1086, $next1087, $next1088, $next1089, $next1090, $next1091];
};

$tests['wal hot journal savepoint checkpoint current source next1076-1091 chains from next1075'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        1076 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        1077 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        1078 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        1079 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        1080 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        1081 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        1082 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        1083 => 'seal_after_ready_checkpoint_current_source_next1076_1083_complete',
        1084 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        1085 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        1086 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        1087 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        1088 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        1089 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        1090 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        1091 => 'seal_after_ready_checkpoint_current_source_next1084_1091_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 1076];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next1076 = $chainRows[0];
    $next1091 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next1075', $next1076['base_status']);
    $t->same(['next1091-current-source-seal'], $next1091['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next1060_1067_next1067', implode(',', $next1091['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1068_1075_next1075', implode(',', $next1091['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1076_1083_next1083', implode(',', $next1091['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1075', implode(',', $next1091['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1091', implode(',', $next1091['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next1076 rejects missing next1075 handoff'] = static function (TestRunner $t) use ($base1075, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1076AfterCurrentCheckpoint(
        array_replace($base1075, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1074']),
        [$receiptFor($base1075, 'next1076-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next1081 blocks unsynced database header'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next1080] = $chain();
    $receipt = $receiptFor($next1080, 'next1081-database-header-unsynced');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1081AfterCurrentCheckpoint($next1080, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1081', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1086 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next1085] = $chain();
    $receipt = $receiptFor($next1085, 'next1086-page-cache-mismatch');
    $receipt['page_cache_digest'] = hash('sha256', 'stale page cache image');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1086AfterCurrentCheckpoint($next1085, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1086', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1091 blocks duplicate receipt names'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next1090] = $chain();
    $receipt = $receiptFor($next1090, 'next1091-duplicate-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1091AfterCurrentCheckpoint($next1090, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1091', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next1091-duplicate-seal'], $record['blocked_reasons']);
};

return $tests;
