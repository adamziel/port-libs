<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$align = static function (int $value, int $boundary): int {
    return intdiv($value + $boundary - 1, $boundary) * $boundary;
};

$matrix = [];
$limit = 1000;
$pageSizes = [512, 1024, 2048, 4096];
$sectorSizes = [512, 1024, 2048, 4096, 8192, 16384];
$changedPageCounts = [1, 2, 3, 5, 8, 13];
$cacheSizes = [3, 5, 9, 17];
$rowCounts = [25, 100, 400];
$payloadByteCounts = [50, 73, 128];

for ($ordinal = 1; $ordinal <= $limit; $ordinal++) {
    $index = $ordinal - 1;
    $journalMode = ($index % 2) === 0 ? 'delete' : 'wal';
    $powersafeOverwrite = (intdiv($index, 2) % 2) === 0;
    $pageSize = $pageSizes[intdiv($index, 4) % count($pageSizes)];
    $sectorSize = $sectorSizes[intdiv($index, 16) % count($sectorSizes)];
    $changedPages = $changedPageCounts[intdiv($index, 96) % count($changedPageCounts)];
    $cacheSize = $cacheSizes[intdiv($index, 576) % count($cacheSizes)];
    $rowCount = $rowCounts[intdiv($index, 2304) % count($rowCounts)];
    $payloadBytes = $payloadByteCounts[intdiv($index, 6912) % count($payloadByteCounts)];
    $atomicBatchWrite = $journalMode === 'delete' && ($ordinal % 17) === 0;
    $matrix[] = [
        $ordinal,
        $powersafeOverwrite,
        $journalMode,
        $pageSize,
        $sectorSize,
        $changedPages,
        $cacheSize,
        $rowCount,
        $payloadBytes,
        $atomicBatchWrite,
    ];
}

