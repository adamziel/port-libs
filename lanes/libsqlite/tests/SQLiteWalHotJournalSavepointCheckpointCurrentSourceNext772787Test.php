<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base771 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next771',
    'database_path' => '/srv/www/wp-content/database/wp-next772.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next772.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next772.sqlite-wal',
    'source_token' => 'wp-next772-787-current-source',
    'database_digest' => $digest('next772-787 checkpoint database image'),
    'page_cache_digest' => $digest('next772-787 checkpoint page cache image'),
    'commit_generation' => 772,
    'schema_cookie' => 1772,
    'checkpoint_frame' => 572,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next756_763_next763',
        'seal_after_ready_checkpoint_current_source_next764_771_next771',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next771'],
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

$chain = static function () use ($base771, $receiptFor): array {
    $next772 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next772AfterCurrentCheckpoint($base771, [$receiptFor($base771, 'next772-restart-salt-database-digest')]);
    $next773 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next773AfterCurrentCheckpoint($next772, [$receiptFor($next772, 'next773-reader-release-checkpoint-frame')]);
    $next774 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next774AfterCurrentCheckpoint($next773, [$receiptFor($next773, 'next774-page-cache-source-token')]);
    $next775 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next775AfterCurrentCheckpoint($next774, [$receiptFor($next774, 'next775-schema-cookie-database-header')]);
    $next776 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next776AfterCurrentCheckpoint($next775, [$receiptFor($next775, 'next776-commit-generation-wal-index')]);
    $next777 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next777AfterCurrentCheckpoint($next776, [$receiptFor($next776, 'next777-hot-journal-reader-release')]);
    $next778 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next778AfterCurrentCheckpoint($next777, [$receiptFor($next777, 'next778-wal-index-page-cache')]);
    $next779 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next779AfterCurrentCheckpoint($next778, [$receiptFor($next778, 'next779-current-source-seal')]);
    $next780 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next780AfterCurrentCheckpoint($next779, [$receiptFor($next779, 'next780-restart-salt-database-header')]);
    $next781 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next781AfterCurrentCheckpoint($next780, [$receiptFor($next780, 'next781-reader-release-source-token')]);
    $next782 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next782AfterCurrentCheckpoint($next781, [$receiptFor($next781, 'next782-page-cache-database-digest')]);
    $next783 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next783AfterCurrentCheckpoint($next782, [$receiptFor($next782, 'next783-checkpoint-frame-schema-cookie')]);
    $next784 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next784AfterCurrentCheckpoint($next783, [$receiptFor($next783, 'next784-commit-generation-checkpoint-frame')]);
    $next785 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next785AfterCurrentCheckpoint($next784, [$receiptFor($next784, 'next785-hot-journal-page-cache')]);
    $next786 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next786AfterCurrentCheckpoint($next785, [$receiptFor($next785, 'next786-wal-index-reader-release')]);
    $next787 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next787AfterCurrentCheckpoint($next786, [$receiptFor($next786, 'next787-current-source-seal')]);

    return [$next772, $next773, $next774, $next775, $next776, $next777, $next778, $next779, $next780, $next781, $next782, $next783, $next784, $next785, $next786, $next787];
};

$tests['wal hot journal savepoint checkpoint current source next772-787 receives checkpoint handoff from next771'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        772 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        773 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        774 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        775 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        776 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        777 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        778 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        779 => 'seal_after_ready_checkpoint_current_source_next772_779_complete',
        780 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        781 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        782 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        783 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        784 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        785 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        786 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        787 => 'seal_after_ready_checkpoint_current_source_next780_787_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 772];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next772 = $chainRows[0];
    $next787 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next771', $next772['base_status']);
    $t->same(['next787-current-source-seal'], $next787['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next764_771_next771', implode(',', $next787['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next772_779_next779', implode(',', $next787['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next771', implode(',', $next787['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next787', implode(',', $next787['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next772 rejects missing next771 handoff'] = static function (TestRunner $t) use ($base771, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next772AfterCurrentCheckpoint(
        array_replace($base771, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next770']),
        [$receiptFor($base771, 'next772-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next774 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next773] = $chain();
    $receipt = $receiptFor($next773, 'next774-source-token-mismatch');
    $receipt['source_token'] = 'wp-next772-787-different-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next774AfterCurrentCheckpoint($next773, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next774', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next777 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next776] = $chain();
    $receipt = $receiptFor($next776, 'next777-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next777AfterCurrentCheckpoint($next776, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next777', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next779 rejects missing next778 base'] = static function (TestRunner $t) use ($base771, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next779AfterCurrentCheckpoint(
        array_replace($base771, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next777']),
        [$receiptFor($base771, 'next779-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next783 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next782] = $chain();
    $receipt = $receiptFor($next782, 'next783-checkpoint-frame-mismatch');
    $receipt['checkpoint_frame']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next783AfterCurrentCheckpoint($next782, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next783', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next787 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next786] = $chain();
    $receipt = $receiptFor($next786, 'next787-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next787AfterCurrentCheckpoint($next786, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next787', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next787-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
