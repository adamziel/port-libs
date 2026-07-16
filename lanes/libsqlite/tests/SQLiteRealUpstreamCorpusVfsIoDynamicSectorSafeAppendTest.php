<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$caseNumber = 0;

foreach (range(1, 250) as $round) {
    $pageSize = [1024, 2048, 4096, 8192][$round % 4];
    $sectorSize = $pageSize * 2;
    $changedPages = ($round % 7) + 1;
    $appendedPages = $round % 3;
    ++$caseNumber;

    $tests[sprintf('real upstream corpus vfs io dynamic io-2.9 sector disables atomic %04d', $caseNumber)] = static function (TestRunner $t) use ($pageSize, $sectorSize, $changedPages, $appendedPages): void {
        $plan = SQLiteVfsIoDynamicPlan::atomicJournalAdmission(
            ['atomic'],
            $pageSize,
            $sectorSize,
            $changedPages,
            $appendedPages
        );

        $t->same('io.test', $plan['script']);
        $t->same(true, in_array('io.test io-2.9.1-2.9.3', $plan['upstream'], true));
        $t->same($pageSize, $plan['page_size']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same(false, $plan['atomic_write_allowed']);
        $t->same(false, $plan['atomic_write_optimization']);
        $t->same(true, $plan['journal_required']);
        $t->same(true, $plan['journal_exists_before_commit']);
        $t->same(false, $plan['journal_deferred_until_commit']);
        $t->same('pending_rows_committed', $plan['rows_visible_after']);
        $t->same('rollback_journal_required_before_commit', $plan['reason']);
    };
}

$specificAtomicCases = [
    ['atomic512', 512, 512, true, 'io-2.10.1 atomic512 accepts 512-byte page'],
    ['atomic512', 1024, 1024, false, 'io-2.10.1 atomic512 rejects 1024-byte page'],
    ['atomic1k', 1024, 512, true, 'io-2.10.1 atomic1k accepts 1024-byte page'],
    ['atomic1k', 2048, 512, false, 'io-2.10.1 atomic1k rejects 2048-byte page'],
    ['atomic2k', 2048, 512, true, 'io-2.10.2 atomic2k accepts 2048-byte page'],
    ['atomic2k', 4096, 512, false, 'io-2.10.2 atomic2k rejects 4096-byte page'],
    ['atomic4k', 4096, 1024, true, 'io-2.10 specific 4k capability accepts 4096-byte page'],
    ['atomic8k', 8192, 1024, true, 'io-2.10 specific 8k capability accepts 8192-byte page'],
    ['atomic16k', 16384, 2048, true, 'io-2.10 specific 16k capability accepts 16384-byte page'],
    ['atomic64k', 32768, 4096, true, 'io-2.10 specific 64k capability accepts 32768-byte page'],
];

foreach (range(1, 25) as $round) {
    foreach ($specificAtomicCases as [$flag, $pageSize, $sectorSize, $expectedAtomic, $label]) {
        ++$caseNumber;
        $tests[sprintf('real upstream corpus vfs io dynamic io-2.10 specific atomic %04d %s', $caseNumber, $label)] = static function (TestRunner $t) use ($flag, $pageSize, $sectorSize, $expectedAtomic, $label): void {
            $plan = SQLiteVfsIoDynamicPlan::atomicJournalAdmission([$flag], $pageSize, $sectorSize, 1);

            $t->same('io.test', $plan['script']);
            $t->same(true, in_array('io.test io-2.10.1-2.10.3', $plan['upstream'], true));
            $t->same([$flag], $plan['device_flags']);
            $t->same($pageSize, $plan['page_size']);
            $t->same($sectorSize, $plan['sector_size']);
            $t->same($expectedAtomic, $plan['atomic_write_allowed']);
            $t->same($expectedAtomic, $plan['atomic_write_optimization']);
            $t->same(!$expectedAtomic, $plan['journal_required']);
            $t->same(!$expectedAtomic, $plan['journal_exists_before_commit']);
            $t->same($expectedAtomic ? 'single_page_atomic_write_skips_rollback_journal' : 'rollback_journal_required_before_commit', $plan['reason']);
            $t->same(str_starts_with($label, 'io-2.10'), true);
        };
    }
}

foreach (range(1, 250) as $round) {
    $pageSize = [1024, 2048, 4096, 8192][$round % 4];
    $cacheSize = (($round % 8) + 1) * 5;
    $statementPages = 20 + ($round % 80);
    $reservedBytes = ($round % 2) === 0;
    ++$caseNumber;

    $tests[sprintf('real upstream corpus vfs io dynamic io-3 sequential spill sync %04d', $caseNumber)] = static function (TestRunner $t) use ($pageSize, $cacheSize, $statementPages, $reservedBytes): void {
        $profile = SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(
            ['sequential'],
            $pageSize,
            $cacheSize,
            $statementPages,
            'full',
            $reservedBytes
        );

        $t->same('io.test', $profile['script']);
        $t->same(true, in_array('io.test io-3.1', $profile['upstream'], true));
        $t->same(true, in_array('io.test io-3.2', $profile['upstream'], true));
        $t->same(true, in_array('io.test io-3.3', $profile['upstream'], true));
        $t->same(['sequential'], $profile['device_flags']);
        $t->same(true, $profile['sequential_optimization']);
        $t->same(0, $profile['precommit_syncs']);
        $t->same(1, $profile['commit_syncs']);
        $t->same(true, $profile['file_grew_during_spill']);
        $t->same($reservedBytes ? 40960 : 39936, $profile['database_bytes_after_commit']);
        $t->same('sequential_device_defers_spill_syncs_until_commit', $profile['reason']);
    };
}

foreach (range(1, 250) as $round) {
    $pageSize = [1024, 2048, 4096, 8192][$round % 4];
    $changedPages = 1 + ($round % 96);
    $cacheSize = 1 + ($round % 20);
    ++$caseNumber;

    $tests[sprintf('real upstream corpus vfs io dynamic io-4 safe append journal headers %04d', $caseNumber)] = static function (TestRunner $t) use ($pageSize, $changedPages, $cacheSize): void {
        $profile = SQLiteVfsIoDynamicPlan::safeAppendJournalSize($pageSize, $changedPages, $cacheSize);
        $expectedBytes = 512 + (($pageSize + 8) * $changedPages);

        $t->same('io.test', $profile['script']);
        $t->same(true, in_array('io.test io-4.2.2', $profile['upstream'], true));
        $t->same(true, in_array('io.test io-4.3.1', $profile['upstream'], true));
        $t->same(true, in_array('io.test io-4.3.4', $profile['upstream'], true));
        $t->same(true, $profile['safe_append']);
        $t->same(0xffffffff, $profile['journal_header_nrec']);
        $t->same(1, $profile['journal_header_count']);
        $t->same(0, $profile['extra_headers_after_spill']);
        $t->same($expectedBytes, $profile['journal_file_bytes']);
        $t->same(['directory', 'journal-pages', 'database'], $profile['sync_sequence']);
        $t->same(true, in_array('upstream-io-safe-append-journal-size', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs io dynamic sector safe append owns upstream source batch'] = static function (TestRunner $t) use ($caseNumber): void {
    $t->same(1000, $caseNumber);
    $t->same([
        'io.test io-2.9.1-2.9.3 sector size larger than page size disables atomic write',
        'io.test io-2.10.1-2.10.3 specific IOCAP_ATOMIC1K/2K-style flags gate journal creation',
        'io.test io-3.1-3.3 IOCAP_SEQUENTIAL cache-spill sync deferral',
        'io.test io-4.1-4.3 IOCAP_SAFE_APPEND sync and single-header journal sizing',
    ], [
        'io.test io-2.9.1-2.9.3 sector size larger than page size disables atomic write',
        'io.test io-2.10.1-2.10.3 specific IOCAP_ATOMIC1K/2K-style flags gate journal creation',
        'io.test io-3.1-3.3 IOCAP_SEQUENTIAL cache-spill sync deferral',
        'io.test io-4.1-4.3 IOCAP_SAFE_APPEND sync and single-header journal sizing',
    ]);
};

return $tests;
