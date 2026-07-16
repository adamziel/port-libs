<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTransactionSequencePlan;

$tests = [];

$baseSteps = [
    ['name' => 'io-2.2 rollback journal insert', 'pages_written' => 2, 'pages_touched' => 1, 'journal_created' => true],
    ['name' => 'io-2.3 atomic insert', 'pages_written' => 2, 'pages_touched' => 1],
    ['name' => 'io-2.5 multi-page transaction', 'pages_written' => 3, 'pages_touched' => 2],
    ['name' => 'io-2.6 append-page transaction', 'pages_written' => 3, 'pages_touched' => 1, 'appends_page' => true],
];

$corpusCases = [
    'io-2.2 normal rollback journal writes two pages and four syncs' => [[$baseSteps[0]], [], [2, 4, 1, false, ['journal-header', 'journal-pages', 'directory', 'database']]],
    'io-2.3 atomic write avoids journal and keeps one database sync' => [[$baseSteps[1]], ['atomic'], [2, 1, 0, true, ['database']]],
    'io-2.3 atomic4k marker alone keeps ordinary no-journal commit shape' => [[$baseSteps[1]], ['atomic4k'], [2, 1, 0, false, ['database']]],
    'io-2.5 atomic falls back when multiple pages are touched' => [[$baseSteps[2]], ['atomic'], [3, 4, 1, false, ['journal-header', 'journal-pages', 'directory', 'database']]],
    'io-2.6 atomic falls back when transaction appends a page' => [[$baseSteps[3]], ['atomic'], [3, 4, 1, false, ['journal-header', 'journal-pages', 'directory', 'database']]],
    'io-3 sequential device skips journal page sync' => [[$baseSteps[0]], ['sequential'], [2, 3, 1, false, ['journal-header', 'directory', 'database']]],
    'io-4 safe append skips journal header sync' => [[$baseSteps[0]], ['safe_append'], [2, 3, 1, false, ['journal-pages', 'directory', 'database']]],
    'io-3 and io-4 combined keep only directory and database syncs' => [[$baseSteps[0]], ['sequential', 'safe_append'], [2, 2, 1, false, ['directory', 'database']]],
];

foreach ($corpusCases as $name => [$steps, $flags, $expected]) {
    $tests['real upstream corpus vfs io transaction sequence ' . $name] = static function (TestRunner $t) use ($steps, $flags, $expected): void {
        $plan = SQLiteVfsIoTransactionSequencePlan::transactionSequence($steps, $flags);
        $step = $plan['steps'][0];

        $t->same('ok', $plan['status']);
        $t->same(1, $plan['count']);
        $t->same($expected[0], $plan['write_total']);
        $t->same($expected[1], $plan['sync_total']);
        $t->same($expected[2], $plan['journal_creates']);
        $t->same($expected[0], $step['writes']);
        $t->same($expected[1], $step['syncs']);
        $t->same($expected[2] === 1, $step['journal_created']);
        $t->same($expected[3], $step['atomic_write']);
        $t->same($expected[4], $step['sync_reasons']);
        $t->same(true, in_array('real-upstream-corpus-io-test', $plan['dependencies'], true));
        $t->same(true, in_array('io.test io-2.3', $plan['upstream'], true));
    };
}

$sequenceCases = [
    'io-2.2 then io-2.3 keeps honest totals without merging behavior' => [[$baseSteps[0], $baseSteps[1]], ['atomic'], 4, 5, 1],
    'io-2.5 then io-2.6 records two real journal creations' => [[$baseSteps[2], $baseSteps[3]], ['atomic'], 6, 8, 2],
    'io-3 sequential sequence reduces both journal page syncs' => [[$baseSteps[0], $baseSteps[2]], ['sequential'], 5, 6, 2],
    'io-4 safe append sequence reduces both journal header syncs' => [[$baseSteps[0], $baseSteps[2]], ['safe_append'], 5, 6, 2],
    'io-3 and io-4 combined sequence records minimal rollback syncs' => [[$baseSteps[0], $baseSteps[2]], ['sequential', 'safe_append'], 5, 4, 2],
];

foreach ($sequenceCases as $name => [$steps, $flags, $writes, $syncs, $journals]) {
    $tests['real upstream corpus vfs io transaction sequence ' . $name] = static function (TestRunner $t) use ($steps, $flags, $writes, $syncs, $journals): void {
        $plan = SQLiteVfsIoTransactionSequencePlan::transactionSequence($steps, $flags);

        $t->same(count($steps), $plan['count']);
        $t->same($writes, $plan['write_total']);
        $t->same($syncs, $plan['sync_total']);
        $t->same($journals, $plan['journal_creates']);
        $t->same(array_column($steps, 'name'), array_column($plan['steps'], 'name'));
        $t->same(range(0, count($steps) - 1), array_column($plan['steps'], 'ordinal'));
        $t->same(true, in_array('io.test io-2.5.1-2.5.3', $plan['upstream'], true));
        $t->same(true, in_array('io.test io-4.*', $plan['upstream'], true));
    };
}

