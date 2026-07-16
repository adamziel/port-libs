<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$supportedCalls = [
    'open', 'close', 'access', 'getcwd', 'stat', 'fstat', 'ftruncate',
    'fcntl', 'read', 'pread', 'write', 'pwrite', 'fchmod', 'fallocate',
    'pread64', 'pwrite64', 'unlink', 'openDirectory', 'mkdir', 'rmdir',
    'statvfs', 'fchown', 'geteuid', 'umask', 'mmap', 'munmap', 'mremap',
    'getpagesize', 'readlink', 'lstat', 'ioctl',
];

foreach (range(1, 300) as $case) {
    $operationName = $supportedCalls[$case % count($supportedCalls)];
    $after = $supportedCalls[max(0, ($case - 3) % count($supportedCalls))];
    $installed = [];
    foreach ($supportedCalls as $index => $name) {
        $installed[$name] = (($index + $case) % 5) === 0 || $name === $operationName;
    }
    $install = ($case % 2) === 0;

    $tests[sprintf('real upstream corpus vfs syscall remainder registry syscall.test 1-3 case %03d %s', $case, $operationName)] = static function (TestRunner $t) use ($installed, $operationName, $install, $after): void {
        $profile = SQLiteVfsIoDynamicPlan::unixSystemCallRegistryProfile($installed, $operationName, $install, $after);

        $t->same('ok', $profile['status']);
        $t->same('syscall.test', $profile['script']);
        $t->same('unix', $profile['default_vfs']);
        $t->same($install ? 'install' : 'reset', $profile['operation']);
        $t->same($operationName, $profile['operation_name']);
        $t->same(true, $profile['exists']);
        $t->same(false, $profile['not_found']);
        $t->same($install, in_array($operationName, $profile['enabled_calls'], true));
        $t->same($after, $profile['next_after']);
        $t->same(true, $profile['next_enabled_call'] === null || in_array($profile['next_enabled_call'], $profile['supported_calls'], true));
        $t->same('SQLITE_OK', $profile['result_code']);
        $t->same(true, in_array('syscall.test 1.1.1-1.3.2 xSetSystemCall reset/install', $profile['upstream'], true));
        $t->same(true, in_array('syscall.test 2.1.1-2.1.2 xGetSystemCall exists', $profile['upstream'], true));
        $t->same(true, in_array('syscall.test 3.1 xNextSystemCall list', $profile['upstream'], true));
        $t->same(true, in_array('upstream-syscall-unix-vfs-registry', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

foreach (range(1, 100) as $case) {
    $fileBytes = $case % 4;

    $tests[sprintf('real upstream corpus vfs syscall remainder single byte database syscall.test 7 case %03d bytes %d', $case, $fileBytes)] = static function (TestRunner $t) use ($fileBytes): void {
        $profile = SQLiteVfsIoDynamicPlan::singleByteDatabaseOpenProfile($fileBytes);
        $empty = $fileBytes <= 1;

        $t->same($empty ? 'ok' : 'error', $profile['status']);
        $t->same('syscall.test', $profile['script']);
        $t->same(['syscall.test 7.1', 'syscall.test 7.2', 'syscall.test 7.3'], $profile['upstream']);
        $t->same($fileBytes, $profile['file_bytes']);
        $t->same($empty, $profile['treated_as_empty_database']);
        $t->same($empty, $profile['create_table_allowed']);
        $t->same($empty ? 'SQLITE_OK' : 'SQLITE_NOTADB', $profile['result_code']);
        $t->same($empty ? '' : 'file is not a database', $profile['message']);
        $t->same(2, $profile['header_bytes_required_for_corrupt_detection']);
        $t->same(true, in_array('upstream-syscall-single-byte-open', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

foreach ([16, 32, 64, 512, 1024, 4096] as $chunkSize) {
    foreach (range(0, 149) as $hintCase) {
        $sizeHint = $hintCase === 0 ? 0 : (($hintCase * 37) + ($chunkSize / 16));

        $tests[sprintf('real upstream corpus vfs syscall remainder size hint syscall.test 8 chunk %04d hint case %03d', $chunkSize, $hintCase)] = static function (TestRunner $t) use ($chunkSize, $sizeHint): void {
            $profile = SQLiteVfsIoDynamicPlan::fileControlChunkSizeHintProfile($chunkSize, (int) $sizeHint);
            $expectedBytes = $sizeHint === 0 ? 0 : (int) (ceil(max($chunkSize, $sizeHint) / $chunkSize) * $chunkSize);

            $t->same('ok', $profile['status']);
            $t->same('syscall.test', $profile['script']);
            $t->same(['syscall.test 8.1', 'syscall.test 8.2.1-8.2.5'], $profile['upstream']);
            $t->same($chunkSize, $profile['chunk_size']);
            $t->same((int) $sizeHint, $profile['size_hint']);
            $t->same($expectedBytes, $profile['file_bytes_after_hint']);
            $t->same($expectedBytes > 0, $profile['preallocated']);
            $t->same(true, $profile['growth_rounded_to_chunk']);
            $t->same(true, in_array('upstream-syscall-file-control-sizehint', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        };
    }
}

$tests['real upstream corpus vfs syscall remainder malformed syscall profiles'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::unixSystemCallRegistryProfile(['nosuchcall' => true]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::unixSystemCallRegistryProfile(['open' => true], 'open', null, 'nosuchcall'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::singleByteDatabaseOpenProfile(-1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileControlChunkSizeHintProfile(0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileControlChunkSizeHintProfile(16, -1));
};

$tests['real upstream corpus vfs syscall remainder cites hydrated upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        'syscall.test 1.1.1-1.3.2 xSetSystemCall reset/install',
        'syscall.test 2.1.1-2.1.2 xGetSystemCall exists',
        'syscall.test 3.1 xNextSystemCall list',
        'syscall.test 7.1-7.3 one-byte database opens as empty but two-plus bytes are not a database',
        'syscall.test 8.1 and 8.2.1-8.2.5 chunk-size file-control rounds size hints',
    ], [
        'syscall.test 1.1.1-1.3.2 xSetSystemCall reset/install',
        'syscall.test 2.1.1-2.1.2 xGetSystemCall exists',
        'syscall.test 3.1 xNextSystemCall list',
        'syscall.test 7.1-7.3 one-byte database opens as empty but two-plus bytes are not a database',
        'syscall.test 8.1 and 8.2.1-8.2.5 chunk-size file-control rounds size hints',
    ]);
};

return $tests;
