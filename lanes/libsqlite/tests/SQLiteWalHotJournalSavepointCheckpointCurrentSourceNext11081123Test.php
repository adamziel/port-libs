<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base1107 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1107',
    'database_path' => '/srv/www/wp-content/database/wp-next1108.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1108.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1108.sqlite-wal',
    'source_token' => 'wp-next1108-1123-current-source',
    'database_digest' => $digest('next1108-1123 checkpoint database image'),
    'page_cache_digest' => $digest('next1108-1123 checkpoint page cache image'),
    'commit_generation' => 1108,
    'schema_cookie' => 2108,
    'checkpoint_frame' => 908,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1084_1091_next1091',
        'seal_after_ready_checkpoint_current_source_next1092_1099_next1099',
        'seal_after_ready_checkpoint_current_source_next1100_1107_next1107',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1091',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1107',
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

$chain = static function () use ($base1107, $receiptFor): array {
    $next1108 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1108AfterCurrentCheckpoint($base1107, [$receiptFor($base1107, 'next1108-restart-salt-database-header')]);
    $next1109 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1109AfterCurrentCheckpoint($next1108, [$receiptFor($next1108, 'next1109-reader-release-source-token')]);
    $next1110 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1110AfterCurrentCheckpoint($next1109, [$receiptFor($next1109, 'next1110-page-cache-database-digest')]);
    $next1111 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1111AfterCurrentCheckpoint($next1110, [$receiptFor($next1110, 'next1111-checkpoint-frame-schema-cookie')]);
    $next1112 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1112AfterCurrentCheckpoint($next1111, [$receiptFor($next1111, 'next1112-commit-generation-checkpoint-frame')]);
    $next1113 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1113AfterCurrentCheckpoint($next1112, [$receiptFor($next1112, 'next1113-hot-journal-delete-page-cache')]);
    $next1114 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1114AfterCurrentCheckpoint($next1113, [$receiptFor($next1113, 'next1114-wal-index-reader-release')]);
    $next1115 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1115AfterCurrentCheckpoint($next1114, [$receiptFor($next1114, 'next1115-current-source-seal')]);
    $next1116 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1116AfterCurrentCheckpoint($next1115, [$receiptFor($next1115, 'next1116-restart-salt-database-digest')]);
    $next1117 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1117AfterCurrentCheckpoint($next1116, [$receiptFor($next1116, 'next1117-reader-release-checkpoint-frame')]);
    $next1118 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1118AfterCurrentCheckpoint($next1117, [$receiptFor($next1117, 'next1118-page-cache-source-token')]);
    $next1119 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1119AfterCurrentCheckpoint($next1118, [$receiptFor($next1118, 'next1119-schema-cookie-database-header')]);
    $next1120 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1120AfterCurrentCheckpoint($next1119, [$receiptFor($next1119, 'next1120-commit-generation-wal-index')]);
    $next1121 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1121AfterCurrentCheckpoint($next1120, [$receiptFor($next1120, 'next1121-hot-journal-reader-release')]);
    $next1122 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1122AfterCurrentCheckpoint($next1121, [$receiptFor($next1121, 'next1122-wal-index-page-cache')]);
    $next1123 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1123AfterCurrentCheckpoint($next1122, [$receiptFor($next1122, 'next1123-current-source-seal')]);

    return [$next1108, $next1109, $next1110, $next1111, $next1112, $next1113, $next1114, $next1115, $next1116, $next1117, $next1118, $next1119, $next1120, $next1121, $next1122, $next1123];
};

$tests['wal hot journal savepoint checkpoint current source next1108-1123 chains from next1107'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        1108 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        1109 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        1110 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        1111 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        1112 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        1113 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        1114 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        1115 => 'seal_after_ready_checkpoint_current_source_next1108_1115_complete',
        1116 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        1117 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        1118 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        1119 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        1120 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        1121 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        1122 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        1123 => 'seal_after_ready_checkpoint_current_source_next1116_1123_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 1108];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next1108 = $chainRows[0];
    $next1123 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next1107', $next1108['base_status']);
    $t->same(['next1123-current-source-seal'], $next1123['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next1092_1099_next1099', implode(',', $next1123['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1100_1107_next1107', implode(',', $next1123['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1108_1115_next1115', implode(',', $next1123['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1107', implode(',', $next1123['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1123', implode(',', $next1123['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next1108 rejects missing next1107 handoff'] = static function (TestRunner $t) use ($base1107, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1108AfterCurrentCheckpoint(
        array_replace($base1107, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1106']),
        [$receiptFor($base1107, 'next1108-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next1113 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next1112] = $chain();
    $receipt = $receiptFor($next1112, 'next1113-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1113AfterCurrentCheckpoint($next1112, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1113', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1118 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next1117] = $chain();
    $receipt = $receiptFor($next1117, 'next1118-source-token-mismatch');
    $receipt['source_token'] = 'stale-wal-current-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1118AfterCurrentCheckpoint($next1117, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1118', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1123 blocks unsynced wal index salt'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next1122] = $chain();
    $receipt = $receiptFor($next1122, 'next1123-unsynced-wal-index');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1123AfterCurrentCheckpoint($next1122, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1123', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

return $tests;
