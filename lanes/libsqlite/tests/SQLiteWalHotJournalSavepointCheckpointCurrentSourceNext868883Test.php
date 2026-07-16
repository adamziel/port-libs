<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base867 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next867',
    'database_path' => '/srv/www/wp-content/database/wp-next868.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next868.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next868.sqlite-wal',
    'source_token' => 'wp-next868-883-current-source',
    'database_digest' => $digest('next868-883 checkpoint database image'),
    'page_cache_digest' => $digest('next868-883 checkpoint page cache image'),
    'commit_generation' => 868,
    'schema_cookie' => 1868,
    'checkpoint_frame' => 668,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next836_843_next843',
        'seal_after_ready_checkpoint_current_source_next844_851_next851',
        'seal_after_ready_checkpoint_current_source_next852_859_next859',
        'seal_after_ready_checkpoint_current_source_next860_867_next867',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next835',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next867',
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

$chain = static function () use ($base867, $receiptFor): array {
    $next868 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next868AfterCurrentCheckpoint($base867, [$receiptFor($base867, 'next868-restart-salt-database-digest')]);
    $next869 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next869AfterCurrentCheckpoint($next868, [$receiptFor($next868, 'next869-reader-release-checkpoint-frame')]);
    $next870 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next870AfterCurrentCheckpoint($next869, [$receiptFor($next869, 'next870-page-cache-source-token')]);
    $next871 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next871AfterCurrentCheckpoint($next870, [$receiptFor($next870, 'next871-schema-cookie-database-header')]);
    $next872 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next872AfterCurrentCheckpoint($next871, [$receiptFor($next871, 'next872-commit-generation-wal-index')]);
    $next873 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next873AfterCurrentCheckpoint($next872, [$receiptFor($next872, 'next873-hot-journal-reader-release')]);
    $next874 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next874AfterCurrentCheckpoint($next873, [$receiptFor($next873, 'next874-wal-index-page-cache')]);
    $next875 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next875AfterCurrentCheckpoint($next874, [$receiptFor($next874, 'next875-current-source-seal')]);
    $next876 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next876AfterCurrentCheckpoint($next875, [$receiptFor($next875, 'next876-restart-salt-database-header')]);
    $next877 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next877AfterCurrentCheckpoint($next876, [$receiptFor($next876, 'next877-reader-release-source-token')]);
    $next878 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next878AfterCurrentCheckpoint($next877, [$receiptFor($next877, 'next878-page-cache-database-digest')]);
    $next879 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next879AfterCurrentCheckpoint($next878, [$receiptFor($next878, 'next879-checkpoint-frame-schema-cookie')]);
    $next880 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next880AfterCurrentCheckpoint($next879, [$receiptFor($next879, 'next880-commit-generation-checkpoint-frame')]);
    $next881 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next881AfterCurrentCheckpoint($next880, [$receiptFor($next880, 'next881-hot-journal-page-cache')]);
    $next882 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next882AfterCurrentCheckpoint($next881, [$receiptFor($next881, 'next882-wal-index-reader-release')]);
    $next883 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next883AfterCurrentCheckpoint($next882, [$receiptFor($next882, 'next883-current-source-seal')]);

    return [$next868, $next869, $next870, $next871, $next872, $next873, $next874, $next875, $next876, $next877, $next878, $next879, $next880, $next881, $next882, $next883];
};

$tests['wal hot journal savepoint checkpoint current source next868-883 receives checkpoint handoff from next867'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        868 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        869 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        870 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        871 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        872 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        873 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        874 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        875 => 'seal_after_ready_checkpoint_current_source_next868_875_complete',
        876 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        877 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        878 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        879 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        880 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        881 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        882 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        883 => 'seal_after_ready_checkpoint_current_source_next876_883_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 868];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next868 = $chainRows[0];
    $next883 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next867', $next868['base_status']);
    $t->same(['next883-current-source-seal'], $next883['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next836_843_next843', implode(',', $next883['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next844_851_next851', implode(',', $next883['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next852_859_next859', implode(',', $next883['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next860_867_next867', implode(',', $next883['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next868_875_next875', implode(',', $next883['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next867', implode(',', $next883['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next883', implode(',', $next883['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next868 rejects missing next867 handoff'] = static function (TestRunner $t) use ($base867, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next868AfterCurrentCheckpoint(
        array_replace($base867, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next866']),
        [$receiptFor($base867, 'next868-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next870 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next869] = $chain();
    $receipt = $receiptFor($next869, 'next870-source-token-mismatch');
    $receipt['source_token'] = 'wp-next870-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next870AfterCurrentCheckpoint($next869, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next870', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next873 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next872] = $chain();
    $receipt = $receiptFor($next872, 'next873-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next873AfterCurrentCheckpoint($next872, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next873', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next875 rejects missing next874 base'] = static function (TestRunner $t) use ($base867, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next875AfterCurrentCheckpoint(
        array_replace($base867, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next873']),
        [$receiptFor($base867, 'next875-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next879 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next878] = $chain();
    $receipt = $receiptFor($next878, 'next879-checkpoint-frame-mismatch');
    $receipt['checkpoint_frame']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next879AfterCurrentCheckpoint($next878, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next879', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next883 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next882] = $chain();
    $receipt = $receiptFor($next882, 'next883-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next883AfterCurrentCheckpoint($next882, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next883', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next883-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
