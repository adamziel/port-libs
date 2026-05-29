<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base819 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next819',
    'database_path' => '/srv/www/wp-content/database/wp-next820.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next820.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next820.sqlite-wal',
    'source_token' => 'wp-next820-835-current-source',
    'database_digest' => $digest('next820-835 checkpoint database image'),
    'page_cache_digest' => $digest('next820-835 checkpoint page cache image'),
    'commit_generation' => 820,
    'schema_cookie' => 1820,
    'checkpoint_frame' => 620,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next804_811_next811',
        'seal_after_ready_checkpoint_current_source_next812_819_next819',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next819'],
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

$chain = static function () use ($base819, $receiptFor): array {
    $next820 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next820AfterCurrentCheckpoint($base819, [$receiptFor($base819, 'next820-restart-salt-database-digest')]);
    $next821 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next821AfterCurrentCheckpoint($next820, [$receiptFor($next820, 'next821-reader-release-checkpoint-frame')]);
    $next822 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next822AfterCurrentCheckpoint($next821, [$receiptFor($next821, 'next822-page-cache-source-token')]);
    $next823 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next823AfterCurrentCheckpoint($next822, [$receiptFor($next822, 'next823-schema-cookie-database-header')]);
    $next824 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next824AfterCurrentCheckpoint($next823, [$receiptFor($next823, 'next824-commit-generation-wal-index')]);
    $next825 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next825AfterCurrentCheckpoint($next824, [$receiptFor($next824, 'next825-hot-journal-reader-release')]);
    $next826 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next826AfterCurrentCheckpoint($next825, [$receiptFor($next825, 'next826-wal-index-page-cache')]);
    $next827 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next827AfterCurrentCheckpoint($next826, [$receiptFor($next826, 'next827-current-source-seal')]);
    $next828 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next828AfterCurrentCheckpoint($next827, [$receiptFor($next827, 'next828-restart-salt-database-header')]);
    $next829 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next829AfterCurrentCheckpoint($next828, [$receiptFor($next828, 'next829-reader-release-source-token')]);
    $next830 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next830AfterCurrentCheckpoint($next829, [$receiptFor($next829, 'next830-page-cache-database-digest')]);
    $next831 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next831AfterCurrentCheckpoint($next830, [$receiptFor($next830, 'next831-checkpoint-frame-schema-cookie')]);
    $next832 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next832AfterCurrentCheckpoint($next831, [$receiptFor($next831, 'next832-commit-generation-checkpoint-frame')]);
    $next833 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next833AfterCurrentCheckpoint($next832, [$receiptFor($next832, 'next833-hot-journal-page-cache')]);
    $next834 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next834AfterCurrentCheckpoint($next833, [$receiptFor($next833, 'next834-wal-index-reader-release')]);
    $next835 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next835AfterCurrentCheckpoint($next834, [$receiptFor($next834, 'next835-current-source-seal')]);

    return [$next820, $next821, $next822, $next823, $next824, $next825, $next826, $next827, $next828, $next829, $next830, $next831, $next832, $next833, $next834, $next835];
};

$tests['wal hot journal savepoint checkpoint current source next820-835 receives checkpoint handoff from next819'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        820 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        821 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        822 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        823 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        824 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        825 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        826 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        827 => 'seal_after_ready_checkpoint_current_source_next820_827_complete',
        828 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        829 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        830 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        831 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        832 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        833 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        834 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        835 => 'seal_after_ready_checkpoint_current_source_next828_835_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 820];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next820 = $chainRows[0];
    $next835 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next819', $next820['base_status']);
    $t->same(['next835-current-source-seal'], $next835['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next804_811_next811', implode(',', $next835['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next812_819_next819', implode(',', $next835['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next820_827_next827', implode(',', $next835['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next819', implode(',', $next835['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next835', implode(',', $next835['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next820 rejects missing next819 handoff'] = static function (TestRunner $t) use ($base819, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next820AfterCurrentCheckpoint(
        array_replace($base819, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next818']),
        [$receiptFor($base819, 'next820-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next822 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next821] = $chain();
    $receipt = $receiptFor($next821, 'next822-source-token-mismatch');
    $receipt['source_token'] = 'wp-next822-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next822AfterCurrentCheckpoint($next821, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next822', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next825 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next824] = $chain();
    $receipt = $receiptFor($next824, 'next825-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next825AfterCurrentCheckpoint($next824, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next825', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next827 rejects missing next826 base'] = static function (TestRunner $t) use ($base819, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next827AfterCurrentCheckpoint(
        array_replace($base819, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next825']),
        [$receiptFor($base819, 'next827-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next831 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next830] = $chain();
    $receipt = $receiptFor($next830, 'next831-checkpoint-frame-mismatch');
    $receipt['checkpoint_frame']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next831AfterCurrentCheckpoint($next830, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next831', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next835 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next834] = $chain();
    $receipt = $receiptFor($next834, 'next835-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next835AfterCurrentCheckpoint($next834, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next835', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next835-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
