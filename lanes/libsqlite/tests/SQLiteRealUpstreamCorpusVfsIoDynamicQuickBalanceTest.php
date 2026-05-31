<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

foreach ([512, 1024, 2048, 4096, 8192] as $pageSize) {
    foreach ([80, 120, 180, 230, 310, 480, 760, 1024] as $payloadBytes) {
        $cellBytes = $payloadBytes + 8;
        $rootLeafCapacity = max(1, intdiv($pageSize - 16, $cellBytes));
        $splitRow = $rootLeafCapacity + 1;
        $quickBalanceRow = ($rootLeafCapacity * 2) + 1;

        foreach ([$splitRow - 1, $splitRow, $splitRow + 1, $quickBalanceRow, $quickBalanceRow + 2] as $rowCount) {
            $rowCount = max(1, $rowCount);
            $name = sprintf(
                'real upstream corpus vfs io dynamic io-1 quick-balance page %05d payload %04d rows %03d',
                $pageSize,
                $payloadBytes,
                $rowCount
            );

            $tests[$name] = static function (TestRunner $t) use ($pageSize, $payloadBytes, $rowCount, $rootLeafCapacity, $splitRow, $quickBalanceRow): void {
                $profile = SQLiteVfsIoDynamicPlan::quickBalanceDynamicWriteProfile($pageSize, $payloadBytes, $rowCount);

                $t->same('ok', $profile['status']);
                $t->same('io.test', $profile['script']);
                $t->same(true, in_array('io.test io-1.1', $profile['upstream'], true));
                $t->same(true, in_array('io.test io-1.2', $profile['upstream'], true));
                $t->same(true, in_array('io.test io-1.3', $profile['upstream'], true));
                $t->same(true, in_array('io.test io-1.5', $profile['upstream'], true));
                $t->same($pageSize, $profile['page_size']);
                $t->same($payloadBytes, $profile['payload_bytes']);
                $t->same($rowCount, $profile['row_count']);
                $t->same($rootLeafCapacity, $profile['root_leaf_capacity']);
                $t->same($splitRow, $profile['split_row']);
                $t->same($quickBalanceRow, $profile['quick_balance_row']);
                $t->same($rowCount, count($profile['events']));
                $t->same($rowCount >= $splitRow ? 1 : 0, $profile['split_events']);
                $t->same($rowCount >= $quickBalanceRow ? 1 : 0, $profile['quick_balance_events']);
                $t->same(array_sum(array_column($profile['events'], 'database_writes')), $profile['total_database_writes']);
                $t->same(true, in_array('sqlite-vfs-quick-balance-traffic', $profile['dependencies'], true));
                $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));

                $lastEvent = $profile['events'][$rowCount - 1];
                if ($rowCount < $splitRow) {
                    $t->same('io.test io-1.2', $lastEvent['upstream']);
                    $t->same(2, $lastEvent['database_writes']);
                    $t->same('root_leaf_and_change_counter', $lastEvent['reason']);
                } elseif ($rowCount === $splitRow) {
                    $t->same('io.test io-1.3', $lastEvent['upstream']);
                    $t->same(4, $lastEvent['database_writes']);
                    $t->same('two_leaf_pages_root_and_change_counter', $lastEvent['reason']);
                } elseif ($rowCount === $quickBalanceRow) {
                    $t->same('io.test io-1.5', $lastEvent['upstream']);
                    $t->same(3, $lastEvent['database_writes']);
                    $t->same('quick_balance_new_leaf_root_and_change_counter', $lastEvent['reason']);
                } else {
                    $t->same('io.test io-1.4', $lastEvent['upstream']);
                    $t->same(2, $lastEvent['database_writes']);
                    $t->same('leaf_page_and_change_counter', $lastEvent['reason']);
                }
            };
        }
    }
}

$tests['real upstream corpus vfs io dynamic io-1 canonical upstream citation'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::quickBalanceDynamicWriteProfile(1024, 230, 9);

    $t->same(true, $profile['canonical_io_1_shape']);
    $t->same(4, $profile['root_leaf_capacity']);
    $t->same(5, $profile['split_row']);
    $t->same(9, $profile['quick_balance_row']);
    $t->same([2, 2, 2, 2, 4, 2, 2, 2, 3], array_column($profile['events'], 'database_writes'));
    $t->same([
        'io.test io-1.2',
        'io.test io-1.2',
        'io.test io-1.2',
        'io.test io-1.2',
        'io.test io-1.3',
        'io.test io-1.4',
        'io.test io-1.4',
        'io.test io-1.4',
        'io.test io-1.5',
    ], array_column($profile['events'], 'upstream'));
};

$tests['real upstream corpus vfs io dynamic io-1 rejects invalid quick-balance inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::quickBalanceDynamicWriteProfile(1000, 230, 9));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::quickBalanceDynamicWriteProfile(1024, 0, 9));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::quickBalanceDynamicWriteProfile(1024, 230, 0));
};

return $tests;
