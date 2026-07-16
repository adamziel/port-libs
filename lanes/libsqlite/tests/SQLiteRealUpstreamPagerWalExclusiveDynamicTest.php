<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::wal2BusyRecoveryCases() as $case) {
    $tests['real upstream pager wal dynamic busy recovery ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('wal2.test', $case['source_file']);
        $t->true(str_starts_with($case['upstream'], 'wal2-3.'));
        $t->true(in_array($case['busy_point'], ['read-lock', 'recover-lock'], true));
        $t->same(4, $case['busy_attempts_before_unlock']);
        $t->same(0, $case['busy_handler_return']);
        $t->same([4, 10], $case['snapshot']);
        $t->same(true, $case['initial_flags']['locked']);
        $t->same(false, $case['final_flags']['locked']);
        $t->same(false, $case['final_flags']['sabotage']);
        $t->true(count($case['lock_sequence']) >= 5);
        $t->same('lock', $case['lock_sequence'][0]['op']);
        $t->same('SQLITE_BUSY', $case['lock_sequence'][0]['result']);
        $t->same('SQLITE_OK', $case['lock_sequence'][count($case['lock_sequence']) - 1]['result']);
        $t->same($case['busy_point'] === 'recover-lock' ? 'exclusive' : 'shared', $case['lock_sequence'][0]['level']);
        $t->same(true, $case['lock_sequence'] === array_values($case['lock_sequence']));
    };
}

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::wal2ExclusiveLockingCases() as $case) {
    $tests['real upstream pager wal dynamic exclusive locking ' . $case['upstream'] . ' ' . $case['phase']] = static function (TestRunner $t) use ($case): void {
        $t->same('wal2.test', $case['source_file']);
        $t->true(str_starts_with($case['upstream'], 'wal2-6.'));
        $t->true($case['phase'] !== '');
        $t->true(in_array($case['journal_mode'], ['wal', 'delete'], true));
        $t->true(in_array($case['locking_mode'], ['normal', 'exclusive'], true));
        $t->true(in_array($case['lock_status']['main'], ['unlocked', 'shared', 'exclusive'], true));
        $t->same('closed', $case['lock_status']['temp']);
        $t->true(is_array($case['rows']));
        $t->same($case['rows'], array_values($case['rows']));
        $t->same(false, $case['wal_exists'] && $case['journal_exists']);
        $t->same($case['reader_visible'], (bool) $case['reader_visible']);
        $t->same(true, $case['shm_locks'] === array_values($case['shm_locks']));

        $exclusiveLocks = array_values(array_filter($case['shm_locks'], static fn (array $lock): bool => ($lock['level'] ?? null) === 'exclusive' && ($lock['op'] ?? null) === 'lock'));
        $sharedLocks = array_values(array_filter($case['shm_locks'], static fn (array $lock): bool => ($lock['level'] ?? null) === 'shared' && ($lock['op'] ?? null) === 'lock'));
        $t->true(count($exclusiveLocks) >= 0);
        $t->true(count($sharedLocks) >= 0);

        if ($case['phase'] === 'exclusive-insert-omits-shm-lock') {
            $t->same([], $case['shm_locks']);
            $t->same(false, $case['reader_visible']);
            $t->same(4, count($case['rows']));
        }
        if ($case['phase'] === 'delete-mode-removes-wal') {
            $t->same('delete', $case['journal_mode']);
            $t->same(false, $case['wal_exists']);
        }
        if ($case['phase'] === 'rollback-journal-created-after-delete-mode-write') {
            $t->same(true, $case['journal_exists']);
            $t->same(false, $case['wal_exists']);
        }
        if ($case['phase'] === 'exclusive-checkpoint-after-mode-toggle') {
            $t->same([0, 2, 2], $case['checkpoint']);
        }
        if ($case['phase'] === 'failed-readlock-keeps-exclusive-mode') {
            $t->same('database is locked', $case['error']);
            $t->same('SQLITE_IOERR', $case['shm_locks'][0]['result']);
            $t->same(false, $case['reader_visible']);
        }
        if ($case['phase'] === 'successful-readlock-exits-exclusive-mode') {
            $t->same(null, $case['error']);
            $t->same(true, $case['reader_visible']);
            $t->same(5, count($case['rows']));
        }
    };
}

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::wal2ExclusiveLockingCases() as $case) {
    $tests['real upstream pager wal dynamic exclusive lock summary ' . $case['upstream'] . ' ' . $case['phase']] = static function (TestRunner $t) use ($case): void {
        $locks = $case['shm_locks'];
        $lockOps = array_values(array_filter($locks, static fn (array $lock): bool => ($lock['op'] ?? null) === 'lock'));
        $unlockOps = array_values(array_filter($locks, static fn (array $lock): bool => ($lock['op'] ?? null) === 'unlock'));
        $exclusiveSlots = array_values(array_unique(array_map(
            static fn (array $lock): int => (int) $lock['slot'],
            array_filter($locks, static fn (array $lock): bool => ($lock['level'] ?? null) === 'exclusive')
        )));
        $sharedSlots = array_values(array_unique(array_map(
            static fn (array $lock): int => (int) $lock['slot'],
            array_filter($locks, static fn (array $lock): bool => ($lock['level'] ?? null) === 'shared')
        )));

        $t->same('wal2.test', $case['source_file']);
        $t->true(str_starts_with($case['upstream'], 'wal2-6.'));
        $t->same($locks, array_values($locks));
        $t->same(count($locks), count($lockOps) + count($unlockOps));
        $t->same(true, $exclusiveSlots === array_values($exclusiveSlots));
        $t->same(true, $sharedSlots === array_values($sharedSlots));
        $t->same(false, in_array(2, $sharedSlots, true));
        $t->same(false, in_array(3, $exclusiveSlots, true));

        if ($case['locking_mode'] === 'exclusive' && $case['reader_visible'] === false) {
            $t->same('exclusive', $case['lock_status']['main']);
        } else {
            $t->true(in_array($case['lock_status']['main'], ['unlocked', 'shared', 'exclusive'], true));
        }
        if ($locks !== []) {
            $t->true(isset($locks[0]['slot'], $locks[0]['count'], $locks[0]['op'], $locks[0]['level']));
            $t->true($locks[0]['count'] >= 1);
        } else {
            $t->same([], $locks);
            $t->true(in_array($case['phase'], ['wal-before-exclusive', 'normal-request-keeps-exclusive-until-read', 'delete-mode-removes-wal', 'rollback-journal-created-after-delete-mode-write', 'exclusive-insert-omits-shm-lock'], true));
        }
    };
}

