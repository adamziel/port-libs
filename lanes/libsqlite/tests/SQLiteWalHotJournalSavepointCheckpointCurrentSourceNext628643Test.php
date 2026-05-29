<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base627 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next627',
    'database_path' => '/srv/www/wp-content/database/wp-next628.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next628.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next628.sqlite-wal',
    'source_token' => 'wp-next628-643-current-source',
    'database_digest' => $digest('next628-643 checkpoint database image'),
    'page_cache_digest' => $digest('next628-643 checkpoint page cache image'),
    'commit_generation' => 628,
    'schema_cookie' => 1628,
    'checkpoint_frame' => 428,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next612_619_next619',
        'seal_after_ready_checkpoint_current_source_next620_627_next627',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next627'],
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

$chain = static function () use ($base627, $receiptFor): array {
    $next628 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next628AfterCurrentCheckpoint($base627, [$receiptFor($base627, 'next628-restart-salt-database-header')]);
    $next629 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next629AfterCurrentCheckpoint($next628, [$receiptFor($next628, 'next629-reader-release-database-digest')]);
    $next630 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next630AfterCurrentCheckpoint($next629, [$receiptFor($next629, 'next630-page-cache-source-token')]);
    $next631 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next631AfterCurrentCheckpoint($next630, [$receiptFor($next630, 'next631-checkpoint-frame-commit-generation')]);
    $next632 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next632AfterCurrentCheckpoint($next631, [$receiptFor($next631, 'next632-schema-cookie-wal-index')]);
    $next633 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next633AfterCurrentCheckpoint($next632, [$receiptFor($next632, 'next633-hot-journal-reader-release')]);
    $next634 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next634AfterCurrentCheckpoint($next633, [$receiptFor($next633, 'next634-database-digest-page-cache')]);
    $next635 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next635AfterCurrentCheckpoint($next634, [$receiptFor($next634, 'next635-current-source-seal')]);
    $next636 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next636AfterCurrentCheckpoint($next635, [$receiptFor($next635, 'next636-restart-salt-source-token')]);
    $next637 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next637AfterCurrentCheckpoint($next636, [$receiptFor($next636, 'next637-reader-release-database-header')]);
    $next638 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next638AfterCurrentCheckpoint($next637, [$receiptFor($next637, 'next638-page-cache-schema-cookie')]);
    $next639 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next639AfterCurrentCheckpoint($next638, [$receiptFor($next638, 'next639-checkpoint-frame-database-digest')]);
    $next640 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next640AfterCurrentCheckpoint($next639, [$receiptFor($next639, 'next640-commit-generation-wal-index')]);
    $next641 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next641AfterCurrentCheckpoint($next640, [$receiptFor($next640, 'next641-hot-journal-page-cache')]);
    $next642 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next642AfterCurrentCheckpoint($next641, [$receiptFor($next641, 'next642-wal-index-reader-release')]);
    $next643 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next643AfterCurrentCheckpoint($next642, [$receiptFor($next642, 'next643-current-source-seal')]);

    return [$next628, $next629, $next630, $next631, $next632, $next633, $next634, $next635, $next636, $next637, $next638, $next639, $next640, $next641, $next642, $next643];
};

$tests['wal hot journal savepoint checkpoint current source next628-643 chains after merged next612-627'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        628 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        629 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        630 => 'verify_after_ready_checkpoint_page_cache_database_header_complete',
        631 => 'verify_after_ready_checkpoint_checkpoint_frame_wal_index_salt_complete',
        632 => 'verify_after_ready_checkpoint_commit_generation_schema_cookie_complete',
        633 => 'verify_after_ready_checkpoint_hot_journal_delete_reader_release_complete',
        634 => 'verify_after_ready_checkpoint_database_digest_page_cache_complete',
        635 => 'seal_after_ready_checkpoint_current_source_next628_635_complete',
        636 => 'verify_after_ready_checkpoint_restart_salt_source_token_complete',
        637 => 'verify_after_ready_checkpoint_reader_mark_release_database_header_complete',
        638 => 'verify_after_ready_checkpoint_page_cache_schema_cookie_complete',
        639 => 'verify_after_ready_checkpoint_checkpoint_frame_database_digest_complete',
        640 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        641 => 'verify_after_ready_checkpoint_hot_journal_absence_page_cache_complete',
        642 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        643 => 'seal_after_ready_checkpoint_current_source_next636_643_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 628];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next643 = $chainRows[15];
    $t->same(['next643-current-source-seal'], $next643['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next620_627_next627', implode(',', $next643['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next628_635_next635', implode(',', $next643['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next627', implode(',', $next643['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next643', implode(',', $next643['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next628 blocks database header not synced'] = static function (TestRunner $t) use ($base627, $receiptFor): void {
    $receipt = $receiptFor($base627, 'next628-unsynced-database-header');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next628AfterCurrentCheckpoint($base627, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next628', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next630 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next629] = $chain();
    $receipt = $receiptFor($next629, 'next630-stale-source-token');
    $receipt['source_token'] = 'stale-next630-current-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next630AfterCurrentCheckpoint($next629, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next630', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next635 rejects missing next634 base'] = static function (TestRunner $t) use ($base627, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next635AfterCurrentCheckpoint(
        array_replace($base627, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next633']),
        [$receiptFor($base627, 'next635-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next637 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , $next636] = $chain();
    $receipt = $receiptFor($next636, 'next637-stale-schema-cookie');
    $receipt['schema_cookie'] = 1611;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next637AfterCurrentCheckpoint($next636, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next637', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next641 blocks hot journal still visible'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , $next640] = $chain();
    $receipt = $receiptFor($next640, 'next641-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next641AfterCurrentCheckpoint($next640, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next641', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next643 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next642] = $chain();
    $receipt = $receiptFor($next642, 'next643-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next643AfterCurrentCheckpoint($next642, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next643', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next643-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
