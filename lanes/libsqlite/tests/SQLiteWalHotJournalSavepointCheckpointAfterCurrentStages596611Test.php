<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base595 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next595',
    'database_path' => '/srv/www/wp-content/database/wp-next596.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next596.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next596.sqlite-wal',
    'source_token' => 'wp-next596-611-current-source',
    'database_digest' => $digest('next596-611 checkpoint database image'),
    'page_cache_digest' => $digest('next596-611 checkpoint page cache image'),
    'commit_generation' => 595,
    'schema_cookie' => 1595,
    'checkpoint_frame' => 395,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next580_587_next587',
        'seal_after_ready_checkpoint_current_source_next588_595_next595',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next595'],
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

$chain = static function () use ($base595, $receiptFor): array {
    $next596 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base595, [$receiptFor($base595, 'next596-restart-salt-database-digest')], 596);
    $next597 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next596, [$receiptFor($next596, 'next597-reader-release-source-token')], 597);
    $next598 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next597, [$receiptFor($next597, 'next598-page-cache-commit-generation')], 598);
    $next599 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next598, [$receiptFor($next598, 'next599-checkpoint-frame-schema-cookie')], 599);
    $next600 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next599, [$receiptFor($next599, 'next600-commit-generation-wal-index')], 600);
    $next601 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next600, [$receiptFor($next600, 'next601-hot-journal-database-header')], 601);
    $next602 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next601, [$receiptFor($next601, 'next602-wal-index-source-token')], 602);
    $next603 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next602, [$receiptFor($next602, 'next603-current-source-seal')], 603);
    $next604 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next603, [$receiptFor($next603, 'next604-restart-salt-schema-cookie')], 604);
    $next605 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next604, [$receiptFor($next604, 'next605-reader-release-page-cache')], 605);
    $next606 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next605, [$receiptFor($next605, 'next606-database-digest-commit-generation')], 606);
    $next607 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next606, [$receiptFor($next606, 'next607-checkpoint-frame-reader-release')], 607);
    $next608 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next607, [$receiptFor($next607, 'next608-commit-generation-source-token')], 608);
    $next609 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next608, [$receiptFor($next608, 'next609-hot-journal-page-cache')], 609);
    $next610 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next609, [$receiptFor($next609, 'next610-wal-index-database-digest')], 610);
    $next611 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next610, [$receiptFor($next610, 'next611-current-source-seal')], 611);

    return [$next596, $next597, $next598, $next599, $next600, $next601, $next602, $next603, $next604, $next605, $next606, $next607, $next608, $next609, $next610, $next611];
};

$tests['wal hot journal savepoint checkpoint current source next596-611 chains after merged next580-595'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        596 => 'verify_after_ready_checkpoint_restart_salt_receipt_database_digest_complete',
        597 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        598 => 'verify_after_ready_checkpoint_page_cache_digest_commit_generation_complete',
        599 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        600 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        601 => 'verify_after_ready_checkpoint_hot_journal_absence_database_header_complete',
        602 => 'verify_after_ready_checkpoint_wal_index_salt_source_token_complete',
        603 => 'seal_after_ready_checkpoint_current_source_next596_603_complete',
        604 => 'verify_after_ready_checkpoint_restart_salt_receipt_schema_cookie_complete',
        605 => 'verify_after_ready_checkpoint_reader_mark_release_page_cache_complete',
        606 => 'verify_after_ready_checkpoint_database_digest_commit_generation_complete',
        607 => 'verify_after_ready_checkpoint_checkpoint_frame_reader_release_complete',
        608 => 'verify_after_ready_checkpoint_commit_generation_source_token_complete',
        609 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        610 => 'verify_after_ready_checkpoint_wal_index_salt_database_digest_complete',
        611 => 'seal_after_ready_checkpoint_current_source_next604_611_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 596];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next611 = $chainRows[15];
    $t->same(['next611-current-source-seal'], $next611['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next588_595_next595', implode(',', $next611['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next596_603_next603', implode(',', $next611['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next595', implode(',', $next611['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next611', implode(',', $next611['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next596 blocks database digest mismatch'] = static function (TestRunner $t) use ($base595, $receiptFor): void {
    $receipt = $receiptFor($base595, 'next596-stale-database-digest');
    $receipt['database_digest'] = hash('sha256', 'stale next596 database image');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base595, [$receipt], 596);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next596', $record['status']);
    $t->same(['checkpoint_database_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next598 blocks generation mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next597] = $chain();
    $receipt = $receiptFor($next597, 'next598-stale-generation');
    $receipt['commit_generation'] = 594;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next597, [$receipt], 598);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next598', $record['status']);
    $t->same(['checkpoint_generation_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next603 rejects missing next602 base'] = static function (TestRunner $t) use ($base595, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        array_replace($base595, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next601'], 603),
        [$receiptFor($base595, 'next603-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next604 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , $next603] = $chain();
    $receipt = $receiptFor($next603, 'next604-stale-schema-cookie');
    $receipt['schema_cookie'] = 1594;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next603, [$receipt], 604);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next604', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next607 blocks reader marks not released'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next606] = $chain();
    $receipt = $receiptFor($next606, 'next607-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next606, [$receipt], 607);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next607', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next611 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next610] = $chain();
    $receipt = $receiptFor($next610, 'next611-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next610, [$receipt, $receipt], 611);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next611', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next611-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
