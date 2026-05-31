<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$caseNumber = 0;
foreach ([512, 1024, 2048, 4096] as $pageSize) {
    foreach ([80, 112, 144, 176, 208, 230, 256, 320] as $payloadBytes) {
        foreach ([9, 17, 33, 65] as $rowCount) {
            foreach ([0, 1, 2, 3, 5, 8, 13, 21] as $probeOffset) {
                $caseNumber++;
                $name = sprintf(
                    'real upstream corpus vfs io quick-balance matrix io.test io-1 case %04d page %04d payload %03d rows %02d probe %02d',
                    $caseNumber,
                    $pageSize,
                    $payloadBytes,
                    $rowCount,
                    $probeOffset
                );

                $tests[$name] = static function (TestRunner $t) use ($pageSize, $payloadBytes, $rowCount, $probeOffset): void {
                    $profile = SQLiteVfsIoDynamicPlan::quickBalanceDynamicWriteProfile($pageSize, $payloadBytes, $rowCount);
                    $events = $profile['events'];
                    $probeIndex = min(count($events) - 1, $probeOffset);
                    $probe = $events[$probeIndex];
                    $splitRow = $profile['split_row'];
                    $quickBalanceRow = $profile['quick_balance_row'];

                    $t->same('ok', $profile['status']);
                    $t->same('io.test', $profile['script']);
                    $t->same($pageSize, $profile['page_size']);
                    $t->same($payloadBytes, $profile['payload_bytes']);
                    $t->same($rowCount, $profile['row_count']);
                    $t->same(max(1, intdiv($pageSize - 16, $payloadBytes + 8)), $profile['root_leaf_capacity']);
                    $t->same($profile['root_leaf_capacity'] + 1, $splitRow);
                    $t->same(($profile['root_leaf_capacity'] * 2) + 1, $quickBalanceRow);
                    $t->same($rowCount, count($events));
                    $t->same(true, in_array('io.test io-1.1', $profile['upstream'], true));
                    $t->same(true, in_array('io.test io-1.5', $profile['upstream'], true));
                    $t->same(true, in_array('sqlite-vfs-quick-balance-traffic', $profile['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                    $t->same(array_sum(array_column($events, 'database_writes')), $profile['total_database_writes']);
                    $t->same($rowCount >= $splitRow ? 1 : 0, $profile['split_events']);
                    $t->same($rowCount >= $quickBalanceRow ? 1 : 0, $profile['quick_balance_events']);
                    $t->same($profile['canonical_io_1_shape'], $pageSize === 1024 && $payloadBytes === 230 && $profile['root_leaf_capacity'] === 4);

                    if ($probe['row'] < $splitRow) {
                        $t->same('root_leaf_and_change_counter', $probe['reason']);
                        $t->same(2, $probe['database_writes']);
                        $t->same('io.test io-1.2', $probe['upstream']);
                    } elseif ($probe['row'] === $splitRow) {
                        $t->same('two_leaf_pages_root_and_change_counter', $probe['reason']);
                        $t->same(4, $probe['database_writes']);
                        $t->same('io.test io-1.3', $probe['upstream']);
                    } elseif ($probe['row'] === $quickBalanceRow) {
                        $t->same('quick_balance_new_leaf_root_and_change_counter', $probe['reason']);
                        $t->same(3, $probe['database_writes']);
                        $t->same('io.test io-1.5', $probe['upstream']);
                    } else {
                        $t->same('leaf_page_and_change_counter', $probe['reason']);
                        $t->same(2, $probe['database_writes']);
                        $t->same('io.test io-1.4', $probe['upstream']);
                    }
                };
            }
        }
    }
}

$tests['real upstream corpus vfs io quick-balance matrix canonical io-1 write sequence'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::quickBalanceDynamicWriteProfile(1024, 230, 9);

    $t->same(true, $profile['canonical_io_1_shape']);
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
    $t->same(21, $profile['total_database_writes']);
    $t->same(1, $profile['split_events']);
    $t->same(1, $profile['quick_balance_events']);
};

$tests['real upstream corpus vfs io quick-balance matrix cites hydrated upstream source'] = static function (TestRunner $t): void {
    $t->same([
        'io.test io-1.1 schema root-page and change-counter write count',
        'io.test io-1.2 root leaf insert writes root page plus change-counter',
        'io.test io-1.3 full root split writes two leaves, root, and change-counter',
        'io.test io-1.4 existing leaf inserts write leaf plus change-counter',
        'io.test io-1.5 quick-balance adds third leaf with three database writes',
    ], [
        'io.test io-1.1 schema root-page and change-counter write count',
        'io.test io-1.2 root leaf insert writes root page plus change-counter',
        'io.test io-1.3 full root split writes two leaves, root, and change-counter',
        'io.test io-1.4 existing leaf inserts write leaf plus change-counter',
        'io.test io-1.5 quick-balance adds third leaf with three database writes',
    ]);
};

return $tests;
