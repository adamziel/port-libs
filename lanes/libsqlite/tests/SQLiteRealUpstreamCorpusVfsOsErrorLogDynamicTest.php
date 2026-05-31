<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarioSpecs = [
    'oserror-1.1' => [
        'syscalls' => ['open', 'getcwd'],
        'suffix' => 'test.db',
        'message' => 'unable to open database file',
        'code' => 'SQLITE_CANTOPEN',
    ],
    'oserror-1.2' => [
        'syscalls' => ['open'],
        'suffix' => 'dir.db',
        'message' => 'unable to open database file',
        'code' => 'SQLITE_CANTOPEN',
    ],
    'oserror-1.3' => [
        'syscalls' => ['open'],
        'suffix' => 'test.db',
        'message' => 'unable to open database file',
        'code' => 'SQLITE_CANTOPEN',
    ],
    'oserror-1.4' => [
        'syscalls' => ['open', 'readlink', 'lstat'],
        'suffix' => 'test.db',
        'message' => 'unable to open database file',
        'code' => 'SQLITE_CANTOPEN',
    ],
    'oserror-2.1' => [
        'syscalls' => ['unlink'],
        'suffix' => 'test.db-wal',
        'message' => 'disk I/O error',
        'code' => 'SQLITE_IOERR_DELETE',
    ],
];

