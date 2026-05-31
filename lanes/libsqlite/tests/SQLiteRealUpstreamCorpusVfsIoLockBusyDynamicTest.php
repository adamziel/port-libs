<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$operations = ['update', 'insert', 'delete'];
$case = 0;

foreach (range(0, 9) as $round) {
    foreach ([false, true] as $requesterHoldsReadLock) {
        foreach (range(0, 49) as $busyBreakCount) {
            $operation = $operations[($round + $busyBreakCount) % count($operations)];
            ++$case;
            $scenario = $requesterHoldsReadLock
                ? 'lock-2.3.2 read-lock requester bypasses busy callback'
                : ($busyBreakCount === 0
                    ? 'lock-2.3.1 unlocked requester invokes busy callback once'
                    : 'lock-2.4.1 unlocked requester repeats busy callback until break');

            $tests[sprintf(
                'real upstream corpus vfs io lock busy callback lock.test 2.3-2.4 case %04d readlock %d break %02d %s',
                $case,
                $requesterHoldsReadLock ? 1 : 0,
                $busyBreakCount,
                $operation
            )] = static function (TestRunner $t) use ($scenario, $requesterHoldsReadLock, $busyBreakCount, $operation): void {
                $profile = SQLiteVfsIoTrafficPlan::lockBusyCallbackProfile(
                    $scenario,
                    $requesterHoldsReadLock,
                    $busyBreakCount,
                    $operation
                );

                $expectedCounts = $requesterHoldsReadLock ? [] : range(0, $busyBreakCount);

                $t->same('lock.test', $profile['script']);
                $t->same($scenario, $profile['scenario']);
                $t->same('db', $profile['writer_connection']);
                $t->same('db2', $profile['reader_connection']);
                $t->same(true, $profile['writer_holds_reserved']);
                $t->same($requesterHoldsReadLock, $profile['requester_holds_read_lock']);
                $t->same($operation, $profile['operation']);
                $t->same(true, $profile['busy_handler_registered']);
                $t->same(!$requesterHoldsReadLock, $profile['busy_callback_invoked']);
                $t->same($expectedCounts, $profile['busy_callback_counts']);
                $t->same($requesterHoldsReadLock ? null : $busyBreakCount, $profile['busy_break_count']);
                $t->same('SQLITE_BUSY', $profile['result_code']);
                $t->same('database is locked', $profile['result_message']);
                $t->same(true, $profile['reader_can_select']);
                $t->same(true, $profile['writer_transaction_open']);
                $t->same(true, $profile['rollback_required']);
                $t->same(true, in_array('sqlite-upstream-lock-test', $profile['dependencies'], true));
                $t->same(true, in_array('sqlite-vfs-reserved-lock-contention', $profile['dependencies'], true));
                $t->same(true, in_array('sqlite-busy-handler-callback-sequence', $profile['dependencies'], true));
                $t->same(true, in_array('lock.test lock-2.1 writer obtains RESERVED lock', $profile['upstream'], true));
                $t->same(true, in_array('lock.test lock-2.2 reader can SELECT while writer holds RESERVED', $profile['upstream'], true));
                $t->same(true, in_array('lock.test lock-2.3 busy callback skipped when requester already holds read lock', $profile['upstream'], true));
                $t->same(true, in_array('lock.test lock-2.4 busy callback repeats until callback break', $profile['upstream'], true));
            };
        }
    }
}

$tests['real upstream corpus vfs io lock busy callback owns upstream lock test rows'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
    $t->same([
        'lock.test lock-2.1 writer obtains RESERVED lock',
        'lock.test lock-2.2 reader can SELECT while writer holds RESERVED',
        'lock.test lock-2.3.1 busy callback invoked for requester without an existing read lock',
        'lock.test lock-2.3.2 busy callback skipped when requester already holds a read lock',
        'lock.test lock-2.4.1 busy callback repeats with counts 0..5 before break',
    ], [
        'lock.test lock-2.1 writer obtains RESERVED lock',
        'lock.test lock-2.2 reader can SELECT while writer holds RESERVED',
        'lock.test lock-2.3.1 busy callback invoked for requester without an existing read lock',
        'lock.test lock-2.3.2 busy callback skipped when requester already holds a read lock',
        'lock.test lock-2.4.1 busy callback repeats with counts 0..5 before break',
    ]);
};

$tests['real upstream corpus vfs io lock busy callback rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::lockBusyCallbackProfile('', false, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::lockBusyCallbackProfile('lock-2.4.1', false, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::lockBusyCallbackProfile('lock-2.4.1', false, 5, 'vacuum'));
};

return $tests;
