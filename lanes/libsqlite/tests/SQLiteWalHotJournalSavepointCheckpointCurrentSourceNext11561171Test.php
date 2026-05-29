<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base1155 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1155',
    'database_path' => '/srv/www/wp-content/database/wp-next1156.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1156.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1156.sqlite-wal',
    'source_token' => 'wp-next1156-1171-current-source',
    'database_digest' => $digest('next1156-1171 checkpoint database image'),
    'page_cache_digest' => $digest('next1156-1171 checkpoint page cache image'),
    'commit_generation' => 1156,
    'schema_cookie' => 2156,
    'checkpoint_frame' => 956,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1132_1139_next1139',
        'seal_after_ready_checkpoint_current_source_next1140_1147_next1147',
        'seal_after_ready_checkpoint_current_source_next1148_1155_next1155',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1139',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1155',
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

$chain = static function () use ($base1155, $receiptFor): array {
    $next1156 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1156AfterCurrentCheckpoint($base1155, [$receiptFor($base1155, 'next1156-restart-salt-database-header')]);
    $next1157 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1157AfterCurrentCheckpoint($next1156, [$receiptFor($next1156, 'next1157-reader-release-source-token')]);
    $next1158 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1158AfterCurrentCheckpoint($next1157, [$receiptFor($next1157, 'next1158-page-cache-database-digest')]);
    $next1159 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1159AfterCurrentCheckpoint($next1158, [$receiptFor($next1158, 'next1159-checkpoint-frame-schema-cookie')]);
    $next1160 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1160AfterCurrentCheckpoint($next1159, [$receiptFor($next1159, 'next1160-commit-generation-checkpoint-frame')]);
    $next1161 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1161AfterCurrentCheckpoint($next1160, [$receiptFor($next1160, 'next1161-hot-journal-delete-page-cache')]);
    $next1162 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1162AfterCurrentCheckpoint($next1161, [$receiptFor($next1161, 'next1162-wal-index-reader-release')]);
    $next1163 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1163AfterCurrentCheckpoint($next1162, [$receiptFor($next1162, 'next1163-current-source-seal')]);
    $next1164 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1164AfterCurrentCheckpoint($next1163, [$receiptFor($next1163, 'next1164-restart-salt-database-digest')]);
    $next1165 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1165AfterCurrentCheckpoint($next1164, [$receiptFor($next1164, 'next1165-reader-release-checkpoint-frame')]);
    $next1166 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1166AfterCurrentCheckpoint($next1165, [$receiptFor($next1165, 'next1166-page-cache-source-token')]);
    $next1167 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1167AfterCurrentCheckpoint($next1166, [$receiptFor($next1166, 'next1167-schema-cookie-database-header')]);
    $next1168 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1168AfterCurrentCheckpoint($next1167, [$receiptFor($next1167, 'next1168-commit-generation-wal-index')]);
    $next1169 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1169AfterCurrentCheckpoint($next1168, [$receiptFor($next1168, 'next1169-hot-journal-reader-release')]);
    $next1170 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1170AfterCurrentCheckpoint($next1169, [$receiptFor($next1169, 'next1170-wal-index-page-cache')]);
    $next1171 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1171AfterCurrentCheckpoint($next1170, [$receiptFor($next1170, 'next1171-current-source-seal')]);

    return [$next1156, $next1157, $next1158, $next1159, $next1160, $next1161, $next1162, $next1163, $next1164, $next1165, $next1166, $next1167, $next1168, $next1169, $next1170, $next1171];
};

$tests['wal hot journal savepoint checkpoint current source next1156-1171 chains from next1155'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        1156 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        1157 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        1158 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        1159 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        1160 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        1161 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        1162 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        1163 => 'seal_after_ready_checkpoint_current_source_next1156_1163_complete',
        1164 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        1165 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        1166 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        1167 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        1168 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        1169 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        1170 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        1171 => 'seal_after_ready_checkpoint_current_source_next1164_1171_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 1156];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next1171 = $chainRows[15];
    $t->same(['next1171-current-source-seal'], $next1171['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next1148_1155_next1155', implode(',', $next1171['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1156_1163_next1163', implode(',', $next1171['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1155', implode(',', $next1171['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1171', implode(',', $next1171['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next1156 rejects missing next1155 handoff'] = static function (TestRunner $t) use ($base1155, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1156AfterCurrentCheckpoint(
        array_replace($base1155, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1154']),
        [$receiptFor($base1155, 'next1156-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next1161 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next1160] = $chain();
    $receipt = $receiptFor($next1160, 'next1161-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1161AfterCurrentCheckpoint($next1160, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1161', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1166 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next1165] = $chain();
    $receipt = $receiptFor($next1165, 'next1166-source-token-mismatch');
    $receipt['source_token'] = 'stale-wal-current-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1166AfterCurrentCheckpoint($next1165, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1166', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1171 blocks unsynced wal index salt'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next1170] = $chain();
    $receipt = $receiptFor($next1170, 'next1171-unsynced-wal-index');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1171AfterCurrentCheckpoint($next1170, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1171', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

return $tests;
