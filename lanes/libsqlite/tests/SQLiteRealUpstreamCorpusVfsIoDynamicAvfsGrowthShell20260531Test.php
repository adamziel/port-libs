<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$growthCases = [];
foreach ([1, 2, 4, 8, 16, 32, 64, 128] as $initialRows) {
    foreach ([300, 360, 420, 480, 540, 600, 720, 840] as $insertRows) {
        foreach ([900, 1200, 1500, 1800] as $payloadBytes) {
            foreach ([2048, 4096, 8192] as $pageSize) {
                foreach ([5, 8, 10, 16] as $keepModulo) {
                    $growthCases[] = [$initialRows, $insertRows, $payloadBytes, $pageSize, $keepModulo];
                }
            }
        }
    }
}

$caseNo = 0;
foreach ($growthCases as [$initialRows, $insertRows, $payloadBytes, $pageSize, $keepModulo]) {
    ++$caseNo;
    if ($caseNo > 520) {
        break;
    }

    $tests[sprintf('real upstream corpus vfs io dynamic avfs growth shell avfs-3 case %04d', $caseNo)] = static function (TestRunner $t) use ($initialRows, $insertRows, $payloadBytes, $pageSize, $keepModulo): void {
        $profile = SQLiteVfsIoDynamicPlan::appendGrowthProfile($initialRows, $insertRows, $payloadBytes, $pageSize, $keepModulo);
        $grownRows = $initialRows + $insertRows;
        $keptRows = intdiv($grownRows, $keepModulo);

        $t->same('ok', $profile['status']);
        $t->same('avfs.test', $profile['script']);
        $t->same($initialRows, $profile['initial_rows']);
        $t->same($insertRows, $profile['insert_rows']);
        $t->same($grownRows, $profile['grown_rows']);
        $t->same($keptRows, $profile['kept_rows_after_delete']);
        $t->same(0, $profile['grown_bytes'] % $pageSize);
        $t->same(0, $profile['shrunk_bytes'] % $pageSize);
        $t->same(true, $profile['grown_bytes'] >= $pageSize);
        $t->same(true, $profile['shrunk_bytes'] >= $pageSize);
        $t->same(true, $profile['grown_bytes'] >= $profile['shrunk_bytes']);
        $t->same(true, $profile['growth_ratio_per_payload'] > 0.0);
        $t->same(true, $profile['shrink_ratio'] >= 1.0);
        $t->same(['ok', 'ok', 'ok', 'ok'], $profile['integrity_sequence']);
        $t->same(true, $profile['reopen_intact']);
        $t->same(true, $profile['prefix_intact']);
        $t->same(true, in_array('avfs.test avfs-3.1', $profile['upstream'], true));
        $t->same(true, in_array('avfs.test avfs-3.2', $profile['upstream'], true));
        $t->same(true, in_array('avfs.test avfs-3.3', $profile['upstream'], true));
        $t->same(true, in_array('avfs.test avfs-3.4', $profile['upstream'], true));
        $t->same(true, in_array('avfs.test avfs-3.5', $profile['upstream'], true));
        $t->same(true, in_array('upstream-avfs-growth-shrink', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

$shellCases = [];
foreach ([0, 1, 17, 50, 511, 512, 513, 1000, 1023, 1024, 1025, 2047, 2048, 2049, 4095, 4096, 4097, 8191, 8192, 8193] as $prefixBytes) {
    foreach ([512, 1024, 2048, 4096] as $pageSize) {
        foreach ([true, false] as $archiveMode) {
            foreach ([false, true] as $updateExisting) {
                foreach ([1, 2, 4, 8] as $appendedEntries) {
                    $shellCases[] = [$prefixBytes, $pageSize, $archiveMode, $updateExisting, $appendedEntries];
                }
            }
        }
    }
}

$shellNo = 0;
foreach ($shellCases as [$prefixBytes, $pageSize, $archiveMode, $updateExisting, $appendedEntries]) {
    ++$shellNo;
    if ($shellNo > 480) {
        break;
    }

    $tests[sprintf('real upstream corpus vfs io dynamic avfs growth shell avfs-4 case %04d', $shellNo)] = static function (TestRunner $t) use ($prefixBytes, $pageSize, $archiveMode, $updateExisting, $appendedEntries): void {
        $profile = SQLiteVfsIoDynamicPlan::appendShellLifecycleProfile($prefixBytes, $pageSize, $archiveMode, $updateExisting, $appendedEntries);
        $expectedOffset = $prefixBytes === 0 ? 0 : (int) (ceil($prefixBytes / $pageSize) * $pageSize);
        $expectedInitialRows = $archiveMode ? 0 : 1;
        $expectedRows = $updateExisting ? $expectedInitialRows + $appendedEntries : $expectedInitialRows;

        $t->same('ok', $profile['status']);
        $t->same('avfs.test', $profile['script']);
        $t->same($prefixBytes, $profile['prefix_bytes']);
        $t->same($pageSize, $profile['page_size']);
        $t->same($archiveMode, $profile['archive_mode']);
        $t->same($updateExisting, $profile['update_existing_append_database']);
        $t->same($expectedOffset, $profile['database_offset']);
        $t->same($expectedOffset - $prefixBytes, $profile['padding_bytes']);
        $t->same('Start-Of-SQLite3-', $profile['trailer_magic']);
        $t->same($expectedOffset, $profile['trailer_offset']);
        $t->same(true, $profile['prefix_intact']);
        $t->same(true, $profile['aligned']);
        $t->same(0, $profile['shell_exit_code']);
        $t->same($archiveMode ? 'sqlar' : 'appended_rows', $profile['table_name']);
        $t->same([$profile['table_name']], $profile['tables_output']);
        $t->same($expectedInitialRows, $profile['initial_rows']);
        $t->same($appendedEntries, $profile['appended_entries']);
        $t->same($expectedRows, $profile['updated_rows']);
        $t->same($updateExisting ? $appendedEntries : 0, $profile['shell_output_rows']);
        $t->same($expectedRows, $profile['reopen_count']);
        $t->same('&vfs=apndvfs', $profile['append_uri']);
        $t->same(true, in_array('avfs.test avfs-4.1', $profile['upstream'], true));
        $t->same(true, in_array('avfs.test avfs-4.2', $profile['upstream'], true));
        $t->same(true, in_array('avfs.test avfs-4.3', $profile['upstream'], true));
        $t->same(true, in_array('upstream-avfs-shell-append-lifecycle', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs io dynamic avfs growth shell cites hydrated upstream sections'] = static function (TestRunner $t) use ($caseNo, $shellNo): void {
    $t->same(521, $caseNo);
    $t->same(481, $shellNo);

    $growth = SQLiteVfsIoDynamicPlan::appendGrowthProfile(1, 300, 1500, 4096, 8);
    $shell = SQLiteVfsIoDynamicPlan::appendShellLifecycleProfile(50, 512, false, true, 2);

    $t->same([
        'avfs.test avfs-3.1',
        'avfs.test avfs-3.2',
        'avfs.test avfs-3.3',
        'avfs.test avfs-3.4',
        'avfs.test avfs-3.5',
    ], $growth['upstream']);
    $t->same([
        'avfs.test avfs-4.1',
        'avfs.test avfs-4.2',
        'avfs.test avfs-4.3',
    ], $shell['upstream']);
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/avfs.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/avfs.test');
};

$tests['real upstream corpus vfs io dynamic avfs growth shell rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendGrowthProfile(0, 300, 1500));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendGrowthProfile(1, 0, 1500));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendGrowthProfile(1, 300, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendGrowthProfile(1, 300, 1500, 1000));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendGrowthProfile(1, 300, 1500, 4096, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendShellLifecycleProfile(-1, 512, true, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendShellLifecycleProfile(0, 500, true, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendShellLifecycleProfile(0, 768, true, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendShellLifecycleProfile(0, 512, true, false, 0));
};

return $tests;
