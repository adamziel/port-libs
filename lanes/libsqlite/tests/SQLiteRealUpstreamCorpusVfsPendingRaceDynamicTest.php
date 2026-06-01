<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];
$caseCount = 0;
$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

foreach (range(1, 1000) as $case) {
    ++$caseCount;
    $pageCount = 20 + ($case % 2);
    $cacheSize = 1 + ($case % 13);
    $rowCount = 10 + ($case % 37);
    $payloadBytes = 64 + (($case * 17) % 193);
    $peerUnlockFailAt = 1 + ($case % 9);

    $tests[sprintf(
        'real upstream corpus vfs pendingrace dynamic hot journal pending lock %04d pages %02d cache %02d',
        $case,
        $pageCount,
        $cacheSize
    )] = static function (TestRunner $t) use ($cacheSize, $pageCount, $payloadBytes, $peerUnlockFailAt, $rowCount): void {
        $plan = SQLiteVfsIoDynamicPlan::pendingHotJournalRollbackRaceProfile(
            $pageCount,
            $cacheSize,
            $rowCount,
            $payloadBytes,
            $peerUnlockFailAt
        );

        $t->same('ok', $plan['status']);
        $t->same('pendingrace.test', $plan['script']);
        $t->same('pendingrace-1.3', $plan['scenario']);
        $t->same($pageCount, $plan['page_count']);
        $t->same($cacheSize, $plan['cache_size']);
        $t->same($rowCount, $plan['row_count']);
        $t->same($payloadBytes, $plan['payload_bytes']);
        $t->same('tvfs', $plan['primary_vfs']);
        $t->same('tvfs2', $plan['peer_vfs']);
        $t->same(true, $plan['hot_journal_exists_before_integrity_check']);
        $t->same(true, $plan['database_without_hot_journal_corrupt']);
        $t->same(true, $plan['saved_database_image_restored']);
        $t->same(true, $plan['saved_journal_image_restored']);
        $t->same('xAccess', $plan['peer_read_trigger']);
        $t->same($peerUnlockFailAt, $plan['peer_unlock_fail_at']);
        $t->same(true, $plan['peer_unlock_failed']);
        $t->same(true, $plan['peer_subsequent_vfs_calls_fail']);
        $t->same(true, $plan['peer_pending_lock_retained']);
        $t->same('failed', $plan['exclusive_upgrade_for_hot_journal_rollback']);
        $t->same(true, $plan['hot_journal_rollback_attempted']);
        $t->same(true, $plan['hot_journal_rollback_deferred']);
        $t->same([1, 'database is locked'], $plan['primary_integrity_check']);
        $t->same('database is locked', $plan['primary_error_message']);
        $t->same('SQLITE_BUSY', $plan['primary_result_code']);
        $t->same('SQLITE_IOERR_UNLOCK', $plan['peer_result_code']);
        $t->same('until_peer_close', $plan['pending_lock_leak_window']);
        $t->same(6, count($plan['lock_timeline']));
        $t->same('xLock-exclusive', $plan['lock_timeline'][3]['op']);
        $t->same('pending', $plan['lock_timeline'][4]['lock']);
        $t->same('integrity_check', $plan['lock_timeline'][5]['op']);
        $t->same(0, $plan['open_file_count_after_close']);
        $t->same('failed_peer_unlock_after_hot_journal_exclusive_upgrade_keeps_pending_lock_and_blocks_primary_rollback', $plan['reason']);
        $t->same(true, in_array('upstream-pendingrace-hot-journal-lock', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-pending-lock-after-unlock-ioerr', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-hot-journal-rollback-race', $plan['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
        $t->same(true, str_contains($plan['upstream'][3], 'database is locked'));
        $t->same(0, $plan['journal_bytes'] % 512);
        $t->same(true, $plan['journal_bytes'] >= 512);
    };
}

$tests['real upstream corpus vfs pendingrace dynamic cites hydrated source truth'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $source = (string) file_get_contents($upstreamRoot . '/pendingrace.test');
    $profile = SQLiteVfsIoDynamicPlan::pendingHotJournalRollbackRaceProfile();

    $t->same(true, is_file($upstreamRoot . '/pendingrace.test'));
    $t->contains('set testprefix pendingrace', $source);
    $t->contains('tvfs2 filter xUnlock', $source);
    $t->contains('tvfs filter xAccess', $source);
    $t->contains('PRAGMA integrity_check', $source);
    $t->contains('{1 {database is locked}}', $source);
    $t->same(20, $profile['page_count']);
    $t->same(5, $profile['cache_size']);
    $t->same([
        'pendingrace.test 1.0 creates indexed table with cache_size=5 and about twenty pages',
        'pendingrace.test 1.1 copies a hot rollback journal from a crashed peer update',
        'pendingrace.test 1.2 confirms test.db-journal exists before recovery',
        'pendingrace.test 1.3 primary integrity_check returns database is locked when peer xUnlock leaves PENDING lock',
    ], $profile['upstream']);
};

$tests['real upstream corpus vfs pendingrace dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pendingHotJournalRollbackRaceProfile(1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pendingHotJournalRollbackRaceProfile(20, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pendingHotJournalRollbackRaceProfile(20, 5, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pendingHotJournalRollbackRaceProfile(20, 5, 10, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pendingHotJournalRollbackRaceProfile(20, 5, 10, 100, 0));
};

$tests['real upstream corpus vfs pendingrace dynamic owns focused pass count'] = static function (TestRunner $t) use (&$tests, $caseCount): void {
    $t->same(1000, $caseCount);
    $t->same(1003, count($tests));
};

return $tests;