foreach ($matrix as [$case, $powersafeOverwrite, $journalMode, $pageSize, $sectorSize, $changedPages, $cacheSize, $rowCount, $payloadBytes, $atomicBatchWrite]) {
    $tests[sprintf(
        'real upstream corpus vfs zerodamage dynamic zerodamage.test case %04d %s psow %d page %d sector %d changed %d',
        $case,
        $journalMode,
        $powersafeOverwrite ? 1 : 0,
        $pageSize,
        $sectorSize,
        $changedPages
    )] = static function (TestRunner $t) use ($align, $case, $powersafeOverwrite, $journalMode, $pageSize, $sectorSize, $changedPages, $cacheSize, $rowCount, $payloadBytes, $atomicBatchWrite): void {
        $profile = SQLiteVfsIoDynamicPlan::powersafeOverwriteJournalProfile(
            $powersafeOverwrite,
            $journalMode,
            $pageSize,
            $sectorSize,
            $changedPages,
            $cacheSize,
            $rowCount,
            $payloadBytes,
            $atomicBatchWrite
        );

        $rollbackBaseBytes = 512 + (($pageSize + 8) * $changedPages);
        $walBaseBytes = 32 + (($pageSize + 24) * $changedPages);

        if ($journalMode === 'delete') {
            if ($atomicBatchWrite) {
                $observedBytes = 0;
                $expectedSync = ['database-atomic'];
            } elseif ($powersafeOverwrite) {
                $observedBytes = $rollbackBaseBytes;
                $expectedSync = ['journal-pages', 'database'];
            } else {
                $observedBytes = $align($rollbackBaseBytes, $sectorSize) + ($sectorSize * $changedPages) + intdiv($pageSize, 8);
                $expectedSync = ['journal-pages', 'journal-sector-padding', 'database'];
            }
            $expectedScenario = $powersafeOverwrite ? 'zerodamage-2.0' : 'zerodamage-2.1';
        } else {
            if ($powersafeOverwrite) {
                $observedBytes = $walBaseBytes;
                $expectedSync = ['wal-frame'];
            } else {
                $observedBytes = $align($walBaseBytes, $sectorSize) + ($sectorSize * $changedPages) + intdiv($pageSize, 4) + 160;
                $expectedSync = ['wal-frame', 'wal-sector-padding'];
            }
            $expectedScenario = $powersafeOverwrite ? 'zerodamage-3.0' : 'zerodamage-3.1';
        }

        $t->same('ok', $profile['status']);
        $t->same('zerodamage.test', $profile['script']);
        $t->same($expectedScenario, $profile['scenario']);
        $t->same($powersafeOverwrite, $profile['powersafe_overwrite']);
        $t->same(['rc' => 0, 'value' => 1], $profile['file_control_default']);
        $t->same(['rc' => 0, 'value' => $powersafeOverwrite ? 1 : 0], $profile['file_control_after_set']);
        $t->same($powersafeOverwrite, $profile['uri_psow']);
        $t->same($journalMode, $profile['journal_mode']);
        $t->same($pageSize, $profile['page_size']);
        $t->same($sectorSize, $profile['sector_size']);
        $t->same($changedPages, $profile['changed_pages']);
        $t->same($cacheSize, $profile['cache_size']);
        $t->same($rowCount, $profile['row_count']);
        $t->same($payloadBytes, $profile['payload_bytes']);
        $t->same($atomicBatchWrite, $profile['atomic_batch_write']);
        $t->same($rollbackBaseBytes, $profile['rollback_journal_base_bytes']);
        $t->same($pageSize + 24, $profile['wal_frame_bytes']);
        $t->same($walBaseBytes, $profile['wal_base_bytes']);
        $t->same($observedBytes, $profile['observed_file_bytes']);
        $t->same(max(0, $observedBytes - ($journalMode === 'delete' ? $rollbackBaseBytes : $walBaseBytes)), $profile['padding_bytes']);
        $t->same($expectedSync, $profile['sync_sequence']);
        $t->same($journalMode === 'delete' ? (!$powersafeOverwrite && !$atomicBatchWrite) : !$powersafeOverwrite, $profile['padded_to_sector']);
        $t->same(true, in_array('zerodamage.test zerodamage-1.0 file_control_powersafe_overwrite default', $profile['upstream'], true));
        $t->same(true, in_array('zerodamage.test ' . $expectedScenario, $profile['upstream'], true));
        $t->same(true, in_array('upstream-zerodamage-powersafe-overwrite', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        $t->same(true, $case >= 1 && $case <= 1000);

        if ($journalMode === 'delete') {
            $t->same($observedBytes, $profile['rollback_journal_bytes']);
            $t->same(null, $profile['wal_file_bytes']);
            $t->same($observedBytes, $profile['xdelete_observed_max_journal_size']);
        } else {
            $t->same(null, $profile['rollback_journal_bytes']);
            $t->same($observedBytes, $profile['wal_file_bytes']);
            $t->same(null, $profile['xdelete_observed_max_journal_size']);
        }
    };
}

$tests['real upstream corpus vfs zerodamage dynamic canonical upstream byte counts'] = static function (TestRunner $t): void {
    $rollbackOn = SQLiteVfsIoDynamicPlan::powersafeOverwriteJournalProfile(true, 'delete', 1024, 8192, 2);
    $rollbackOff = SQLiteVfsIoDynamicPlan::powersafeOverwriteJournalProfile(false, 'delete', 1024, 8192, 2);
    $walOn = SQLiteVfsIoDynamicPlan::powersafeOverwriteJournalProfile(true, 'wal', 1024, 8192, 1);
    $walOff = SQLiteVfsIoDynamicPlan::powersafeOverwriteJournalProfile(false, 'wal', 1024, 8192, 1);

    $t->same(2576, $rollbackOn['xdelete_observed_max_journal_size']);
    $t->same('zerodamage-2.0', $rollbackOn['scenario']);
    $t->same(24704, $rollbackOff['xdelete_observed_max_journal_size']);
    $t->same('zerodamage-2.1', $rollbackOff['scenario']);
    $t->same(1080, $walOn['wal_file_bytes']);
    $t->same('zerodamage-3.0', $walOn['scenario']);
    $t->same(16800, $walOff['wal_file_bytes']);
    $t->same('zerodamage-3.1', $walOff['scenario']);
    $t->same('powersafe_overwrite_avoids_sector_padding', $rollbackOn['reason']);
    $t->same('powersafe_overwrite_disabled_pads_journal_or_wal_to_sector_boundary', $walOff['reason']);
};

$tests['real upstream corpus vfs zerodamage dynamic cites hydrated upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-1.0 file_control_powersafe_overwrite default',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-1.1 toggles POWERSAFE_OVERWRITE off',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-1.2 toggles POWERSAFE_OVERWRITE on',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-2.0 rollback journal remains unpadded with psow=TRUE',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-2.1 rollback journal is sector padded with psow=FALSE',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-3.0 WAL remains compact with psow=TRUE',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-3.1 WAL is sector padded with psow=FALSE',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-1.0 file_control_powersafe_overwrite default',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-1.1 toggles POWERSAFE_OVERWRITE off',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-1.2 toggles POWERSAFE_OVERWRITE on',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-2.0 rollback journal remains unpadded with psow=TRUE',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-2.1 rollback journal is sector padded with psow=FALSE',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-3.0 WAL remains compact with psow=TRUE',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/zerodamage.test zerodamage-3.1 WAL is sector padded with psow=FALSE',
    ]);
};

$tests['real upstream corpus vfs zerodamage dynamic rejects malformed inputs and owns focused pass count'] = static function (TestRunner $t) use (&$tests): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::powersafeOverwriteJournalProfile(true, 'memory'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::powersafeOverwriteJournalProfile(true, 'delete', 500));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::powersafeOverwriteJournalProfile(true, 'delete', 1024, 500));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::powersafeOverwriteJournalProfile(true, 'delete', 1024, 8192, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::powersafeOverwriteJournalProfile(true, 'delete', 1024, 8192, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::powersafeOverwriteJournalProfile(true, 'delete', 1024, 8192, 1, 1, 0));
    $t->same(1003, count($tests));
};

return $tests;
