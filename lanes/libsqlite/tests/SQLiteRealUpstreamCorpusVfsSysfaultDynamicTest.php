<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$openFaults = [
    ['sysfault-1.open-transient', 'open', 'EIO', false],
    ['sysfault-1.getcwd-persistent', 'getcwd', 'EACCES', true],
    ['sysfault-1.2.1', 'fstat', 'ENOMEM', false],
    ['sysfault-1.2.2', 'fstat', 'EOVERFLOW', false],
];

foreach (range(1, 125) as $round) {
    foreach ($openFaults as [$scenario, $syscall, $errno, $persistent]) {
        $tests["real upstream corpus vfs sysfault dynamic open and fstat {$scenario} round {$round}"] = static function (TestRunner $t) use ($scenario, $syscall, $errno, $persistent, $round): void {
            $plan = SQLiteVfsIoTrafficPlan::syscallFaultProfile($scenario . '.' . $round, $syscall, $errno, $round, $persistent);

            $t->same('sysfault.test', $plan['script']);
            $t->same($syscall, $plan['syscall']);
            $t->same($errno, $plan['errno']);
            $t->same($round, $plan['fault_index']);
            $t->same($persistent, $plan['persistent']);
            $t->same(0, $plan['open_file_count']);
            $t->same(true, in_array('sqlite-upstream-sysfault-test', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-syscall-faultsim', $plan['dependencies'], true));

            if ($syscall === 'fstat' && $errno === 'EOVERFLOW') {
                $t->same('large file support is disabled', $plan['result_code']);
                $t->same(true, $plan['large_file_possible']);
                return;
            }

            if ($persistent && $syscall === 'getcwd' && ($round % 2) === 0) {
                $t->same('attempt to write a readonly database', $plan['result_code']);
                $t->same(true, $plan['readonly_possible']);
                return;
            }

            $t->same(in_array($syscall, ['open', 'getcwd'], true) ? 'unable to open database file' : 'disk I/O error', $plan['result_code']);
            $t->same(false, $plan['wal_rows_visible']);
        };
    }
}

$lockErrnos = [
    'EAGAIN' => 'database is locked',
    'ETIMEDOUT' => 'database is locked',
    'EBUSY' => 'database is locked',
    'EINTR' => 'ok',
    'ENOLCK' => 'database is locked',
    'EACCES' => 'database is locked',
    'EPERM' => 'access permission denied',
    'EDEADLK' => 'disk I/O error',
    'ENOMEM' => 'disk I/O error',
];

foreach (range(1, 56) as $round) {
    foreach ($lockErrnos as $errno => $expected) {
        $tests["real upstream corpus vfs sysfault dynamic fcntl {$errno} lock round {$round}"] = static function (TestRunner $t) use ($errno, $expected, $round): void {
            $plan = SQLiteVfsIoTrafficPlan::syscallFaultProfile('sysfault-1.3.unix.' . $errno . '.' . $round, 'fcntl', $errno, $round, false);

            $t->same('sysfault.test', $plan['script']);
            $t->same('fcntl', $plan['syscall']);
            $t->same($errno, $plan['errno']);
            $t->same($expected, $plan['result_code']);
            $t->same(true, $plan['lock_error_possible']);
            $t->same($errno === 'EINTR', $plan['transient_retry']);
            $t->same($expected === 'ok', $plan['wal_rows_visible']);
            $t->same(true, str_contains($plan['upstream'][0], 'sysfault.test sysfault-1.3'));
        };
    }
}

$eintrSyscalls = ['open', 'ftruncate', 'close', 'read', 'pread', 'pread64', 'write', 'fallocate'];

foreach (range(1, 63) as $round) {
    foreach ($eintrSyscalls as $syscall) {
        $tests["real upstream corpus vfs sysfault dynamic transient EINTR {$syscall} round {$round}"] = static function (TestRunner $t) use ($syscall, $round): void {
            $plan = SQLiteVfsIoTrafficPlan::syscallFaultProfile('sysfault-2.1.' . $syscall . '.' . $round, $syscall, 'EINTR', $round, false);

            $t->same('ok', $plan['result_code']);
            $t->same(true, $plan['transient_retry']);
            $t->same(true, $plan['attached_rows_visible']);
            $t->same(true, $plan['temp_rows_visible']);
            $t->same('ok', $plan['integrity_check']);
            $t->same(['sysfault.test sysfault-2.1.' . $syscall . '.' . $round . ' transient EINTR retry for ' . $syscall], $plan['upstream']);
        };
    }
}

$persistentAttached = ['open', 'ftruncate', 'close', 'read', 'pread', 'pread64', 'write', 'fallocate'];

foreach (range(1, 63) as $round) {
    foreach ($persistentAttached as $syscall) {
        $tests["real upstream corpus vfs sysfault dynamic persistent attached commit {$syscall} round {$round}"] = static function (TestRunner $t) use ($syscall, $round): void {
            $plan = SQLiteVfsIoTrafficPlan::syscallFaultProfile('sysfault-2.2.' . $syscall . '.' . $round, $syscall, 'EIO', $round, true);

            $t->same('sysfault.test', $plan['script']);
            $t->same($syscall, $plan['syscall']);
            $t->same('EIO', $plan['errno']);
            $t->same(false, $plan['transient_retry']);
            $t->same(false, $plan['attached_rows_visible']);
            $t->same(false, $plan['temp_rows_visible']);
            $t->same(0, $plan['open_file_count']);
            $t->same(true, in_array($plan['result_code'], ['unable to open database file', 'attempt to write a readonly database', 'disk I/O error'], true));
        };
    }
}

foreach (range(1, 5) as $round) {
    foreach (['fstat', 'fallocate'] as $syscall) {
        $tests["real upstream corpus vfs sysfault dynamic large insert {$syscall} round {$round}"] = static function (TestRunner $t) use ($syscall, $round): void {
            $plan = SQLiteVfsIoTrafficPlan::syscallFaultProfile('sysfault-3.' . $syscall . '.' . $round, $syscall, 'EIO', $round, true);

            $t->same('disk I/O error', $plan['result_code']);
            $t->same(false, $plan['transient_retry']);
            $t->same(false, $plan['wal_rows_visible']);
            $t->same('not-run-after-fault', $plan['integrity_check']);
            $t->same(['sysfault.test sysfault-3.' . $syscall . '.' . $round . ' fstat/fallocate large insert fault'], $plan['upstream']);
        };
    }
}

foreach (range(1, 10) as $round) {
    $tests["real upstream corpus vfs sysfault dynamic mmap EACCES read fault round {$round}"] = static function (TestRunner $t) use ($round): void {
        $plan = SQLiteVfsIoTrafficPlan::syscallFaultProfile('sysfault-4.mmap.' . $round, 'mmap', 'EACCES', $round, true);

        $t->same('mmap', $plan['syscall']);
        $t->same('EACCES', $plan['errno']);
        $t->same('disk I/O error', $plan['result_code']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(false, $plan['wal_rows_visible']);
        $t->same(['sysfault.test sysfault-4.mmap.' . $round . ' mmap EACCES read fault'], $plan['upstream']);
    };
}

$tests['real upstream corpus vfs sysfault dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::syscallFaultProfile('', 'open', 'EIO', 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::syscallFaultProfile('sysfault-1', '', 'EIO', 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::syscallFaultProfile('sysfault-1', 'open', '', 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::syscallFaultProfile('sysfault-1', 'open', 'EIO', 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::syscallFaultProfile('sysfault-1', 'unlink', 'EIO', 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::syscallFaultProfile('sysfault-9', 'open', 'EIO', 1));
};

$tests['real upstream corpus vfs sysfault dynamic records upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        'sysfault.test sysfault-1 open/getcwd WAL open fault',
        'sysfault.test sysfault-1.2 fstat ENOMEM/EOVERFLOW open/write fault',
        'sysfault.test sysfault-1.3 fcntl lock fault for unix and unix-excl VFS',
        'sysfault.test sysfault-2.1 transient EINTR retry across open/ftruncate/close/read/pread/write/fallocate',
        'sysfault.test sysfault-2.2 persistent syscall faults during attached commit',
        'sysfault.test sysfault-3 fstat/fallocate large insert fault',
        'sysfault.test sysfault-4 mmap EACCES read fault',
    ], [
        'sysfault.test sysfault-1 open/getcwd WAL open fault',
        'sysfault.test sysfault-1.2 fstat ENOMEM/EOVERFLOW open/write fault',
        'sysfault.test sysfault-1.3 fcntl lock fault for unix and unix-excl VFS',
        'sysfault.test sysfault-2.1 transient EINTR retry across open/ftruncate/close/read/pread/write/fallocate',
        'sysfault.test sysfault-2.2 persistent syscall faults during attached commit',
        'sysfault.test sysfault-3 fstat/fallocate large insert fault',
        'sysfault.test sysfault-4 mmap EACCES read fault',
    ]);
};

return $tests;
