<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTransactionSequencePlan;

$tests = [];

$transactions = [
    'io-6.2.1 two table transaction' => [
        ['table' => 't1', 'pages_touched' => 1, 'row_count' => 1],
        ['table' => 't2', 'pages_touched' => 1, 'row_count' => 1],
    ],
    'io-6.2.2 one table transaction' => [
        ['table' => 't1', 'pages_touched' => 1, 'row_count' => 1],
    ],
];

$cacheSizes = [128, 256, 512, 1024, 1536, 2000, 2500, 4096, 8192];
$databasePages = [32, 64, 96, 128, 192, 256, 384, 512, 768, 1024, 1536, 2048];
$flagSets = [
    'atomic' => ['atomic'],
    'atomic safe append' => ['atomic', 'safe_append'],
    'atomic sequential' => ['atomic', 'sequential'],
    'atomic safe append sequential' => ['atomic', 'safe_append', 'sequential'],
    'atomic4k only' => ['atomic4k'],
];

$caseOrdinal = 0;

foreach ($transactions as $transaction => $writes) {
    foreach ($flagSets as $flagName => $flags) {
        foreach ($cacheSizes as $cacheSize) {
            foreach ($databasePages as $pageCount) {
                $caseOrdinal++;
                $tests["real upstream corpus vfs io pager cache dynamic {$caseOrdinal} {$transaction} {$flagName} cache {$cacheSize} pages {$pageCount}"] =
                    static function (TestRunner $t) use ($transaction, $writes, $flags, $flagName, $cacheSize, $pageCount): void {
                        $plan = SQLiteVfsIoTransactionSequencePlan::pagerCacheAtomicCommitOutcome(
                            $transaction,
                            $writes,
                            $flags,
                            $cacheSize,
                            $pageCount,
                            0
                        );

                        $cacheHoldsDatabase = $cacheSize >= $pageCount;
                        $atomicDevice = in_array('atomic', $flags, true);

                        $t->same('ok', $plan['status']);
                        $t->same('io.test', $plan['script']);
                        $t->same('io-6.1 and io-6.2.*', $plan['scenario']);
                        $t->same($transaction, $plan['transaction']);
                        $t->same($cacheSize, $plan['cache_size']);
                        $t->same($pageCount, $plan['database_pages']);
                        $t->same(0, $plan['mmap_size']);
                        $t->same($atomicDevice, $plan['atomic_device']);
                        $t->same(true, $plan['cache_warmed']);
                        $t->same($cacheHoldsDatabase, $plan['cache_holds_database']);
                        $t->same($flagName === 'atomic4k only' || count($writes) > 1, $plan['requires_journal']);
                        $t->same(!$cacheHoldsDatabase, $plan['pager_cache_flushed']);
                        $t->same(!$cacheHoldsDatabase, $plan['corruption_visible']);
                        $t->same($cacheHoldsDatabase ? 'ok' : 'corruption visible after cache flush', $plan['integrity_check']);
                        $t->same($writes, $plan['writes']);
                        $t->same(true, in_array('vfs-io-pager-cache-atomic-commit', $plan['dependencies'], true));
                        $t->same(true, in_array('real-upstream-corpus-io-test', $plan['dependencies'], true));
                        $t->same([
                            'io.test io-6.1 pager-cache warm setup',
                            'io.test io-6.2.1 two-table atomic-device commit keeps warmed cache',
                            'io.test io-6.2.2 one-table atomic-device commit keeps warmed cache',
                        ], $plan['upstream']);
                    };
            }
        }
    }
}

$mmapCases = [
    'mmap disabled keeps warmed cache authoritative' => [0, false, 'ok'],
    'mmap one byte makes corruption visible after cache flush' => [1, true, 'corruption visible after cache flush'],
    'mmap page sized makes corruption visible after cache flush' => [1024, true, 'corruption visible after cache flush'],
    'mmap large makes corruption visible after cache flush' => [65536, true, 'corruption visible after cache flush'],
];

foreach ($mmapCases as $name => [$mmapSize, $flushed, $integrity]) {
    $tests['real upstream corpus vfs io pager cache dynamic ' . $name] = static function (TestRunner $t) use ($transactions, $mmapSize, $flushed, $integrity): void {
        $plan = SQLiteVfsIoTransactionSequencePlan::pagerCacheAtomicCommitOutcome(
            'io-6.2.2 one table transaction',
            $transactions['io-6.2.2 one table transaction'],
            ['atomic'],
            2000,
            96,
            $mmapSize
        );

        $t->same($mmapSize, $plan['mmap_size']);
        $t->same($flushed, $plan['pager_cache_flushed']);
        $t->same($flushed, $plan['corruption_visible']);
        $t->same($integrity, $plan['integrity_check']);
        $t->same(true, in_array('io.test io-6.2.2 one-table atomic-device commit keeps warmed cache', $plan['upstream'], true));
    };
}

$guardCases = [
    'rejects empty transaction name' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerCacheAtomicCommitOutcome('', [['table' => 't1']]),
    'rejects empty write list' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerCacheAtomicCommitOutcome('io-6.2 guard', []),
    'rejects empty write table' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerCacheAtomicCommitOutcome('io-6.2 guard', [['table' => '']]),
    'rejects zero pages touched' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerCacheAtomicCommitOutcome('io-6.2 guard', [['table' => 't1', 'pages_touched' => 0]]),
    'rejects zero row count' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerCacheAtomicCommitOutcome('io-6.2 guard', [['table' => 't1', 'row_count' => 0]]),
    'rejects zero cache size' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerCacheAtomicCommitOutcome('io-6.2 guard', [['table' => 't1']], ['atomic'], 0),
    'rejects zero database pages' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerCacheAtomicCommitOutcome('io-6.2 guard', [['table' => 't1']], ['atomic'], 1, 0),
    'rejects negative mmap size' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerCacheAtomicCommitOutcome('io-6.2 guard', [['table' => 't1']], ['atomic'], 1, 1, -1),
    'rejects unknown device flag' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerCacheAtomicCommitOutcome('io-6.2 guard', [['table' => 't1']], ['remote']),
];

foreach ($guardCases as $name => $callable) {
    $tests['real upstream corpus vfs io pager cache dynamic ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $callable);
}

return $tests;
