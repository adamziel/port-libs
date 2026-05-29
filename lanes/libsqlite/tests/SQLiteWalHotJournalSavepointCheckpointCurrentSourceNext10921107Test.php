<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base1091 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1091',
    'database_path' => '/srv/www/wp-content/database/wp-next1092.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1092.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1092.sqlite-wal',
    'source_token' => 'wp-next1092-1107-current-source',
    'database_digest' => $digest('next1092-1107 checkpoint database image'),
    'page_cache_digest' => $digest('next1092-1107 checkpoint page cache image'),
    'commit_generation' => 1092,
    'schema_cookie' => 2092,
    'checkpoint_frame' => 892,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1068_1075_next1075',
        'seal_after_ready_checkpoint_current_source_next1076_1083_next1083',
        'seal_after_ready_checkpoint_current_source_next1084_1091_next1091',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1075',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1091',
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

$chain = static function () use ($base1091, $receiptFor): array {
    $next1092 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1092AfterCurrentCheckpoint($base1091, [$receiptFor($base1091, 'next1092-restart-salt-database-header')]);
    $next1093 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1093AfterCurrentCheckpoint($next1092, [$receiptFor($next1092, 'next1093-reader-release-source-token')]);
    $next1094 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1094AfterCurrentCheckpoint($next1093, [$receiptFor($next1093, 'next1094-page-cache-database-digest')]);
    $next1095 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1095AfterCurrentCheckpoint($next1094, [$receiptFor($next1094, 'next1095-checkpoint-frame-schema-cookie')]);
    $next1096 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1096AfterCurrentCheckpoint($next1095, [$receiptFor($next1095, 'next1096-commit-generation-checkpoint-frame')]);
    $next1097 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1097AfterCurrentCheckpoint($next1096, [$receiptFor($next1096, 'next1097-hot-journal-delete-page-cache')]);
    $next1098 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1098AfterCurrentCheckpoint($next1097, [$receiptFor($next1097, 'next1098-wal-index-reader-release')]);
    $next1099 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1099AfterCurrentCheckpoint($next1098, [$receiptFor($next1098, 'next1099-current-source-seal')]);
    $next1100 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1100AfterCurrentCheckpoint($next1099, [$receiptFor($next1099, 'next1100-restart-salt-database-digest')]);
    $next1101 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1101AfterCurrentCheckpoint($next1100, [$receiptFor($next1100, 'next1101-reader-release-checkpoint-frame')]);
    $next1102 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1102AfterCurrentCheckpoint($next1101, [$receiptFor($next1101, 'next1102-page-cache-source-token')]);
    $next1103 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1103AfterCurrentCheckpoint($next1102, [$receiptFor($next1102, 'next1103-schema-cookie-database-header')]);
    $next1104 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1104AfterCurrentCheckpoint($next1103, [$receiptFor($next1103, 'next1104-commit-generation-wal-index')]);
    $next1105 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1105AfterCurrentCheckpoint($next1104, [$receiptFor($next1104, 'next1105-hot-journal-reader-release')]);
    $next1106 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1106AfterCurrentCheckpoint($next1105, [$receiptFor($next1105, 'next1106-wal-index-page-cache')]);
    $next1107 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1107AfterCurrentCheckpoint($next1106, [$receiptFor($next1106, 'next1107-current-source-seal')]);

    return [$next1092, $next1093, $next1094, $next1095, $next1096, $next1097, $next1098, $next1099, $next1100, $next1101, $next1102, $next1103, $next1104, $next1105, $next1106, $next1107];
};

$tests['wal hot journal savepoint checkpoint current source next1092-1107 chains from next1091'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        1092 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        1093 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        1094 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        1095 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        1096 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        1097 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        1098 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        1099 => 'seal_after_ready_checkpoint_current_source_next1092_1099_complete',
        1100 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        1101 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        1102 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        1103 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        1104 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        1105 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        1106 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        1107 => 'seal_after_ready_checkpoint_current_source_next1100_1107_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 1092];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next1092 = $chainRows[0];
    $next1107 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next1091', $next1092['base_status']);
    $t->same(['next1107-current-source-seal'], $next1107['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next1076_1083_next1083', implode(',', $next1107['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1084_1091_next1091', implode(',', $next1107['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1092_1099_next1099', implode(',', $next1107['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1091', implode(',', $next1107['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1107', implode(',', $next1107['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next1092 rejects missing next1091 handoff'] = static function (TestRunner $t) use ($base1091, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1092AfterCurrentCheckpoint(
        array_replace($base1091, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1090']),
        [$receiptFor($base1091, 'next1092-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next1097 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next1096] = $chain();
    $receipt = $receiptFor($next1096, 'next1097-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1097AfterCurrentCheckpoint($next1096, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1097', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1102 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next1101] = $chain();
    $receipt = $receiptFor($next1101, 'next1102-source-token-mismatch');
    $receipt['source_token'] = 'stale-wal-current-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1102AfterCurrentCheckpoint($next1101, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1102', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1107 blocks unsynced wal index salt'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next1106] = $chain();
    $receipt = $receiptFor($next1106, 'next1107-unsynced-wal-index');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1107AfterCurrentCheckpoint($next1106, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1107', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

return $tests;