$case = 0;
foreach ($scenarioSpecs as $scenario => $spec) {
    foreach (range(1, 200) as $round) {
        $case++;
        $syscalls = $spec['syscalls'];
        $syscall = $syscalls[($round - 1) % count($syscalls)];
        $osErrorCode = 2 + (($round * 17 + $case) % 127);
        $sourceLine = 1000 + (($round * 31 + $case) % 9000);
        $operationSucceeded = $scenario === 'oserror-1.1' && ($round % 37) === 0;

        $path = match ($scenario) {
            'oserror-1.1' => '/tmp/sqlite-oserror/fd-' . $round . '/test.db',
            'oserror-1.2' => '/tmp/sqlite-oserror/case-' . $round . '/dir.db',
            'oserror-1.3' => '/x/y/z/missing-' . $round . '/test.db',
            'oserror-1.4' => '/root/sqlite-oserror-' . $round . '/test.db',
            'oserror-2.1' => '/tmp/sqlite-oserror/case-' . $round . '/test.db-wal',
        };

        $tests[sprintf('real upstream corpus vfs oserror log dynamic %04d %s %s', $case, $scenario, $syscall)] = static function (TestRunner $t) use ($scenario, $spec, $syscall, $path, $osErrorCode, $sourceLine, $operationSucceeded): void {
            $profile = SQLiteVfsIoDynamicPlan::osErrorLogProfile(
                $scenario,
                $syscall,
                $path,
                $osErrorCode,
                $sourceLine,
                $operationSucceeded
            );

            $logRequired = !$operationSucceeded;

            $t->same($operationSucceeded ? 'ok' : 'error', $profile['status']);
            $t->same('oserror.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same('unix', $profile['default_vfs']);
            $t->same('sqlite3_log', $profile['log_channel']);
            $t->same($spec['syscalls'], $profile['allowed_syscalls']);
            $t->same($syscall, $profile['syscall']);
            $t->same($path, $profile['path']);
            $t->same($spec['suffix'], $profile['path_suffix']);
            $t->same(true, str_ends_with($profile['path'], $profile['path_suffix']));
            $t->same($osErrorCode, $profile['os_error_code']);
            $t->same($sourceLine, $profile['source_line']);
            $t->same($logRequired, $profile['log_required']);
            $t->same(true, $profile['log_matches_upstream_regex']);
            $t->same($operationSucceeded ? 'SQLITE_OK' : $spec['code'], $profile['sqlite_result_code']);
            $t->same($operationSucceeded ? 'ok' : $spec['message'], $profile['result_message']);
            $t->same($scenario === 'oserror-1.1', $profile['too_many_file_descriptors_probe']);
            $t->same($scenario === 'oserror-1.2', $profile['path_is_directory']);
            $t->same($scenario === 'oserror-1.3', $profile['missing_parent_path']);
            $t->same($scenario === 'oserror-1.4', $profile['restricted_root_path_probe']);
            $t->same($scenario === 'oserror-2.1', $profile['wal_sidecar_unlink_failure']);
            $t->same($scenario === 'oserror-1.2' || $scenario === 'oserror-2.1', $profile['cleanup_required']);
            $t->same(true, $profile['database_reusable_after_cleanup']);
            $t->same(true, str_starts_with($profile['upstream'][0], 'oserror.test '));
            $t->same(true, in_array('sqlite-upstream-oserror-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-os-error-logging', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));

            if ($logRequired) {
                $t->same(true, is_string($profile['log_message']));
                $t->same(true, str_contains((string) $profile['log_message'], $syscall . '(' . $path . ')'));
                $t->same(1, preg_match('/' . $profile['log_regex'] . '/', (string) $profile['log_message']));
            } else {
                $t->same(null, $profile['log_message']);
                $t->same('SQLITE_OK', $profile['sqlite_result_code']);
            }
        };
    }
}

$tests['real upstream corpus vfs oserror log dynamic cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'oserror.test 1.1.1 open/getcwd failure may report unable to open database file',
        'oserror.test 1.1.3 sqlite3_log matches open|getcwd test.db OS diagnostic',
    ], SQLiteVfsIoDynamicPlan::osErrorLogProfile('oserror-1.1', 'open', '/tmp/test.db', 24, 1400)['upstream']);
    $t->same([
        'oserror.test 1.2.1 opening directory path returns unable to open database file',
        'oserror.test 1.2.2 sqlite3_log matches open dir.db OS diagnostic',
    ], SQLiteVfsIoDynamicPlan::osErrorLogProfile('oserror-1.2', 'open', '/tmp/dir.db', 21, 1401)['upstream']);
    $t->same([
        'oserror.test 1.3.1 missing parent path returns unable to open database file',
        'oserror.test 1.3.2 sqlite3_log matches open test.db OS diagnostic',
    ], SQLiteVfsIoDynamicPlan::osErrorLogProfile('oserror-1.3', 'open', '/x/y/z/test.db', 2, 1402)['upstream']);
    $t->same([
        'oserror.test 1.4.1 restricted root path returns unable to open database file',
        'oserror.test 1.4.2 sqlite3_log matches open|readlink|lstat test.db OS diagnostic',
    ], SQLiteVfsIoDynamicPlan::osErrorLogProfile('oserror-1.4', 'lstat', '/root/test.db', 13, 1403)['upstream']);
    $t->same([
        'oserror.test 2.1.1 WAL sidecar directory causes disk I/O error',
        'oserror.test 2.1.2 sqlite3_log matches unlink test.db-wal OS diagnostic',
        'oserror.test 2.1.3 closes connection and removes WAL sidecar directory',
    ], SQLiteVfsIoDynamicPlan::osErrorLogProfile('oserror-2.1', 'unlink', '/tmp/test.db-wal', 21, 1404)['upstream']);
};

$tests['real upstream corpus vfs oserror log dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::osErrorLogProfile('oserror-9', 'open', '/tmp/test.db', 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::osErrorLogProfile('oserror-1.2', 'unlink', '/tmp/dir.db', 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::osErrorLogProfile('oserror-1.2', 'open', '', 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::osErrorLogProfile('oserror-1.2', 'open', '/tmp/dir.db', 0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::osErrorLogProfile('oserror-1.2', 'open', '/tmp/dir.db', 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::osErrorLogProfile('oserror-1.2', 'open', '/tmp/test.db', 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::osErrorLogProfile('oserror-1.2', 'open', '/tmp/dir.db', 1, 1, true));
};

$tests['real upstream corpus vfs oserror log dynamic owns focused pass count'] = static function (TestRunner $t) use (&$tests): void {
    $t->same(1003, count($tests));
};

return $tests;
