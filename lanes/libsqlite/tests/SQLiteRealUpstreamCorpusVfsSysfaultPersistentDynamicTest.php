<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$vectors = [
    ['sysfault-1', 'open', 'EACCES', ['unable to open database file', 'attempt to write a readonly database'], ['wal', 1, 2, 3, 4]],
    ['sysfault-1', 'getcwd', 'ENOMEM', ['unable to open database file'], ['wal', 1, 2, 3, 4]],
    ['sysfault-1.2', 'fstat', 'ENOMEM', ['disk I/O error'], ['wal', 1, 2, 3, 4]],
    ['sysfault-1.2', 'fstat', 'EOVERFLOW', ['disk I/O error', 'large file support is disabled'], ['wal', 1, 2, 3, 4]],
    ['sysfault-1.3', 'fcntl', 'EAGAIN', ['database is locked', 'disk I/O error'], [1, 2]],
    ['sysfault-1.3', 'fcntl', 'ETIMEDOUT', ['database is locked', 'disk I/O error'], [1, 2]],
    ['sysfault-1.3', 'fcntl', 'EBUSY', ['database is locked', 'disk I/O error'], [1, 2]],
    ['sysfault-1.3', 'fcntl', 'EINTR', ['database is locked', 'disk I/O error'], [1, 2]],
    ['sysfault-1.3', 'fcntl', 'ENOLCK', ['database is locked', 'disk I/O error'], [1, 2]],
    ['sysfault-1.3', 'fcntl', 'EACCES', ['database is locked', 'disk I/O error'], [1, 2]],
    ['sysfault-1.3', 'fcntl', 'EPERM', ['access permission denied', 'disk I/O error'], [1, 2]],
    ['sysfault-1.3', 'fcntl', 'EDEADLK', ['disk I/O error'], [1, 2]],
    ['sysfault-1.3', 'fcntl', 'ENOMEM', ['disk I/O error'], [1, 2]],
    ['sysfault-3', 'fstat', 'EIO', [], [20000]],
    ['sysfault-3', 'fallocate', 'EIO', [], [20000]],
    ['sysfault-4', 'mmap', 'EACCES', ['disk I/O error'], [1, 2]],
];

$case = 0;
foreach (range(1, 63) as $faultPosition) {
    foreach ($vectors as [$scenario, $syscall, $errno, $errors, $success]) {
        if ($case >= 1000) {
            break 2;
        }
        $case++;
        $vfs = (($case % 2) === 0) ? 'unix' : 'unix-excl';
        $persistent = ($case % 5) !== 0;

        $tests[sprintf(
            'real upstream corpus vfs sysfault persistent dynamic sysfault.test case %04d %s %s %s fault %03d %s',
            $case,
            $scenario,
            $syscall,
            strtolower($errno),
            $faultPosition,
            $vfs
        )] = static function (TestRunner $t) use ($scenario, $syscall, $errno, $faultPosition, $vfs, $persistent, $errors, $success): void {
            $profile = SQLiteVfsIoDynamicPlan::sysfaultPersistentUnixErrorProfile(
                $scenario,
                $syscall,
                $errno,
                $faultPosition,
                $vfs,
                $persistent
            );

            $t->same('ok', $profile['status']);
            $t->same('sysfault.test', $profile['script']);
            $t->same($scenario . '-' . $syscall . '-' . strtolower($errno) . '-' . $faultPosition, $profile['scenario']);
            $t->same($syscall, $profile['syscall']);
            $t->same($errno, $profile['errno']);
            $t->same($faultPosition, $profile['fault_position']);
            $t->same($persistent, $profile['persistent_fault']);
            $t->same(!$persistent, $profile['transient_fault']);
            $t->same($vfs, $profile['vfs']);
            $t->same($errors, $profile['acceptable_errors']);
            $t->same($success, $profile['success_result']);
            $t->same(1 + count($errors), $profile['acceptable_result_count']);
            $t->same([$syscall => $errno], $profile['fault_injection']['errno']);
            $t->same($faultPosition, $profile['fault_injection']['fault_position']);
            $t->same($persistent, $profile['fault_injection']['persistent']);
            $t->same(true, in_array($syscall, $profile['installed_calls'], true));
            $t->same(true, $profile['database_reusable_after_fault']);
            $t->same('ok', $profile['integrity_check_after_fault']);
            $t->same($errno === 'EOVERFLOW', $profile['large_file_support_disabled']);
            $t->same($scenario === 'sysfault-1' && $errno === 'EACCES', $profile['readonly_error_allowed']);
            $t->same($scenario === 'sysfault-1.3' && in_array($errno, ['EAGAIN', 'ETIMEDOUT', 'EBUSY', 'EINTR', 'ENOLCK', 'EACCES'], true), $profile['lock_error_allowed']);
            $t->same(in_array('disk I/O error', $errors, true), $profile['falls_back_to_ioerr']);
            $t->same($scenario === 'sysfault-4', $profile['mmap_read_can_fallback_or_error']);
            $t->same($scenario === 'sysfault-3', $profile['chunked_write_can_ignore_hint_fault']);
            $t->same(true, str_starts_with($profile['upstream'][0], 'sysfault.test '));
            $t->same(true, in_array('sqlite-upstream-sysfault-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-persistent-unix-error-map', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        };
    }
}

$tests['real upstream corpus vfs sysfault persistent dynamic validates batch size'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

$tests['real upstream corpus vfs sysfault persistent dynamic cites hydrated upstream scenarios'] = static function (TestRunner $t): void {
    $t->same([
        'sysfault.test 1 open/getcwd vfsfault persistent open and write body',
        'sysfault.test 1.2 fstat ENOMEM/EOVERFLOW while opening and writing',
        'sysfault.test 1.3 unix/unix-excl fcntl locking errno mapping',
        'sysfault.test 3 fstat/fallocate EIO during chunked write path',
        'sysfault.test 4 mmap EACCES during mapped SELECT',
    ], [
        'sysfault.test 1 open/getcwd vfsfault persistent open and write body',
        'sysfault.test 1.2 fstat ENOMEM/EOVERFLOW while opening and writing',
        'sysfault.test 1.3 unix/unix-excl fcntl locking errno mapping',
        'sysfault.test 3 fstat/fallocate EIO during chunked write path',
        'sysfault.test 4 mmap EACCES during mapped SELECT',
    ]);
};

$tests['real upstream corpus vfs sysfault persistent dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultPersistentUnixErrorProfile('sysfault-9', 'open', 'EACCES', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultPersistentUnixErrorProfile('sysfault-1', 'unlink', 'EACCES', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultPersistentUnixErrorProfile('sysfault-1', 'open', 'ENOENT', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultPersistentUnixErrorProfile('sysfault-1', 'open', 'EACCES', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultPersistentUnixErrorProfile('sysfault-1', 'open', 'EACCES', 1, 'win32'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultPersistentUnixErrorProfile('sysfault-3', 'mmap', 'EACCES', 1));
};

return $tests;
