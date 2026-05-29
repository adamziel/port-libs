<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base995 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next995',
    'database_path' => '/srv/www/wp-content/database/wp-next996.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next996.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next996.sqlite-wal',
    'source_token' => 'wp-next996-1011-current-source',
    'database_digest' => $digest('next996-1011 checkpoint database image'),
    'page_cache_digest' => $digest('next996-1011 checkpoint page cache image'),
    'commit_generation' => 996,
    'schema_cookie' => 1996,
    'checkpoint_frame' => 796,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next972_979_next979',
        'seal_after_ready_checkpoint_current_source_next980_987_next987',
        'seal_after_ready_checkpoint_current_source_next988_995_next995',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next979',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next995',
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

$chain = static function () use ($base995, $receiptFor): array {
    $next996 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next996AfterCurrentCheckpoint($base995, [$receiptFor($base995, 'next996-restart-salt-database-header')]);
    $next997 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next997AfterCurrentCheckpoint($next996, [$receiptFor($next996, 'next997-reader-release-source-token')]);
    $next998 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next998AfterCurrentCheckpoint($next997, [$receiptFor($next997, 'next998-page-cache-database-digest')]);
    $next999 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next999AfterCurrentCheckpoint($next998, [$receiptFor($next998, 'next999-checkpoint-frame-schema-cookie')]);
    $next1000 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1000AfterCurrentCheckpoint($next999, [$receiptFor($next999, 'next1000-commit-generation-checkpoint-frame')]);
    $next1001 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1001AfterCurrentCheckpoint($next1000, [$receiptFor($next1000, 'next1001-hot-journal-delete-page-cache')]);
    $next1002 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1002AfterCurrentCheckpoint($next1001, [$receiptFor($next1001, 'next1002-wal-index-reader-release')]);
    $next1003 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1003AfterCurrentCheckpoint($next1002, [$receiptFor($next1002, 'next1003-current-source-seal')]);
    $next1004 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1004AfterCurrentCheckpoint($next1003, [$receiptFor($next1003, 'next1004-restart-salt-database-digest')]);
    $next1005 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1005AfterCurrentCheckpoint($next1004, [$receiptFor($next1004, 'next1005-reader-release-checkpoint-frame')]);
    $next1006 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1006AfterCurrentCheckpoint($next1005, [$receiptFor($next1005, 'next1006-page-cache-source-token')]);
    $next1007 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1007AfterCurrentCheckpoint($next1006, [$receiptFor($next1006, 'next1007-schema-cookie-database-header')]);
    $next1008 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1008AfterCurrentCheckpoint($next1007, [$receiptFor($next1007, 'next1008-commit-generation-wal-index')]);
    $next1009 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1009AfterCurrentCheckpoint($next1008, [$receiptFor($next1008, 'next1009-hot-journal-reader-release')]);
    $next1010 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1010AfterCurrentCheckpoint($next1009, [$receiptFor($next1009, 'next1010-wal-index-page-cache')]);
    $next1011 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1011AfterCurrentCheckpoint($next1010, [$receiptFor($next1010, 'next1011-current-source-seal')]);

    return [$next996, $next997, $next998, $next999, $next1000, $next1001, $next1002, $next1003, $next1004, $next1005, $next1006, $next1007, $next1008, $next1009, $next1010, $next1011];
};

$tests['wal hot journal savepoint checkpoint current source next996-1011 chains from next995'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        996 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        997 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        998 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        999 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        1000 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        1001 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        1002 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        1003 => 'seal_after_ready_checkpoint_current_source_next996_1003_complete',
        1004 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        1005 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        1006 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        1007 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        1008 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        1009 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        1010 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        1011 => 'seal_after_ready_checkpoint_current_source_next1004_1011_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 996];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next996 = $chainRows[0];
    $next1011 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next995', $next996['base_status']);
    $t->same(['next1011-current-source-seal'], $next1011['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next980_987_next987', implode(',', $next1011['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next988_995_next995', implode(',', $next1011['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next996_1003_next1003', implode(',', $next1011['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next995', implode(',', $next1011['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1011', implode(',', $next1011['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next996 rejects missing next995 handoff'] = static function (TestRunner $t) use ($base995, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next996AfterCurrentCheckpoint(
        array_replace($base995, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next994']),
        [$receiptFor($base995, 'next996-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next1001 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next1000] = $chain();
    $receipt = $receiptFor($next1000, 'next1001-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1001AfterCurrentCheckpoint($next1000, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1001', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1006 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next1005] = $chain();
    $receipt = $receiptFor($next1005, 'next1006-source-token-mismatch');
    $receipt['source_token'] = 'wp-next1006-stale-current-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1006AfterCurrentCheckpoint($next1005, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1006', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1011 blocks duplicate receipt names'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next1010] = $chain();
    $receipt = $receiptFor($next1010, 'next1011-duplicate-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1011AfterCurrentCheckpoint($next1010, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1011', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next1011-duplicate-seal'], $record['blocked_reasons']);
};

return $tests;
