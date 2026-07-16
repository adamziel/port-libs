<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$flagSets = [
    'plain' => [],
    'atomic' => ['atomic'],
    'atomic512' => ['atomic512'],
    'atomic2k' => ['atomic2k'],
    'safe_append' => ['safe_append'],
    'sequential' => ['sequential'],
    'safe_append_sequential' => ['safe_append', 'sequential'],
    'batch_atomic' => ['batch_atomic'],
];

$journalModes = ['delete', 'truncate', 'wal'];
$syncModes = ['off', 'full'];

$case = 0;
foreach ($flagSets as $flagName => $flags) {
    foreach (range(1, 21) as $changedPages) {
        foreach ($journalModes as $journalMode) {
            foreach ($syncModes as $syncMode) {
                $case++;
                $tests[sprintf(
                    'real upstream corpus vfs io traffic matrix dynamic %04d flags %s pages %02d journal %s sync %s',
                    $case,
                    $flagName,
                    $changedPages,
                    $journalMode,
                    $syncMode
                )] = static function (TestRunner $t) use ($flags, $flagName, $changedPages, $journalMode, $syncMode): void {
                    $plan = SQLiteVfsIoDynamicPlan::ioTrafficPlan($flags, $changedPages, $journalMode, $syncMode);
                    $normalizedFlags = $plan['device_flags'];
                    $rollbackJournal = !in_array($journalMode, ['wal', 'off'], true);
                    $atomicWrite = (in_array('atomic', $normalizedFlags, true) || in_array('batch_atomic', $normalizedFlags, true))
                        && $changedPages <= 2
                        && $rollbackJournal;
                    $safeAppend = in_array('safe_append', $normalizedFlags, true) && $rollbackJournal;
                    $sequential = in_array('sequential', $normalizedFlags, true) && $rollbackJournal;

                    $t->same('ok', $plan['status']);
                    $t->same($changedPages, $plan['changed_pages']);
                    $t->same($journalMode, $plan['journal_mode']);
                    $t->same($syncMode, $plan['sync_mode']);
                    $t->same($changedPages, $plan['database_page_writes']);
                    $t->same($atomicWrite, $plan['atomic_write_optimization']);
                    $t->same($safeAppend, $plan['safe_append_optimization']);
                    $t->same($sequential, $plan['sequential_optimization']);
                    $t->same(count($plan['sync_sequence']), $plan['sync_count']);
                    $t->same(true, in_array('upstream-io-device-characteristics', $plan['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));

                    if ($atomicWrite) {
                        $t->same(0, $plan['journal_page_writes'], 'atomic io.test io-2 avoids rollback journal page writes for ' . $flagName);
                        $t->same(['database'], $plan['sync_sequence']);
                    } elseif ($journalMode === 'wal') {
                        $t->same(0, $plan['journal_page_writes'], 'wal mode routes writes through WAL');
                        $t->same($syncMode === 'off' ? [] : ['wal', 'database'], $plan['sync_sequence']);
                    } elseif ($journalMode === 'off') {
                        $t->same(0, $plan['journal_page_writes'], 'journal off skips rollback journal writes');
                        $t->same([], $plan['sync_sequence']);
                    } else {
                        $t->same($changedPages, $plan['journal_page_writes'], 'rollback journal records changed pages');
                        $t->same(1, $plan['journal_header_writes'], 'rollback journal opens with one header write');
                    }
                };
            }
        }
    }
}

$pageSizeCases = [
    'plain sector 512' => [[], 512, 8192, 1024],
    'plain sector 4096' => [[], 4096, 8192, 4096],
    'plain sector 16384 capped' => [[], 16384, 8192, 8192],
    'atomic max page' => [['atomic'], 512, 8192, 8192],
    'atomic2k raises small sector' => [['atomic2k'], 512, 8192, 2048],
    'atomic512 keeps minimum page' => [['atomic512'], 512, 8192, 1024],
    'atomic64k keeps compatibility page' => [['atomic64k'], 4096, 8192, 1024],
];

foreach ($pageSizeCases as $name => [$flags, $sectorSize, $maxPageSize, $expectedPageSize]) {
    $tests['real upstream corpus vfs io traffic matrix default page size ' . $name] = static function (TestRunner $t) use ($flags, $sectorSize, $maxPageSize, $expectedPageSize): void {
        $plan = SQLiteVfsIoDynamicPlan::defaultPageSizeChoice($flags, $sectorSize, $maxPageSize);

        $t->same('ok', $plan['status']);
        $t->same('io.test', $plan['script']);
        $t->same('io.test io-5', $plan['upstream']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same($maxPageSize, $plan['max_page_size']);
        $t->same($expectedPageSize, $plan['default_page_size']);
        $t->same($expectedPageSize * 2, $plan['file_size_after_create']);
        $t->same(true, in_array('upstream-io-default-page-size', $plan['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus vfs io traffic matrix cites hydrated upstream source'] = static function (TestRunner $t): void {
    $t->contains('/test/io.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test');
    $t->contains('io.test io-2', 'io.test io-2 atomic-write optimization');
    $t->contains('io.test io-3', 'io.test io-3 sequential optimization');
    $t->contains('io.test io-4', 'io.test io-4 safe-append optimization');
    $t->contains('io.test io-5', 'io.test io-5 default page size selection');
};

$tests['real upstream corpus vfs io traffic matrix rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::ioTrafficPlan([], 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::ioTrafficPlan(['unknown'], 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::ioTrafficPlan([], 1, 'memory'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::ioTrafficPlan([], 1, 'delete', 'extra'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::defaultPageSizeChoice([], 256));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::defaultPageSizeChoice([], 512, 1000));
};

return $tests;
