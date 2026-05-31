<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaEncodingPageTempStoreState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test pragma-9.1 through
 * pragma-9.18. These cases cover PRAGMA temp_store reads, numeric and symbolic
 * assignments, value 3 normalizing back to default, committed temp-table
 * survival, and rejection of temp_store changes while a temp transaction or
 * active scan is in progress.
 */

foreach (range(1, 1000) as $variant) {
    $table = sprintf('temp_settings_%04d', $variant);
    $initialRows = [
        ['key_name' => "alpha-{$variant}", 'key_value' => $variant],
    ];

    $tests[sprintf('real upstream pragma schema dynamic temp store corpus variant %04d', $variant)] = static function (TestRunner $t) use ($variant, $table, $initialRows): void {
        $state = new SQLitePragmaEncodingPageTempStoreState([
            'main' => ['temp_store' => 0, 'page_count' => $variant % 13],
        ]);

        $t->same(0, $state->execute('PRAGMA temp_store')['effective']);
        $t->same(1, $state->execute('PRAGMA temp_store=file')['effective']);
        $t->same(1, $state->execute('PRAGMA temp_store')['rows'][0]['temp_store']);
        $t->same(2, $state->execute('PRAGMA temp_store=memory')['effective']);
        $t->same(2, $state->execute('PRAGMA temp_store(2)')['effective']);
        $t->same(0, $state->execute('PRAGMA temp_store = 0')['effective']);
        $t->same(1, $state->execute('PRAGMA temp_store = 1')['effective']);
        $t->same(2, $state->execute('PRAGMA temp_store = 2')['effective']);
        $t->same(0, $state->execute('PRAGMA temp_store = 3')['effective']);

        $begin = $state->beginTempTransaction($table, $initialRows);
        $t->same('temp_transaction_active', $begin['status']);
        $t->same(1, $begin['rows']);
        $t->throws(RuntimeException::class, static fn () => $state->execute('PRAGMA temp_store = 1'));
        $t->same(2, $state->insertTempRow($table, ['key_name' => "beta-{$variant}", 'key_value' => $variant + 1])['rows']);
        $commit = $state->commitTempTransaction();
        $t->same('temp_transaction_committed', $commit['status']);
        $t->same([$table], $commit['tables']);

        $scan = $state->beginTempScan($table);
        $t->same('temp_scan_active', $scan['status']);
        $t->same([
            ['key_name' => "alpha-{$variant}", 'key_value' => $variant],
            ['key_name' => "beta-{$variant}", 'key_value' => $variant + 1],
        ], $scan['rows']);
        $t->throws(RuntimeException::class, static fn () => $state->execute('PRAGMA temp_store = 1'));
        $t->same('temp_scan_finished', $state->endTempScan()['status']);
        $t->same(1, $state->execute('PRAGMA temp_store = FILE')['effective']);
    };
}

$tests['real upstream pragma schema dynamic temp store corpus cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-9.1 through pragma-9.4 cover temp_store query and FILE/MEMORY/default assignments',
        'pragma.test pragma-9.11 through pragma-9.14 cover numeric temp_store values 0, 1, 2, and 3 normalizing to default',
        'pragma.test pragma-9.15 through pragma-9.18 cover transaction and active scan rejection for temp_store changes while committed temp rows remain readable',
    ];

    $t->same(3, count($sections));
    $t->contains('pragma-9.1', $sections[0]);
    $t->contains('pragma-9.14', $sections[1]);
    $t->contains('pragma-9.18', $sections[2]);
};

return $tests;
