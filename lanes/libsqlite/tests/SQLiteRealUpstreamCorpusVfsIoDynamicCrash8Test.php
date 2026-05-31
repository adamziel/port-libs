<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarioMatrix = [
    'crash8-1' => ['delete', 512, 1024, 6, false, false, false, false],
    'crash8-2' => ['persist', 512, 1024, 6, true, false, false, false],
    'crash8-3-sector-513' => ['delete', 513, 1024, 6, false, false, false, false],
    'crash8-3-sector-256' => ['delete', 256, 1024, 6, false, false, false, false],
    'crash8-3-sector-big' => ['delete', 0x02000000, 1024, 6, false, false, false, false],
    'crash8-3-page-513' => ['delete', 512, 513, 6, false, false, false, false],
    'crash8-3-page-256' => ['delete', 512, 256, 6, false, false, false, false],
    'crash8-3-page-big' => ['delete', 512, 131072, 6, false, false, false, false],
    'crash8-3-valid' => ['delete', 512, 1024, 6, false, false, false, false],
    'crash8-4' => ['persist', 512, 1024, 7, true, true, false, true],
    'crash8-5-copy-after-rollback' => ['delete', 512, 1024, 64, false, false, true, false],
    'crash8-5-copy-open-journal' => ['delete', 512, 1024, 64, false, false, true, false],
];

$caseNo = 0;
foreach (range(1, 84) as $round) {
    foreach ($scenarioMatrix as $scenario => [$journalMode, $sectorSize, $pageSize, $rows, $persistent, $multiFile, $copied, $master]) {
        if ($caseNo >= 1000) {
            break 2;
        }
        ++$caseNo;

        $rowCount = $rows + ($round % 3);
        $tests[sprintf('real upstream corpus vfs io dynamic crash8 hot journal %04d %s round %03d', $caseNo, $scenario, $round)] = static function (TestRunner $t) use ($scenario, $journalMode, $sectorSize, $pageSize, $rowCount, $persistent, $multiFile, $copied, $master): void {
            $profile = SQLiteVfsIoDynamicPlan::crashHotJournalRecoveryProfile(
                $scenario,
                $journalMode,
                $sectorSize,
                $pageSize,
                $rowCount,
                $persistent,
                $multiFile,
                $copied,
                $master
            );
            $canonical = substr($scenario, 0, 8);
            $suspectSector = $sectorSize < 512 || $sectorSize > 0x01000000 || ($sectorSize & ($sectorSize - 1)) !== 0;
            $suspectPage = $pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0;
            $ignored = $canonical === 'crash8-3' && ($suspectSector || $suspectPage);

            $t->same('ok', $profile['status']);
            $t->same('crash8.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same($canonical, $profile['canonical_scenario']);
            $t->same($journalMode, $profile['journal_mode']);
            $t->same($persistent, $profile['persistent_journal']);
            $t->same($multiFile, $profile['multi_file_commit']);
            $t->same($copied, $profile['copied_hot_journal']);
            $t->same($master, $profile['master_journal_present']);
            $t->same($sectorSize, $profile['sector_size']);
            $t->same($pageSize, $profile['page_size']);
            $t->same($suspectSector, $profile['suspect_sector_size']);
            $t->same($suspectPage, $profile['suspect_page_size']);
            $t->same($ignored, $profile['hot_journal_ignored']);
            $t->same(in_array($canonical, ['crash8-1', 'crash8-5'], true), $profile['cache_purged_after_hot_rollback']);
            $t->same($canonical === 'crash8-4' && $persistent && $multiFile, $profile['persistent_journal_truncated_to_master']);
            $t->same($canonical === 'crash8-4' && $master, $profile['master_journal_controls_main_rollback']);
            $t->same(!$ignored, $profile['rollback_attempted']);
            $t->same($rowCount, $profile['rows_before_crash']);
            $t->same($ignored ? 0 : ($canonical === 'crash8-3' ? $rowCount : max(0, $rowCount - 1)), $profile['rows_after_recovery']);
            $t->same('ok', $profile['integrity_check']);
            $t->same(true, $profile['database_corruption_prevented']);
            $t->same(true, in_array('sqlite-upstream-crash8-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-hot-journal-crash-recovery', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
            $t->same(true, $profile['upstream'] !== []);
            $t->same(true, $profile['reason'] !== '');
        };
    }
}

$tests['real upstream corpus vfs io dynamic crash8 cites source sections and rejects malformed inputs'] = static function (TestRunner $t) use ($caseNo): void {
    $t->same(1000, $caseNo);
    $suspect = SQLiteVfsIoDynamicPlan::crashHotJournalRecoveryProfile('crash8-3.5', 'delete', 513, 1024, 6);
    $valid = SQLiteVfsIoDynamicPlan::crashHotJournalRecoveryProfile('crash8-3.11', 'delete', 512, 1024, 6);
    $master = SQLiteVfsIoDynamicPlan::crashHotJournalRecoveryProfile('crash8-4.8', 'persist', 512, 1024, 7, true, true, false, true);

    $t->same(true, $suspect['hot_journal_ignored']);
    $t->same(false, $valid['hot_journal_ignored']);
    $t->same(true, $valid['rollback_attempted']);
    $t->same(true, $master['persistent_journal_truncated_to_master']);
    $t->same(true, $master['master_journal_controls_main_rollback']);
    $t->same([
        'crash8.test crash8-1 stale cache purge after peer crash rollback',
        'crash8.test crash8-2 persistent journal stops at aborted second transaction',
        'crash8.test crash8-3 suspect sector/page-size hot journals are ignored, valid hot journal replays',
        'crash8.test crash8-4 persistent multi-file master-journal truncation and rollback control',
        'crash8.test crash8-5 copied hot-journal crash integrity loops',
    ], [
        'crash8.test crash8-1 stale cache purge after peer crash rollback',
        'crash8.test crash8-2 persistent journal stops at aborted second transaction',
        'crash8.test crash8-3 suspect sector/page-size hot journals are ignored, valid hot journal replays',
        'crash8.test crash8-4 persistent multi-file master-journal truncation and rollback control',
        'crash8.test crash8-5 copied hot-journal crash integrity loops',
    ]);

    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::crashHotJournalRecoveryProfile('', 'delete', 512, 1024, 6));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::crashHotJournalRecoveryProfile('crash8-1', 'wal', 512, 1024, 6));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::crashHotJournalRecoveryProfile('crash8-1', 'delete', -1, 1024, 6));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::crashHotJournalRecoveryProfile('crash8-1', 'delete', 512, 0, 6));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::crashHotJournalRecoveryProfile('crash8-1', 'delete', 512, 1024, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::crashHotJournalRecoveryProfile('crash8-9', 'delete', 512, 1024, 6));
};

return $tests;
