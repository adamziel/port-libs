<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base347 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next347',
    'database_path' => '/srv/www/wp-content/database/wp-next348.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next348.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next348.sqlite-wal',
    'source_token' => 'wp-next348-355-current-source',
    'database_digest' => $digest('next348-355 checkpoint database image'),
    'page_cache_digest' => $digest('next348-355 checkpoint page cache image'),
    'commit_generation' => 355,
    'schema_cookie' => 1355,
    'checkpoint_frame' => 155,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next340_347_next347'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next347'],
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

$chain = static function () use ($base347, $receiptFor): array {
    $next348 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next348AfterCurrentCheckpoint(
        $base347,
        [$receiptFor($base347, 'next348-frame-boundary-receipt')]
    );
    $next349 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next349AfterCurrentCheckpoint(
        $next348,
        [$receiptFor($next348, 'next349-reader-mark-release-receipt')]
    );
    $next350 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next350AfterCurrentCheckpoint(
        $next349,
        [$receiptFor($next349, 'next350-page-cache-digest-receipt')]
    );
    $next351 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next351AfterCurrentCheckpoint(
        $next350,
        [$receiptFor($next350, 'next351-schema-cookie-receipt')]
    );
    $next352 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next352AfterCurrentCheckpoint(
        $next351,
        [$receiptFor($next351, 'next352-commit-generation-receipt')]
    );
    $next353 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next353AfterCurrentCheckpoint(
        $next352,
        [$receiptFor($next352, 'next353-hot-journal-absence-receipt')]
    );
    $next354 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next354AfterCurrentCheckpoint(
        $next353,
        [$receiptFor($next353, 'next354-source-token-receipt')]
    );
    $next355 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next355AfterCurrentCheckpoint(
        $next354,
        [$receiptFor($next354, 'next355-current-source-seal')]
    );

    return [$next348, $next349, $next350, $next351, $next352, $next353, $next354, $next355];
};

$tests['wal hot journal savepoint checkpoint current source next348-355 chains after merged next340-347'] = static function (TestRunner $t) use ($chain): void {
    [$next348, $next349, $next350, $next351, $next352, $next353, $next354, $next355] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next348', $next348['status']);
    $t->same('verify_after_ready_checkpoint_frame_boundary_receipt_complete', $next348['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next349', $next349['status']);
    $t->same('verify_after_ready_checkpoint_reader_mark_release_receipt_complete', $next349['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next350', $next350['status']);
    $t->same('verify_after_ready_checkpoint_page_cache_digest_receipt_complete', $next350['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next351', $next351['status']);
    $t->same('verify_after_ready_checkpoint_schema_cookie_receipt_complete', $next351['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next352', $next352['status']);
    $t->same('verify_after_ready_checkpoint_commit_generation_receipt_complete', $next352['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next353', $next353['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_absence_receipt_complete', $next353['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next354', $next354['status']);
    $t->same('verify_after_ready_checkpoint_source_token_receipt_complete', $next354['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next355', $next355['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next348_355_complete', $next355['reason']);
    $t->same(['next355-current-source-seal'], $next355['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_frame_boundary_receipt_next348', implode(',', $next355['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next347', implode(',', $next355['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next355', implode(',', $next355['dependencies']));
    $t->same(true, $next355['after_current_checkpoint_admitted']);
};

$tests['wal hot journal savepoint checkpoint current source next348 blocks stale checkpoint frame'] = static function (TestRunner $t) use ($base347, $receiptFor): void {
    $receipt = $receiptFor($base347, 'next348-stale-frame');
    $receipt['checkpoint_frame'] = 154;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next348AfterCurrentCheckpoint($base347, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next348', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next350 blocks stale page cache digest'] = static function (TestRunner $t) use ($chain, $receiptFor, $digest): void {
    [, $next349] = $chain();
    $receipt = $receiptFor($next349, 'next350-stale-page-cache');
    $receipt['page_cache_digest'] = $digest('stale next350 page cache image');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next350AfterCurrentCheckpoint($next349, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next350', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next353 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next352] = $chain();
    $receipt = $receiptFor($next352, 'next353-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next353AfterCurrentCheckpoint($next352, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next353', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next354 blocks duplicate receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , $next353] = $chain();
    $receipt = $receiptFor($next353, 'next354-duplicate-source-token');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next354AfterCurrentCheckpoint($next353, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next354', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next354-duplicate-source-token'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next355 rejects missing next354 base'] = static function (TestRunner $t) use ($base347, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next355AfterCurrentCheckpoint(
        array_replace($base347, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next353']),
        [$receiptFor($base347, 'next355-current-source-seal')]
    ));
};

return $tests;
