<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next179.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next179 checkpointed schema after hot journal')
    . $page('next179 checkpointed options root after savepoint')
    . $page('next179 checkpointed active plugins after restart');
$walBytes = $page('next179 next wal frame header placeholder') . $page('next179 retry frame active plugins');
$receiptDigest = hash('sha256', 'next179-receipt');
$receipt = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next178',
    'reason' => 'post_apply_files_match_guarded_checkpoint_receipt',
    'database_path' => $databasePath,
    'journal_path' => $journalPath,
    'wal_path' => $walPath,
    'can_publish_receipt' => true,
    'blocked_reasons' => [],
    'database_sha256' => hash('sha256', $databaseBytes),
    'wal_sha256' => hash('sha256', $walBytes),
    'receipt_digest' => $receiptDigest,
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next178'],
];
$reads = [
    ['page' => 1, 'source' => 'database', 'receipt_digest' => $receiptDigest, 'expected_image' => $page('next179 checkpointed schema after hot journal'), 'actual_image' => $page('next179 checkpointed schema after hot journal')],
    ['page' => 2, 'source' => 'database', 'receipt_digest' => $receiptDigest, 'expected_image' => $page('next179 checkpointed options root after savepoint'), 'actual_image' => $page('next179 checkpointed options root after savepoint')],
    ['page' => 3, 'source' => 'wal', 'receipt_digest' => $receiptDigest, 'expected_image' => $page('next179 retry frame active plugins'), 'actual_image' => $page('next179 retry frame active plugins')],
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next179Plan($receipt, $reads, $databaseBytes, null, $walBytes);
$blockedReceipt = static function () use ($receipt, $reads, $databaseBytes, $walBytes): array {
    $bad = $receipt;
    $bad['can_publish_receipt'] = false;
    $bad['blocked_reasons'] = ['stale_wal_after_apply'];

    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next179Plan($bad, $reads, $databaseBytes, null, $walBytes);
};
$staleRead = static function () use ($receipt, $reads, $databaseBytes, $walBytes): array {
    $badReads = $reads;
    $badReads[1]['receipt_digest'] = hash('sha256', 'old-next179-receipt');
    $badReads[2]['actual_image'] = str_pad('next179 stale retry frame', 512, '.', STR_PAD_RIGHT);

    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next179Plan($receipt, $badReads, $databaseBytes, null, $walBytes);
};
$staleFiles = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next179Plan($receipt, $reads, 'stale-db', 'leftover-journal', 'stale-wal');

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next179'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'receipt_digest_pins_reopen_reads_after_checkpoint'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journalPath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $walPath],
    'receipt digest' => [static fn (): mixed => $plan()['receipt_digest'], $receiptDigest],
    'journal removed' => [static fn (): mixed => $plan()['journal_removed'], true],
    'receipt publishable' => [static fn (): mixed => $plan()['receipt_publishable'], true],
    'can reopen' => [static fn (): mixed => $plan()['can_reopen_after_checkpoint'], true],
    'read sources' => [static fn (): mixed => $plan()['read_sources'], ['database', 'wal']],
    'read row count' => [static fn (): mixed => count($plan()['read_rows']), 3],
    'first read source' => [static fn (): mixed => $plan()['read_rows'][0]['source'], 'database'],
    'third read source' => [static fn (): mixed => $plan()['read_rows'][2]['source'], 'wal'],
    'third read sequence' => [static fn (): mixed => $plan()['read_rows'][2]['sequence'], 3],
    'third read matches' => [static fn (): mixed => $plan()['read_rows'][2]['matches'], true],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'dependency next179' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next179', $plan()['dependencies'], true), true],
    'dependency next178' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next178', $plan()['dependencies'], true), true],
    'application dependency' => [static fn (): mixed => in_array('application-import-hot-journal-checkpoint-reopen-current-source', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'next178 post-apply receipt digests'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next176'), true],
    'blocked receipt status' => [static fn (): mixed => $blockedReceipt()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next179'],
    'blocked receipt reason' => [static fn (): mixed => $blockedReceipt()['blocked_reasons'], ['next178_receipt_not_publishable']],
    'stale read blocked' => [static fn (): mixed => $staleRead()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next179'],
    'stale read reasons' => [static fn (): mixed => $staleRead()['blocked_reasons'], ['reopen_read_2_receipt_digest_mismatch', 'reopen_read_3_image_mismatch']],
    'stale files reasons' => [static fn (): mixed => $staleFiles()['blocked_reasons'], ['reopen_database_digest_mismatch', 'reopen_hot_journal_still_present', 'reopen_wal_digest_mismatch']],
    'missing receipt rejected' => [static function () use ($receipt, $reads, $databaseBytes, $walBytes): string {
        $bad = $receipt;
        unset($bad['receipt_digest']);
        try {
            SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next179Plan($bad, $reads, $databaseBytes, null, $walBytes);
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return 'no-error';
    }, 'SQLite WAL hot-journal savepoint checkpoint current-source next179 missing receipt receipt_digest'],
    'empty reads rejected' => [static function () use ($receipt, $databaseBytes, $walBytes): string {
        try {
            SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next179Plan($receipt, [], $databaseBytes, null, $walBytes);
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return 'no-error';
    }, 'SQLite WAL hot-journal savepoint checkpoint current-source next179 requires reopen reads'],
    'bad source rejected' => [static function () use ($receipt, $reads, $databaseBytes, $walBytes): string {
        $bad = $reads;
        $bad[0]['source'] = 'journal';
        try {
            SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next179Plan($receipt, $bad, $databaseBytes, null, $walBytes);
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return 'no-error';
    }, 'SQLite WAL hot-journal savepoint checkpoint current-source next179 reopen read source must be database or wal'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next179 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
