<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base1043 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1043',
    'database_path' => '/srv/www/wp-content/database/wp-next1044.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1044.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1044.sqlite-wal',
    'source_token' => 'wp-next1044-1059-current-source',
    'database_digest' => $digest('next1044-1059 checkpoint database image'),
    'page_cache_digest' => $digest('next1044-1059 checkpoint page cache image'),
    'commit_generation' => 1044,
    'schema_cookie' => 2044,
    'checkpoint_frame' => 844,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1020_1027_next1027',
        'seal_after_ready_checkpoint_current_source_next1028_1035_next1035',
        'seal_after_ready_checkpoint_current_source_next1036_1043_next1043',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1027',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1043',
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

$chain = static function () use ($base1043, $receiptFor): array {
    $next1044 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1044AfterCurrentCheckpoint($base1043, [$receiptFor($base1043, 'next1044-restart-salt-database-header')]);
    $next1045 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1045AfterCurrentCheckpoint($next1044, [$receiptFor($next1044, 'next1045-reader-release-source-token')]);
    $next1046 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1046AfterCurrentCheckpoint($next1045, [$receiptFor($next1045, 'next1046-page-cache-database-digest')]);
    $next1047 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1047AfterCurrentCheckpoint($next1046, [$receiptFor($next1046, 'next1047-checkpoint-frame-schema-cookie')]);
    $next1048 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1048AfterCurrentCheckpoint($next1047, [$receiptFor($next1047, 'next1048-commit-generation-checkpoint-frame')]);
    $next1049 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1049AfterCurrentCheckpoint($next1048, [$receiptFor($next1048, 'next1049-hot-journal-delete-page-cache')]);
    $next1050 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1050AfterCurrentCheckpoint($next1049, [$receiptFor($next1049, 'next1050-wal-index-reader-release')]);
    $next1051 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1051AfterCurrentCheckpoint($next1050, [$receiptFor($next1050, 'next1051-current-source-seal')]);
    $next1052 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1052AfterCurrentCheckpoint($next1051, [$receiptFor($next1051, 'next1052-restart-salt-database-digest')]);
    $next1053 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1053AfterCurrentCheckpoint($next1052, [$receiptFor($next1052, 'next1053-reader-release-checkpoint-frame')]);
    $next1054 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1054AfterCurrentCheckpoint($next1053, [$receiptFor($next1053, 'next1054-page-cache-source-token')]);
    $next1055 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1055AfterCurrentCheckpoint($next1054, [$receiptFor($next1054, 'next1055-schema-cookie-database-header')]);
    $next1056 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1056AfterCurrentCheckpoint($next1055, [$receiptFor($next1055, 'next1056-commit-generation-wal-index')]);
    $next1057 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1057AfterCurrentCheckpoint($next1056, [$receiptFor($next1056, 'next1057-hot-journal-reader-release')]);
    $next1058 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1058AfterCurrentCheckpoint($next1057, [$receiptFor($next1057, 'next1058-wal-index-page-cache')]);
    $next1059 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1059AfterCurrentCheckpoint($next1058, [$receiptFor($next1058, 'next1059-current-source-seal')]);

    return [$next1044, $next1045, $next1046, $next1047, $next1048, $next1049, $next1050, $next1051, $next1052, $next1053, $next1054, $next1055, $next1056, $next1057, $next1058, $next1059];
};

$tests['wal hot journal savepoint checkpoint current source next1044-1059 chains from next1043'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        1044 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        1045 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        1046 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        1047 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        1048 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        1049 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        1050 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        1051 => 'seal_after_ready_checkpoint_current_source_next1044_1051_complete',
        1052 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        1053 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        1054 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        1055 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        1056 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        1057 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        1058 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        1059 => 'seal_after_ready_checkpoint_current_source_next1052_1059_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 1044];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next1044 = $chainRows[0];
    $next1059 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next1043', $next1044['base_status']);
    $t->same(['next1059-current-source-seal'], $next1059['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next1028_1035_next1035', implode(',', $next1059['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1036_1043_next1043', implode(',', $next1059['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1044_1051_next1051', implode(',', $next1059['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1043', implode(',', $next1059['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1059', implode(',', $next1059['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next1044 rejects missing next1043 handoff'] = static function (TestRunner $t) use ($base1043, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1044AfterCurrentCheckpoint(
        array_replace($base1043, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1042']),
        [$receiptFor($base1043, 'next1044-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next1049 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next1048] = $chain();
    $receipt = $receiptFor($next1048, 'next1049-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1049AfterCurrentCheckpoint($next1048, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1049', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1054 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next1053] = $chain();
    $receipt = $receiptFor($next1053, 'next1054-source-token-mismatch');
    $receipt['source_token'] = 'wp-next1054-stale-current-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1054AfterCurrentCheckpoint($next1053, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1054', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1059 blocks duplicate receipt names'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next1058] = $chain();
    $receipt = $receiptFor($next1058, 'next1059-duplicate-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1059AfterCurrentCheckpoint($next1058, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1059', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next1059-duplicate-seal'], $record['blocked_reasons']);
};

return $tests;
