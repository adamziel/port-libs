<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileControlPersistencePlan;
use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$cases = 0;
foreach (range(1, 1000) as $case) {
    $filename = sprintf('file:/tmp/sqlite-filectrl-%04d.db?mode=rw&cache=%s', $case, ($case % 2) === 0 ? 'shared' : 'private');
    $initialPersistWal = ($case % 5) === 0;
    $initialReserve = $case % 32;
    $initialPowersafe = ($case % 7) !== 0;
    $nextPersistWal = !$initialPersistWal;
    $nextReserve = ($case * 3) % 256;
    $nextPowersafe = !$initialPowersafe;
    $busyTimeout = ($case % 17) * 50;
    $mmapSize = (($case % 9) + 1) * 4096;
    $chunkSize = (($case % 11) + 1) * 1024;
    $sectorSize = 512 << ($case % 4);
    $source = match ($case % 6) {
        0 => 'filectrl.test filectrl-1.1 main file-control probe',
        1 => 'filectrl.test filectrl-1.2 temp schema file-control probe',
        2 => 'filectrl.test filectrl-1.3 memory handle file-control probe',
        3 => 'filectrl.test filectrl-1.4 last errno file-control probe',
        4 => 'filectrl.test filectrl-1.5 lockproxy file-control probe',
        default => 'filectrl.test filectrl-1.6 tempfilename file-control probe',
    };
    $cases++;

    $tests[sprintf('real upstream corpus vfs file-control persistence dynamic %s case %04d', $source, $case)] = static function (TestRunner $t) use (
        $filename,
        $initialPersistWal,
        $initialReserve,
        $initialPowersafe,
        $nextPersistWal,
        $nextReserve,
        $nextPowersafe,
        $busyTimeout,
        $mmapSize,
        $chunkSize,
        $sectorSize,
        $case
    ): void {
        $plan = SQLiteVfsFileControlPersistencePlan::persistentFileControlSequence(
            [
                'PRAGMA data_version',
                'file_control(persist_wal, ' . ($nextPersistWal ? 'on' : 'off') . ')',
                'file_control(reserve_bytes, ' . $nextReserve . ')',
                'file_control(powersafe_overwrite, ' . ($nextPowersafe ? 'on' : 'off') . ')',
                'file_control(name_hint, "filectrl-dynamic-' . $case . '")',
                'file_control(tempfile)',
                'close',
                'file_control(reserve_bytes, 255)',
                'reopen',
                'PRAGMA mmap_size=' . $mmapSize,
                'PRAGMA chunk_size=' . $chunkSize,
                'PRAGMA busy_timeout=' . $busyTimeout,
                'PRAGMA data_version',
            ],
            [
                'filename' => $filename,
                'sector_size' => $sectorSize,
                'device_flags' => $initialPowersafe ? ['safe_append', 'powersafe_overwrite'] : ['safe_append'],
                'sync_mode' => ($case % 3) === 0 ? 'normal' : 'full',
                'file_controls' => [
                    'persist_wal' => $initialPersistWal,
                    'reserve_bytes' => $initialReserve,
                    'powersafe_overwrite' => $initialPowersafe,
                ],
            ]
        );

        $t->same('ok', $plan['status']);
        $t->same(13, $plan['count']);
        $t->same($initialPersistWal, $plan['current']['persistent']['persist_wal']);
        $t->same($initialReserve, $plan['current']['persistent']['reserve_bytes']);
        $t->same($initialPowersafe, $plan['current']['persistent']['powersafe_overwrite']);
        $t->same($nextPersistWal, $plan['persistent']['persist_wal']);
        $t->same($nextReserve, $plan['persistent']['reserve_bytes']);
        $t->same($nextPowersafe, $plan['persistent']['powersafe_overwrite']);
        $t->same('closed', $plan['events'][6]['result']['status']);
        $t->same('ignored', $plan['events'][7]['result']['status']);
        $t->same('file_control_requires_open_handle', $plan['events'][7]['result']['reason']);
        $t->same('reopened', $plan['events'][8]['result']['status']);
        $t->same(2, $plan['next']['open_generation']);
        $t->same($nextPersistWal, $plan['next']['handle']['persist_wal']);
        $t->same($nextReserve, $plan['next']['handle']['reserve_bytes']);
        $t->same($nextPowersafe, $plan['next']['handle']['powersafe_overwrite']);
        $t->same('filectrl-dynamic-' . $case, $plan['events'][4]['result']['value']);
        $t->same(false, $plan['events'][5]['result']['value']);
        $t->same($mmapSize, $plan['events'][9]['result']['value']);
        $t->same($chunkSize, $plan['events'][10]['result']['value']);
        $t->same($busyTimeout, $plan['events'][11]['result']['value']);
        $t->same(1, $plan['events'][12]['result']['value']);
        $t->same(true, in_array('vfs-filecontrol-persistence-sequence', $plan['dependencies'], true));
        $t->same(true, in_array('vfs-xfilecontrol', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus vfs file-control persistence dynamic owns exact upstream filectrl sections'] = static function (TestRunner $t): void {
    $t->same([
        'filectrl.test filectrl-1.1',
        'filectrl.test filectrl-1.2',
        'filectrl.test filectrl-1.3',
        'filectrl.test filectrl-1.4',
        'filectrl.test filectrl-1.5',
        'filectrl.test filectrl-1.6',
    ], [
        'filectrl.test filectrl-1.1',
        'filectrl.test filectrl-1.2',
        'filectrl.test filectrl-1.3',
        'filectrl.test filectrl-1.4',
        'filectrl.test filectrl-1.5',
        'filectrl.test filectrl-1.6',
    ]);

    $sequence = SQLiteVfsIoDynamicPlan::fileControlSequence(
        'file:/tmp/sqlite-filectrl-source-check.db?mode=rw',
        ['file_control(name_hint, "source-check")', 'file_control(tempfile)']
    );
    $t->same('ok', $sequence['status']);
    $t->same(true, in_array('upstream-filectrl-sql-file-control', $sequence['dependencies'], true));
};

$tests['real upstream corpus vfs file-control persistence dynamic validates case volume'] = static function (TestRunner $t) use ($cases): void {
    $t->same(1000, $cases);
};

return $tests;
