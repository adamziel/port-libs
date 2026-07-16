<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base755 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next755',
    'database_path' => '/srv/www/wp-content/database/wp-next756.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next756.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next756.sqlite-wal',
    'source_token' => 'wp-next756-771-current-source',
    'database_digest' => $digest('next756-771 checkpoint database image'),
    'page_cache_digest' => $digest('next756-771 checkpoint page cache image'),
    'commit_generation' => 756,
    'schema_cookie' => 1756,
    'checkpoint_frame' => 556,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next740_747_next747',
        'seal_after_ready_checkpoint_current_source_next748_755_next755',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next755'],
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

$chain = static function () use ($base755, $receiptFor): array {
    $next756 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next756AfterCurrentCheckpoint($base755, [$receiptFor($base755, 'next756-restart-salt-database-digest')]);
    $next757 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next757AfterCurrentCheckpoint($next756, [$receiptFor($next756, 'next757-reader-release-checkpoint-frame')]);
    $next758 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next758AfterCurrentCheckpoint($next757, [$receiptFor($next757, 'next758-page-cache-source-token')]);
    $next759 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next759AfterCurrentCheckpoint($next758, [$receiptFor($next758, 'next759-schema-cookie-database-header')]);
    $next760 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next760AfterCurrentCheckpoint($next759, [$receiptFor($next759, 'next760-commit-generation-wal-index')]);
    $next761 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next761AfterCurrentCheckpoint($next760, [$receiptFor($next760, 'next761-hot-journal-reader-release')]);
    $next762 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next762AfterCurrentCheckpoint($next761, [$receiptFor($next761, 'next762-wal-index-page-cache')]);
    $next763 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next763AfterCurrentCheckpoint($next762, [$receiptFor($next762, 'next763-current-source-seal')]);
    $next764 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next764AfterCurrentCheckpoint($next763, [$receiptFor($next763, 'next764-restart-salt-database-header')]);
    $next765 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next765AfterCurrentCheckpoint($next764, [$receiptFor($next764, 'next765-reader-release-source-token')]);
    $next766 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next766AfterCurrentCheckpoint($next765, [$receiptFor($next765, 'next766-page-cache-database-digest')]);
    $next767 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next767AfterCurrentCheckpoint($next766, [$receiptFor($next766, 'next767-checkpoint-frame-schema-cookie')]);
    $next768 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next768AfterCurrentCheckpoint($next767, [$receiptFor($next767, 'next768-commit-generation-checkpoint-frame')]);
    $next769 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next769AfterCurrentCheckpoint($next768, [$receiptFor($next768, 'next769-hot-journal-page-cache')]);
    $next770 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next770AfterCurrentCheckpoint($next769, [$receiptFor($next769, 'next770-wal-index-reader-release')]);
    $next771 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next771AfterCurrentCheckpoint($next770, [$receiptFor($next770, 'next771-current-source-seal')]);

    return [$next756, $next757, $next758, $next759, $next760, $next761, $next762, $next763, $next764, $next765, $next766, $next767, $next768, $next769, $next770, $next771];
};

$tests['wal hot journal savepoint checkpoint current source next756-771 receives checkpoint handoff from next755'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        756 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        757 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        758 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        759 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        760 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        761 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        762 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        763 => 'seal_after_ready_checkpoint_current_source_next756_763_complete',
        764 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        765 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        766 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        767 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        768 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        769 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        770 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        771 => 'seal_after_ready_checkpoint_current_source_next764_771_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 756];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next756 = $chainRows[0];
    $next771 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next755', $next756['base_status']);
    $t->same(['next771-current-source-seal'], $next771['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next748_755_next755', implode(',', $next771['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next756_763_next763', implode(',', $next771['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next755', implode(',', $next771['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next771', implode(',', $next771['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next756 rejects missing next755 handoff'] = static function (TestRunner $t) use ($base755, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next756AfterCurrentCheckpoint(
        array_replace($base755, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next754']),
        [$receiptFor($base755, 'next756-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next758 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next757] = $chain();
    $receipt = $receiptFor($next757, 'next758-source-token-mismatch');
    $receipt['source_token'] = 'wp-next756-771-different-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next758AfterCurrentCheckpoint($next757, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next758', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next761 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next760] = $chain();
    $receipt = $receiptFor($next760, 'next761-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next761AfterCurrentCheckpoint($next760, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next761', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next763 rejects missing next762 base'] = static function (TestRunner $t) use ($base755, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next763AfterCurrentCheckpoint(
        array_replace($base755, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next761']),
        [$receiptFor($base755, 'next763-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next767 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next766] = $chain();
    $receipt = $receiptFor($next766, 'next767-checkpoint-frame-mismatch');
    $receipt['checkpoint_frame']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next767AfterCurrentCheckpoint($next766, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next767', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next771 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next770] = $chain();
    $receipt = $receiptFor($next770, 'next771-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next771AfterCurrentCheckpoint($next770, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next771', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next771-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
