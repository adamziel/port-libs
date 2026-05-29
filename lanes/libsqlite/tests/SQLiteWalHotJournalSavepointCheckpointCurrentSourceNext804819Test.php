<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base803 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next803',
    'database_path' => '/srv/www/wp-content/database/wp-next804.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next804.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next804.sqlite-wal',
    'source_token' => 'wp-next804-819-current-source',
    'database_digest' => $digest('next804-819 checkpoint database image'),
    'page_cache_digest' => $digest('next804-819 checkpoint page cache image'),
    'commit_generation' => 804,
    'schema_cookie' => 1804,
    'checkpoint_frame' => 604,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next788_795_next795',
        'seal_after_ready_checkpoint_current_source_next796_803_next803',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next803'],
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

$chain = static function () use ($base803, $receiptFor): array {
    $next804 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next804AfterCurrentCheckpoint($base803, [$receiptFor($base803, 'next804-restart-salt-database-digest')]);
    $next805 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next805AfterCurrentCheckpoint($next804, [$receiptFor($next804, 'next805-reader-release-checkpoint-frame')]);
    $next806 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next806AfterCurrentCheckpoint($next805, [$receiptFor($next805, 'next806-page-cache-source-token')]);
    $next807 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next807AfterCurrentCheckpoint($next806, [$receiptFor($next806, 'next807-schema-cookie-database-header')]);
    $next808 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next808AfterCurrentCheckpoint($next807, [$receiptFor($next807, 'next808-commit-generation-wal-index')]);
    $next809 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next809AfterCurrentCheckpoint($next808, [$receiptFor($next808, 'next809-hot-journal-reader-release')]);
    $next810 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next810AfterCurrentCheckpoint($next809, [$receiptFor($next809, 'next810-wal-index-page-cache')]);
    $next811 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next811AfterCurrentCheckpoint($next810, [$receiptFor($next810, 'next811-current-source-seal')]);
    $next812 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next812AfterCurrentCheckpoint($next811, [$receiptFor($next811, 'next812-restart-salt-database-header')]);
    $next813 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next813AfterCurrentCheckpoint($next812, [$receiptFor($next812, 'next813-reader-release-source-token')]);
    $next814 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next814AfterCurrentCheckpoint($next813, [$receiptFor($next813, 'next814-page-cache-database-digest')]);
    $next815 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next815AfterCurrentCheckpoint($next814, [$receiptFor($next814, 'next815-checkpoint-frame-schema-cookie')]);
    $next816 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next816AfterCurrentCheckpoint($next815, [$receiptFor($next815, 'next816-commit-generation-checkpoint-frame')]);
    $next817 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next817AfterCurrentCheckpoint($next816, [$receiptFor($next816, 'next817-hot-journal-page-cache')]);
    $next818 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next818AfterCurrentCheckpoint($next817, [$receiptFor($next817, 'next818-wal-index-reader-release')]);
    $next819 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next819AfterCurrentCheckpoint($next818, [$receiptFor($next818, 'next819-current-source-seal')]);

    return [$next804, $next805, $next806, $next807, $next808, $next809, $next810, $next811, $next812, $next813, $next814, $next815, $next816, $next817, $next818, $next819];
};

$tests['wal hot journal savepoint checkpoint current source next804-819 receives checkpoint handoff from next803'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        804 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        805 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        806 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        807 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        808 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        809 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        810 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        811 => 'seal_after_ready_checkpoint_current_source_next804_811_complete',
        812 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        813 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        814 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        815 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        816 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        817 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        818 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        819 => 'seal_after_ready_checkpoint_current_source_next812_819_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 804];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next804 = $chainRows[0];
    $next819 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next803', $next804['base_status']);
    $t->same(['next819-current-source-seal'], $next819['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next788_795_next795', implode(',', $next819['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next796_803_next803', implode(',', $next819['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next804_811_next811', implode(',', $next819['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next803', implode(',', $next819['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next819', implode(',', $next819['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next804 rejects missing next803 handoff'] = static function (TestRunner $t) use ($base803, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next804AfterCurrentCheckpoint(
        array_replace($base803, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next802']),
        [$receiptFor($base803, 'next804-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next806 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next805] = $chain();
    $receipt = $receiptFor($next805, 'next806-page-cache-mismatch');
    $receipt['page_cache_digest'] = hash('sha256', 'different next806 page cache');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next806AfterCurrentCheckpoint($next805, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next806', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next809 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next808] = $chain();
    $receipt = $receiptFor($next808, 'next809-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next809AfterCurrentCheckpoint($next808, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next809', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next811 rejects missing next810 base'] = static function (TestRunner $t) use ($base803, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next811AfterCurrentCheckpoint(
        array_replace($base803, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next809']),
        [$receiptFor($base803, 'next811-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next815 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next814] = $chain();
    $receipt = $receiptFor($next814, 'next815-schema-cookie-mismatch');
    $receipt['schema_cookie']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next815AfterCurrentCheckpoint($next814, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next815', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next819 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next818] = $chain();
    $receipt = $receiptFor($next818, 'next819-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next819AfterCurrentCheckpoint($next818, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next819', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next819-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
