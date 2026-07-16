<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/subjournal.test';

$tests['real upstream corpus vfs subjournal dynamic cites hydrated upstream source'] = static function (TestRunner $t) use ($source): void {
    $t->same(true, is_file($source));
    $body = file_get_contents($source);
    $t->contains('PRAGMA temp_store = memory', $body);
    $t->contains('ROLLBACK TO two', $body);
    $t->contains('sqlite3_backup B db2 main db main', $body);
    $t->same([
        'subjournal.test 1.0 temp_store memory setup',
        'subjournal.test 1.1 rollback to savepoint preserves outer transaction rows',
        'subjournal.test 1.2 commit after rollback-to-savepoint',
        'subjournal.test 2.0 cache pressure indexed blob setup',
        'subjournal.test 2.1 online backup partial step',
        'subjournal.test 2.2 subjournal rollback while backup is active',
        'subjournal.test 2.3 backup reaches SQLITE_DONE',
        'subjournal.test 2.4 backed-up database integrity check',
    ], SQLiteVfsIoDynamicPlan::subjournalMemoryBackupProfile(100, 5, 499, 498, 75, 65)['upstream']);
};

foreach (range(1, 1000) as $case) {
    $tableRows = 40 + ($case % 211);
    $cachePages = 3 + ($case % 17);
    $outerPayloadBytes = 256 + (($case % 29) * 13);
    $innerPayloadBytes = max(1, $outerPayloadBytes - (1 + ($case % 7)));
    $backupPages = 25 + ($case % 97);
    $backupStepPages = max(1, $backupPages - (1 + ($case % 19)));

    $tests[sprintf('real upstream corpus vfs subjournal dynamic memory backup %04d', $case)] = static function (TestRunner $t) use ($tableRows, $cachePages, $outerPayloadBytes, $innerPayloadBytes, $backupPages, $backupStepPages): void {
        $profile = SQLiteVfsIoDynamicPlan::subjournalMemoryBackupProfile(
            $tableRows,
            $cachePages,
            $outerPayloadBytes,
            $innerPayloadBytes,
            $backupPages,
            $backupStepPages
        );

        $outerBytes = $tableRows * ($outerPayloadBytes + 24);
        $innerBytes = $tableRows * ($innerPayloadBytes + 24);

        $t->same('ok', $profile['status']);
        $t->same('subjournal.test', $profile['script']);
        $t->same('memory', $profile['temp_store']);
        $t->same($tableRows, $profile['table_rows']);
        $t->same($cachePages, $profile['cache_pages']);
        $t->same($outerPayloadBytes, $profile['outer_payload_bytes']);
        $t->same($innerPayloadBytes, $profile['inner_payload_bytes']);
        $t->same($tableRows, $profile['outer_before_images']);
        $t->same($tableRows, $profile['inner_before_images']);
        $t->same($outerBytes, $profile['outer_subjournal_bytes']);
        $t->same($innerBytes, $profile['inner_subjournal_bytes']);
        $t->same($tableRows > $cachePages, $profile['spill_required']);
        $t->same(false, $profile['disk_statement_journal_created']);
        $t->same(true, $profile['rollback_to_inner_restores_outer_update']);
        $t->same(true, $profile['outer_transaction_rows_visible']);
        $t->same('ok', $profile['commit_result']);
        $t->same($backupPages, $profile['backup_total_pages']);
        $t->same($backupStepPages, $profile['backup_first_step_pages']);
        $t->same('SQLITE_OK', $profile['backup_first_step_result']);
        $t->same($backupPages - $backupStepPages, $profile['backup_remaining_pages']);
        $t->same('SQLITE_DONE', $profile['backup_final_step_result']);
        $t->same('ok', $profile['source_integrity_check']);
        $t->same('ok', $profile['backup_integrity_check']);
        $t->same(true, in_array('upstream-subjournal-memory-backup', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-statement-subjournal', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs subjournal dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::subjournalMemoryBackupProfile(0, 5, 499, 498, 75, 65));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::subjournalMemoryBackupProfile(100, 0, 499, 498, 75, 65));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::subjournalMemoryBackupProfile(100, 5, 0, 498, 75, 65));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::subjournalMemoryBackupProfile(100, 5, 499, 0, 75, 65));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::subjournalMemoryBackupProfile(100, 5, 499, 498, 0, 65));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::subjournalMemoryBackupProfile(100, 5, 499, 498, 75, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::subjournalMemoryBackupProfile(100, 5, 499, 498, 75, 75));
};

return $tests;
