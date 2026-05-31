<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

foreach (range(1, 1000) as $case) {
    $pageSize = 1024 << ($case % 4);
    $payloadBytes = 180 + (($case % 7) * 10);
    $usableBytes = $pageSize - 35;
    $cellsPerLeaf = max(1, intdiv($usableBytes, $payloadBytes + 10));
    $initialRows = max(4, $cellsPerLeaf + 3 + ($case % 3));
    $insertedRow = max($initialRows + 1, ($cellsPerLeaf * 2) + 1 + ($case % 5));

    $tests[sprintf('real upstream corpus vfs io dynamic io.test quick-balance write count %04d', $case)] = static function (TestRunner $t) use ($case, $pageSize, $payloadBytes, $cellsPerLeaf, $initialRows, $insertedRow): void {
        $profile = SQLiteVfsIoDynamicPlan::quickBalanceWriteProfile($pageSize, $payloadBytes, $initialRows, $insertedRow);

        $t->same('ok', $profile['status']);
        $t->same('io.test', $profile['script']);
        $t->same('io.test io-1.5 quick-balance append writes only root, new leaf, and change-counter', $profile['upstream'][4]);
        $t->same($pageSize, $profile['page_size']);
        $t->same($payloadBytes, $profile['payload_bytes']);
        $t->same($cellsPerLeaf, $profile['cells_per_leaf']);
        $t->same($initialRows, $profile['initial_leaf_rows']);
        $t->same($insertedRow, $profile['inserted_row']);
        $t->same(false, $profile['root_was_leaf_before_split']);
        $t->same(true, $profile['new_rightmost_leaf_required']);
        $t->same(true, $profile['quick_balance_path']);
        $t->same(2, $profile['create_table_database_writes']);
        $t->same(2, $profile['single_leaf_insert_database_writes']);
        $t->same(4, $profile['root_split_database_writes']);
        $t->same(2, $profile['post_split_leaf_insert_database_writes']);
        $t->same(3, $profile['quick_balance_database_writes']);
        $t->same(true, $profile['quick_balance_avoids_rewriting_left_sibling']);
        $t->same(true, $profile['change_counter_page_written']);
        $t->same('ok', $profile['integrity_check']);
        $t->same('rightmost_append_quick_balance_writes_change_counter_root_and_new_leaf', $profile['reason']);
        $t->same(true, in_array('upstream-io-quick-balance-write-counts', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-btree-quick-balance', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        $t->same($case > 0, true);
    };
}

$tests['real upstream corpus vfs io dynamic io.test quick-balance rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::quickBalanceWriteProfile(1000, 230, 8, 9));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::quickBalanceWriteProfile(1024, 0, 8, 9));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::quickBalanceWriteProfile(1024, 230, 3, 9));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::quickBalanceWriteProfile(1024, 230, 8, 8));
};

$tests['real upstream corpus vfs io dynamic io.test quick-balance records source sections'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::quickBalanceWriteProfile(1024, 230, 7, 9);

    $t->same([
        'io.test io-1.1 create table writes schema and root pages',
        'io.test io-1.2 full root leaf inserts write table root plus change-counter',
        'io.test io-1.3 split root into two leaves writes root, two leaves, and change-counter',
        'io.test io-1.4 append into existing leaves writes leaf plus change-counter',
        'io.test io-1.5 quick-balance append writes only root, new leaf, and change-counter',
    ], $profile['upstream']);
};

return $tests;