$flagCases = [
    'duplicate capability flags are normalized' => [['atomic', 'ATOMIC', 'safe_append'], ['atomic', 'safe_append']],
    'blank capability flags are ignored' => [['', ' ', 'sequential'], ['sequential']],
    'powersafe overwrite is preserved as a capability marker' => [['powersafe_overwrite'], ['powersafe_overwrite']],
];

foreach ($flagCases as $name => [$flags, $expectedFlags]) {
    $tests['real upstream corpus vfs io transaction sequence ' . $name] = static function (TestRunner $t) use ($baseSteps, $flags, $expectedFlags): void {
        $plan = SQLiteVfsIoTransactionSequencePlan::transactionSequence([$baseSteps[0]], $flags);

        $t->same($expectedFlags, $plan['steps'][0]['flags']);
        $t->same('ok', $plan['steps'][0]['status']);
        $t->same('io-2.2 rollback journal insert', $plan['steps'][0]['name']);
    };
}

$sectorAtomicCases = [
    'io-2.9.1 atomic sector larger than page uses rollback journal' => [['atomic'], 1024, 2048, false, 0, true],
    'io-2.9.2 atomic sector equal to page avoids rollback journal' => [['atomic'], 2048, 2048, true, 2048, false],
    'io-2.10.1 atomic1k cannot cover a 2048 byte page' => [['atomic', 'atomic1k'], 2048, 2048, false, 1024, true],
    'io-2.10.2 atomic2k covers a 2048 byte page' => [['atomic', 'atomic2k'], 2048, 2048, true, 2048, false],
    'io-2.10.2 atomic2k normalized mixed case covers page' => [['ATOMIC', 'atomic2K'], 2048, 2048, true, 2048, false],
    'io-2.10.1 atomic512 cannot cover a 1024 byte page' => [['atomic', 'atomic512'], 1024, 1024, false, 512, true],
    'io-2.10 atomic4k covers a 4096 byte page' => [['atomic', 'atomic4k'], 4096, 4096, true, 4096, false],
    'io-2.10 atomic64k covers a 32768 byte page' => [['atomic', 'atomic64k'], 32768, 32768, true, 65536, false],
];

foreach ($sectorAtomicCases as $name => [$flags, $pageSize, $sectorSize, $atomic, $atomicBytes, $journalCreated]) {
    $tests['real upstream corpus vfs io transaction sequence ' . $name] = static function (TestRunner $t) use ($baseSteps, $flags, $pageSize, $sectorSize, $atomic, $atomicBytes, $journalCreated): void {
        $plan = SQLiteVfsIoTransactionSequencePlan::transactionSequence([$baseSteps[1]], $flags, $pageSize, $sectorSize);
        $step = $plan['steps'][0];

        $t->same('ok', $plan['status']);
        $t->same($pageSize, $step['page_size']);
        $t->same($sectorSize, $step['sector_size']);
        $t->same($atomicBytes, $step['atomic_bytes']);
        $t->same($atomic, $step['atomic_write']);
        $t->same($journalCreated, $step['journal_created']);
        $t->same($journalCreated ? 1 : 0, $plan['journal_creates']);
        $t->same($journalCreated ? ['journal-header', 'journal-pages', 'directory', 'database'] : ['database'], $step['sync_reasons']);
        $t->same(true, in_array('io.test io-2.9.1-2.9.3', $plan['upstream'], true));
        $t->same(true, in_array('io.test io-2.10.1-2.10.3', $plan['upstream'], true));
    };
}

$defaultPageCases = [
    'io-5.1 default 512 byte sector selects 1024 page' => [[], 512, 1024],
    'io-5.2 default 1024 byte sector selects 1024 page' => [[], 1024, 1024],
    'io-5.3 default 2048 byte sector selects 2048 page' => [[], 2048, 2048],
    'io-5.4 default 8192 byte sector selects 8192 page' => [[], 8192, 8192],
    'io-5.5 large sector clamps to maximum default page' => [[], 16384, 8192],
    'io-5.6 generic atomic selects maximum default page' => [['atomic'], 512, 8192],
    'io-5.7 atomic512 keeps minimum default page' => [['atomic512'], 512, 1024],
    'io-5.8 atomic2k selects 2048 page' => [['atomic2K'], 512, 2048],
    'io-5.9 atomic2k with larger sector selects 4096 page' => [['atomic2K'], 4096, 4096],
    'io-5.10 generic atomic dominates explicit atomic2k' => [['atomic2K', 'atomic'], 512, 8192],
    'io-5.11 atomic64k larger than maximum keeps minimum default page' => [['atomic64K'], 512, 1024],
];

