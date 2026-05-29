<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base835 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next835',
    'database_path' => '/srv/www/wp-content/database/wp-next836.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next836.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next836.sqlite-wal',
    'source_token' => 'wp-next836-851-current-source',
    'database_digest' => $digest('next836-851 checkpoint database image'),
    'page_cache_digest' => $digest('next836-851 checkpoint page cache image'),
    'commit_generation' => 836,
    'schema_cookie' => 1836,
    'checkpoint_frame' => 636,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next804_811_next811',
        'seal_after_ready_checkpoint_current_source_next812_819_next819',
        'seal_after_ready_checkpoint_current_source_next820_827_next827',
        'seal_after_ready_checkpoint_current_source_next828_835_next835',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next819',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next835',
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

$chain = static function () use ($base835, $receiptFor): array {
    $next836 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next836AfterCurrentCheckpoint($base835, [$receiptFor($base835, 'next836-restart-salt-database-digest')]);
    $next837 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next837AfterCurrentCheckpoint($next836, [$receiptFor($next836, 'next837-reader-release-checkpoint-frame')]);
    $next838 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next838AfterCurrentCheckpoint($next837, [$receiptFor($next837, 'next838-page-cache-source-token')]);
    $next839 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next839AfterCurrentCheckpoint($next838, [$receiptFor($next838, 'next839-schema-cookie-database-header')]);
    $next840 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next840AfterCurrentCheckpoint($next839, [$receiptFor($next839, 'next840-commit-generation-wal-index')]);
    $next841 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next841AfterCurrentCheckpoint($next840, [$receiptFor($next840, 'next841-hot-journal-reader-release')]);
    $next842 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next842AfterCurrentCheckpoint($next841, [$receiptFor($next841, 'next842-wal-index-page-cache')]);
    $next843 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next843AfterCurrentCheckpoint($next842, [$receiptFor($next842, 'next843-current-source-seal')]);
    $next844 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next844AfterCurrentCheckpoint($next843, [$receiptFor($next843, 'next844-restart-salt-database-header')]);
    $next845 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next845AfterCurrentCheckpoint($next844, [$receiptFor($next844, 'next845-reader-release-source-token')]);
    $next846 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next846AfterCurrentCheckpoint($next845, [$receiptFor($next845, 'next846-page-cache-database-digest')]);
    $next847 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next847AfterCurrentCheckpoint($next846, [$receiptFor($next846, 'next847-checkpoint-frame-schema-cookie')]);
    $next848 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next848AfterCurrentCheckpoint($next847, [$receiptFor($next847, 'next848-commit-generation-checkpoint-frame')]);
    $next849 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next849AfterCurrentCheckpoint($next848, [$receiptFor($next848, 'next849-hot-journal-page-cache')]);
    $next850 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next850AfterCurrentCheckpoint($next849, [$receiptFor($next849, 'next850-wal-index-reader-release')]);
    $next851 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next851AfterCurrentCheckpoint($next850, [$receiptFor($next850, 'next851-current-source-seal')]);

    return [$next836, $next837, $next838, $next839, $next840, $next841, $next842, $next843, $next844, $next845, $next846, $next847, $next848, $next849, $next850, $next851];
};

$tests['wal hot journal savepoint checkpoint current source next836-851 receives checkpoint handoff from next835'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        836 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        837 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        838 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        839 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        840 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        841 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        842 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        843 => 'seal_after_ready_checkpoint_current_source_next836_843_complete',
        844 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        845 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        846 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        847 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        848 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        849 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        850 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        851 => 'seal_after_ready_checkpoint_current_source_next844_851_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 836];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next836 = $chainRows[0];
    $next851 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next835', $next836['base_status']);
    $t->same(['next851-current-source-seal'], $next851['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next820_827_next827', implode(',', $next851['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next828_835_next835', implode(',', $next851['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next836_843_next843', implode(',', $next851['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next835', implode(',', $next851['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next851', implode(',', $next851['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next836 rejects missing next835 handoff'] = static function (TestRunner $t) use ($base835, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next836AfterCurrentCheckpoint(
        array_replace($base835, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next834']),
        [$receiptFor($base835, 'next836-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next838 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next837] = $chain();
    $receipt = $receiptFor($next837, 'next838-page-cache-mismatch');
    $receipt['page_cache_digest'] = hash('sha256', 'stale next838 page cache');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next838AfterCurrentCheckpoint($next837, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next838', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next841 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next840] = $chain();
    $receipt = $receiptFor($next840, 'next841-reader-marks-pinned');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next841AfterCurrentCheckpoint($next840, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next841', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next843 rejects missing next842 base'] = static function (TestRunner $t) use ($base835, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next843AfterCurrentCheckpoint(
        array_replace($base835, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next841']),
        [$receiptFor($base835, 'next843-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next847 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next846] = $chain();
    $receipt = $receiptFor($next846, 'next847-schema-cookie-mismatch');
    $receipt['schema_cookie']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next847AfterCurrentCheckpoint($next846, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next847', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next851 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next850] = $chain();
    $receipt = $receiptFor($next850, 'next851-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next851AfterCurrentCheckpoint($next850, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next851', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next851-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
