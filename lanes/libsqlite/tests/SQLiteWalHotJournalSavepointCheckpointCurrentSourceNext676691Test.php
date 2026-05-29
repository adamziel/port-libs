<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base675 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next675',
    'database_path' => '/srv/www/wp-content/database/wp-next676.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next676.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next676.sqlite-wal',
    'source_token' => 'wp-next676-691-current-source',
    'database_digest' => $digest('next676-691 checkpoint database image'),
    'page_cache_digest' => $digest('next676-691 checkpoint page cache image'),
    'commit_generation' => 676,
    'schema_cookie' => 1676,
    'checkpoint_frame' => 476,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next660_667_next667',
        'seal_after_ready_checkpoint_current_source_next668_675_next675',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next675'],
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

$chain = static function () use ($base675, $receiptFor): array {
    $next676 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next676AfterCurrentCheckpoint($base675, [$receiptFor($base675, 'next676-restart-salt-database-digest')]);
    $next677 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next677AfterCurrentCheckpoint($next676, [$receiptFor($next676, 'next677-reader-release-source-token')]);
    $next678 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next678AfterCurrentCheckpoint($next677, [$receiptFor($next677, 'next678-page-cache-schema-cookie')]);
    $next679 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next679AfterCurrentCheckpoint($next678, [$receiptFor($next678, 'next679-checkpoint-frame-wal-index')]);
    $next680 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next680AfterCurrentCheckpoint($next679, [$receiptFor($next679, 'next680-commit-generation-database-header')]);
    $next681 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next681AfterCurrentCheckpoint($next680, [$receiptFor($next680, 'next681-hot-journal-reader-release')]);
    $next682 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next682AfterCurrentCheckpoint($next681, [$receiptFor($next681, 'next682-wal-index-page-cache')]);
    $next683 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next683AfterCurrentCheckpoint($next682, [$receiptFor($next682, 'next683-current-source-seal')]);
    $next684 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next684AfterCurrentCheckpoint($next683, [$receiptFor($next683, 'next684-restart-salt-checkpoint-frame')]);
    $next685 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next685AfterCurrentCheckpoint($next684, [$receiptFor($next684, 'next685-reader-release-database-header')]);
    $next686 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next686AfterCurrentCheckpoint($next685, [$receiptFor($next685, 'next686-page-cache-source-token')]);
    $next687 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next687AfterCurrentCheckpoint($next686, [$receiptFor($next686, 'next687-schema-cookie-database-digest')]);
    $next688 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next688AfterCurrentCheckpoint($next687, [$receiptFor($next687, 'next688-commit-generation-wal-index')]);
    $next689 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next689AfterCurrentCheckpoint($next688, [$receiptFor($next688, 'next689-hot-journal-page-cache')]);
    $next690 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next690AfterCurrentCheckpoint($next689, [$receiptFor($next689, 'next690-wal-index-reader-release')]);
    $next691 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next691AfterCurrentCheckpoint($next690, [$receiptFor($next690, 'next691-current-source-seal')]);

    return [$next676, $next677, $next678, $next679, $next680, $next681, $next682, $next683, $next684, $next685, $next686, $next687, $next688, $next689, $next690, $next691];
};

$tests['wal hot journal savepoint checkpoint current source next676-691 chains directly after next675'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        676 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        677 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        678 => 'verify_after_ready_checkpoint_page_cache_schema_cookie_complete',
        679 => 'verify_after_ready_checkpoint_checkpoint_frame_wal_index_salt_complete',
        680 => 'verify_after_ready_checkpoint_commit_generation_database_header_complete',
        681 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        682 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        683 => 'seal_after_ready_checkpoint_current_source_next676_683_complete',
        684 => 'verify_after_ready_checkpoint_restart_salt_checkpoint_frame_complete',
        685 => 'verify_after_ready_checkpoint_reader_mark_release_database_header_complete',
        686 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        687 => 'verify_after_ready_checkpoint_schema_cookie_database_digest_complete',
        688 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        689 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        690 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        691 => 'seal_after_ready_checkpoint_current_source_next684_691_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 676];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next691 = $chainRows[15];
    $t->same(['next691-current-source-seal'], $next691['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next668_675_next675', implode(',', $next691['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next676_683_next683', implode(',', $next691['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next675', implode(',', $next691['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next691', implode(',', $next691['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next676 rejects missing next675 handoff'] = static function (TestRunner $t) use ($base675, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next676AfterCurrentCheckpoint(
        array_replace($base675, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next674']),
        [$receiptFor($base675, 'next676-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next678 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next677] = $chain();
    $receipt = $receiptFor($next677, 'next678-stale-source-token');
    $receipt['source_token'] = 'wp-next678-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next678AfterCurrentCheckpoint($next677, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next678', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next681 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next680] = $chain();
    $receipt = $receiptFor($next680, 'next681-reader-marks-still-pinned');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next681AfterCurrentCheckpoint($next680, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next681', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next683 rejects missing next682 base'] = static function (TestRunner $t) use ($base675, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next683AfterCurrentCheckpoint(
        array_replace($base675, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next681']),
        [$receiptFor($base675, 'next683-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next687 blocks database digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next686] = $chain();
    $receipt = $receiptFor($next686, 'next687-stale-database-digest');
    $receipt['database_digest'] = hash('sha256', 'stale next687 database digest');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next687AfterCurrentCheckpoint($next686, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next687', $record['status']);
    $t->same(['checkpoint_database_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next691 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next690] = $chain();
    $receipt = $receiptFor($next690, 'next691-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next691AfterCurrentCheckpoint($next690, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next691', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next691-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