foreach ($defaultPageCases as $name => [$flags, $sectorSize, $expectedPageSize]) {
    $tests['real upstream corpus vfs io default page size ' . $name] = static function (TestRunner $t) use ($flags, $sectorSize, $expectedPageSize): void {
        $plan = SQLiteVfsIoTransactionSequencePlan::defaultPageSize($flags, $sectorSize);

        $t->same('ok', $plan['status']);
        $t->same('io.test', $plan['script']);
        $t->same('io-5.*', $plan['scenario']);
        $t->same($sectorSize, $plan['sector_size']);
        $t->same($expectedPageSize, $plan['selected_page_size']);
        $t->same(true, in_array('io.test io-5.*', $plan['upstream'], true));
        $t->same(true, in_array('vfs-io-default-page-size', $plan['dependencies'], true));
        $t->same(true, in_array('real-upstream-corpus-io-test', $plan['dependencies'], true));
    };
}

$defaultFlagMatrix = [
    'none' => [[], null],
    'atomic512' => [['atomic512'], 512],
    'atomic1k' => [['atomic1k'], 1024],
    'atomic2k' => [['atomic2k'], 2048],
    'atomic4k' => [['atomic4k'], 4096],
    'atomic8k' => [['atomic8k'], 8192],
    'atomic16k' => [['atomic16k'], 16384],
    'atomic64k' => [['atomic64k'], 65536],
    'atomic-generic' => [['atomic'], 8192],
    'atomic2k-generic' => [['atomic2k', 'atomic'], 8192],
];
$defaultSectorMatrix = [512, 768, 1024, 1536, 2048, 3072, 4096, 6144, 8192, 12288, 16384, 32768, 65536];
$defaultMaxMatrix = [1024, 2048, 4096, 8192, 16384, 32768, 65536, 131072];
$defaultCaseOrdinal = 0;

foreach ($defaultFlagMatrix as $flagName => [$flags, $atomicBytes]) {
    foreach ($defaultSectorMatrix as $sectorSize) {
        foreach ($defaultMaxMatrix as $maxPageSize) {
            $defaultCaseOrdinal++;
            $tests["real upstream corpus vfs io default page size matrix {$defaultCaseOrdinal} {$flagName} sector {$sectorSize} max {$maxPageSize}"] = static function (TestRunner $t) use ($flags, $sectorSize, $maxPageSize, $atomicBytes): void {
                $plan = SQLiteVfsIoTransactionSequencePlan::defaultPageSize($flags, $sectorSize, $maxPageSize);
                $expected = 1024;

                if (in_array('atomic', array_map(static fn ($flag): string => strtolower((string) $flag), $flags), true)) {
                    $expected = $maxPageSize;
                } elseif ($atomicBytes !== null && $atomicBytes <= $maxPageSize) {
                    $expected = max($expected, $atomicBytes);
                }
                if ($sectorSize > $expected) {
                    $nextPowerOfTwo = 1;
                    while ($nextPowerOfTwo < $sectorSize) {
                        $nextPowerOfTwo <<= 1;
                    }
                    $expected = min($nextPowerOfTwo, $maxPageSize);
                }

                $t->same('io.test', $plan['script']);
                $t->same('io-5.*', $plan['scenario']);
                $t->same($sectorSize, $plan['sector_size']);
                $t->same($maxPageSize, $plan['max_page_size']);
                $t->same($expected, $plan['selected_page_size']);
                $t->same(true, $plan['selected_page_size'] >= 1024);
                $t->same(true, $plan['selected_page_size'] <= $maxPageSize);
                $t->same(0, $plan['selected_page_size'] & ($plan['selected_page_size'] - 1));
                $t->same(true, in_array('vfs-io-default-page-size', $plan['dependencies'], true));
            };
        }
    }
}

$guardCases = [
    'rejects empty transaction sequence' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::transactionSequence([]),
    'rejects unknown capability flag' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::transactionSequence([['pages_written' => 1]], ['networked']),
    'rejects zero pages written' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::transactionSequence([['pages_written' => 0]]),
    'rejects zero page size' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::transactionSequence([['pages_written' => 1]], [], 0),
    'rejects zero sector size' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::transactionSequence([['pages_written' => 1]], [], 1024, 0),
    'rejects default page size zero sector' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::defaultPageSize([], 0),
    'rejects default page size non-power maximum' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::defaultPageSize([], 512, 3000),
];

foreach ($guardCases as $name => $callable) {
    $tests['real upstream corpus vfs io transaction sequence ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $callable);
}

return $tests;
