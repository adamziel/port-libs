<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$registryCases = [
    [['open' => true], 'open', null, null, 'exists', 'SQLITE_OK'],
    [['open' => true], 'nosuchcall', null, null, 'notfound', 'SQLITE_NOTFOUND'],
    [['open' => true], 'open', false, null, 'reset', 'SQLITE_OK'],
    [['open' => false], 'open', true, null, 'install', 'SQLITE_OK'],
    [['open' => true, 'getcwd' => true, 'access' => true], null, null, null, 'list', 'SQLITE_OK'],
];

foreach ($registryCases as $index => [$installed, $operationName, $install, $after, $operation, $resultCode]) {
    $tests[sprintf('real upstream corpus vfs syscall io dynamic syscall.test registry case %02d', $index + 1)] = static function (TestRunner $t) use ($installed, $operationName, $install, $after, $operation, $resultCode): void {
        $profile = SQLiteVfsIoDynamicPlan::unixSystemCallRegistryProfile($installed, $operationName, $install, $after);

        $t->same('syscall.test', $profile['script']);
        $t->same('unix', $profile['default_vfs']);
        $t->same($operation, $profile['operation']);
        $t->same($operationName, $profile['operation_name']);
        $t->same($resultCode, $profile['result_code']);
        $t->same($resultCode === 'SQLITE_NOTFOUND', $profile['not_found']);
        $t->same(true, in_array('syscall.test 1.1.1-1.3.2 xSetSystemCall reset/install', $profile['upstream'], true));
        $t->same(true, in_array('syscall.test 2.1.1-2.1.2 xGetSystemCall exists', $profile['upstream'], true));
        $t->same(true, in_array('syscall.test 3.1 xNextSystemCall list', $profile['upstream'], true));
        $t->same(true, in_array('upstream-syscall-unix-vfs-registry', $profile['dependencies'], true));
    };
}

$enabledMatrix = [
    [['open' => true, 'getcwd' => true, 'access' => true], null, 'open', 3],
    [['open' => true, 'getcwd' => true, 'access' => true], 'open', 'access', 3],
    [['open' => true, 'getcwd' => true, 'access' => true], 'access', 'getcwd', 3],
    [['open' => true, 'getcwd' => true, 'access' => true], 'getcwd', null, 3],
    [['mmap' => true, 'munmap' => true, 'mremap' => true], 'mmap', 'munmap', 3],
    [['pread64' => true, 'pwrite64' => true], 'pread64', 'pwrite64', 2],
];

foreach ($enabledMatrix as $index => [$installed, $after, $next, $count]) {
    $tests[sprintf('real upstream corpus vfs syscall io dynamic syscall.test next call matrix %02d', $index + 1)] = static function (TestRunner $t) use ($installed, $after, $next, $count): void {
        $profile = SQLiteVfsIoDynamicPlan::unixSystemCallRegistryProfile($installed, null, null, $after);

        $t->same('ok', $profile['status']);
        $t->same($after, $profile['next_after']);
        $t->same($next, $profile['next_enabled_call']);
        $t->same($count, $profile['enabled_count']);
        $t->same(array_values(array_filter($profile['supported_calls'], static fn (string $name): bool => isset($installed[$name]) && $installed[$name])), $profile['enabled_calls']);
        $t->same(true, in_array('open', $profile['supported_calls'], true));
        $t->same(true, in_array('mremap', $profile['supported_calls'], true));
        $t->same(true, in_array('ioctl', $profile['supported_calls'], true));
    };
}

foreach ([0, 1, 2, 3, 8, 16] as $fileBytes) {
    $tests[sprintf('real upstream corpus vfs syscall io dynamic syscall.test single byte open %02d', $fileBytes)] = static function (TestRunner $t) use ($fileBytes): void {
        $profile = SQLiteVfsIoDynamicPlan::singleByteDatabaseOpenProfile($fileBytes);
        $empty = $fileBytes <= 1;

        $t->same($empty ? 'ok' : 'error', $profile['status']);
        $t->same('syscall.test', $profile['script']);
        $t->same($fileBytes, $profile['file_bytes']);
        $t->same($empty, $profile['treated_as_empty_database']);
        $t->same($empty, $profile['create_table_allowed']);
        $t->same($empty ? 'SQLITE_OK' : 'SQLITE_NOTADB', $profile['result_code']);
        $t->same($empty ? '' : 'file is not a database', $profile['message']);
        $t->same(2, $profile['header_bytes_required_for_corrupt_detection']);
        $t->same(true, in_array('syscall.test 7.2', $profile['upstream'], true));
        $t->same(true, in_array('upstream-syscall-single-byte-open', $profile['dependencies'], true));
    };
}

$hintCases = [
    [4096, 0, 0],
    [4096, 1000, 4096],
    [4096, 3000, 4096],
    [4096, 4096, 4096],
    [4096, 4197, 8192],
    [8192, 8193, 16384],
    [16384, 32769, 49152],
];

foreach ($hintCases as $index => [$chunkSize, $sizeHint, $expectedBytes]) {
    $tests[sprintf('real upstream corpus vfs syscall io dynamic syscall.test chunk size hint %02d', $index + 1)] = static function (TestRunner $t) use ($chunkSize, $sizeHint, $expectedBytes): void {
        $profile = SQLiteVfsIoDynamicPlan::fileControlChunkSizeHintProfile($chunkSize, $sizeHint);

        $t->same('ok', $profile['status']);
        $t->same('syscall.test', $profile['script']);
        $t->same($chunkSize, $profile['chunk_size']);
        $t->same($sizeHint, $profile['size_hint']);
        $t->same($expectedBytes, $profile['file_bytes_after_hint']);
        $t->same($expectedBytes > 0, $profile['preallocated']);
        $t->same(true, $profile['growth_rounded_to_chunk']);
        $t->same(true, in_array('syscall.test 8.2.1-8.2.5', $profile['upstream'], true));
        $t->same(true, in_array('upstream-syscall-file-control-sizehint', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs syscall io dynamic rejects invalid registry and file controls'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::unixSystemCallRegistryProfile(['not-real' => true]));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::unixSystemCallRegistryProfile(['open' => true], null, null, 'not-real'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::singleByteDatabaseOpenProfile(-1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::fileControlChunkSizeHintProfile(0, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::fileControlChunkSizeHintProfile(4096, -1));
};

$tests['real upstream corpus vfs syscall io dynamic cites hydrated upstream scripts'] = static function (TestRunner $t): void {
    $t->same([
        'syscall.test 1.1.1-1.3.2 xSetSystemCall reset/install',
        'syscall.test 2.1.1-2.1.2 xGetSystemCall exists',
        'syscall.test 3.1 xNextSystemCall list',
        'syscall.test 7.1-7.3 single-byte database file open handling',
        'syscall.test 8.1-8.2.5 xFileControl chunk-size and size-hint growth',
    ], [
        'syscall.test 1.1.1-1.3.2 xSetSystemCall reset/install',
        'syscall.test 2.1.1-2.1.2 xGetSystemCall exists',
        'syscall.test 3.1 xNextSystemCall list',
        'syscall.test 7.1-7.3 single-byte database file open handling',
        'syscall.test 8.1-8.2.5 xFileControl chunk-size and size-hint growth',
    ]);
};

return $tests;