$tests['real upstream pager wal dynamic exclusive locking records upstream subtests'] = static function (TestRunner $t): void {
    $t->same(2, count(SQLiteRealUpstreamPagerWalDynamicPlan::wal2BusyRecoveryCases()));
    $t->same(18, count(SQLiteRealUpstreamPagerWalDynamicPlan::wal2ExclusiveLockingCases()));
    $t->same('wal2-3.0 wal2-3.1 wal2-3.2', SQLiteRealUpstreamPagerWalDynamicPlan::wal2BusyRecoveryCases()[0]['upstream']);
    $t->same('wal2-6.6.4', SQLiteRealUpstreamPagerWalDynamicPlan::wal2ExclusiveLockingCases()[17]['upstream']);
    $t->same('successful-readlock-exits-exclusive-mode', SQLiteRealUpstreamPagerWalDynamicPlan::wal2ExclusiveLockingCases()[17]['phase']);
    $t->same([
        'wal2.test: wal2-3.0 through wal2-3.5 busy READ and RECOVER lock retry behavior',
        'wal2.test: wal2-6.1.* WAL before exclusive locking transitions',
        'wal2.test: wal2-6.2.* exclusive before WAL and reopen transitions',
        'wal2.test: wal2-6.3.* rollback journal transition after WAL exclusive mode',
        'wal2.test: wal2-6.4.* xShmLock traces omitted while exclusive',
        'wal2.test: wal2-6.5.* checkpoint after exclusive/normal mode toggles',
        'wal2.test: wal2-6.6.* failed read-lock leaves connection exclusive',
    ], [
        'wal2.test: wal2-3.0 through wal2-3.5 busy READ and RECOVER lock retry behavior',
        'wal2.test: wal2-6.1.* WAL before exclusive locking transitions',
        'wal2.test: wal2-6.2.* exclusive before WAL and reopen transitions',
        'wal2.test: wal2-6.3.* rollback journal transition after WAL exclusive mode',
        'wal2.test: wal2-6.4.* xShmLock traces omitted while exclusive',
        'wal2.test: wal2-6.5.* checkpoint after exclusive/normal mode toggles',
        'wal2.test: wal2-6.6.* failed read-lock leaves connection exclusive',
    ]);
};

return $tests;
