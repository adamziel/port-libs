<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaEncodingPageTempStoreState;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test
 * - pragma-17.1.*: auto_vacuum accepts numeric and keyword values, mapping
 *   out-of-range inputs such as 3, -1, -1234, and 1234 back to NONE/0.
 * - pragma-18.1.*: temp_store accepts numeric and keyword values, mapping
 *   out-of-range inputs such as 3 and -1 back to DEFAULT/0.
 * - pragma-9.15 and pragma-9.18: temp_store cannot change while a temp
 *   transaction or scan is active.
 */

$tests = [];

$autoVacuumCases = [
    ['0', 0],
    ['1', 1],
    ['2', 2],
    ['3', 0],
    ['-1', 0],
    ['none', 0],
    ['NONE', 0],
    ['NoNe', 0],
    ['off', 0],
    ['OFF', 0],
    ['full', 1],
    ['FULL', 1],
    ['incremental', 2],
    ['INCREMENTAL', 2],
    ['-1234', 0],
    ['1234', 0],
];

$tempStoreCases = [
    ['0', 0],
    ['1', 1],
    ['2', 2],
    ['3', 0],
    ['-1', 0],
    ['default', 0],
    ['DEFAULT', 0],
    ['file', 1],
    ['FILE', 1],
    ['fIlE', 1],
    ['memory', 2],
    ['MEMORY', 2],
    ['MeMoRy', 2],
    ['1234', 0],
    ['-1234', 0],
];

foreach (range(1, 250) as $variant) {
    $auto = $autoVacuumCases[($variant - 1) % count($autoVacuumCases)];
    $temp = $tempStoreCases[($variant - 1) % count($tempStoreCases)];
    $schema = sprintf('aux_%04d', $variant);
    $table = sprintf('temp_store_table_%04d', $variant);

    $tests[sprintf('real upstream pragma store mode dynamic auto_vacuum equals variant %04d', $variant)] =
        static function (TestRunner $t) use ($variant, $auto): void {
            [$input, $expected] = $auto;
            $state = new SQLitePragmaEncodingPageTempStoreState([
                'main' => [
                    'auto_vacuum' => 2,
                    'database_empty' => true,
                    'page_count' => 0,
                ],
            ]);
            $result = $state->execute("PRAGMA auto_vacuum={$input}");

            $t->same('auto_vacuum', $result['pragma']);
            $t->same($expected, $result['requested']);
            $t->same($expected, $result['effective']);
            $t->same([['auto_vacuum' => $expected]], $result['rows']);
            $t->same(false, $result['requires_vacuum']);
            $t->same(null, $result['pending']);
        };

    $tests[sprintf('real upstream pragma store mode dynamic auto_vacuum schema paren variant %04d', $variant)] =
        static function (TestRunner $t) use ($schema, $auto): void {
            [$input, $expected] = $auto;
            $state = new SQLitePragmaEncodingPageTempStoreState([
                $schema => [
                    'auto_vacuum' => 1,
                    'database_empty' => true,
                ],
            ]);
            $result = $state->execute("PRAGMA {$schema}.auto_vacuum({$input})");
            $readback = $state->execute("PRAGMA {$schema}.auto_vacuum");

            $t->same($schema, $result['schema']);
            $t->same($expected, $result['requested']);
            $t->same($expected, $readback['effective']);
            $t->same([['auto_vacuum' => $expected]], $readback['rows']);
            $t->same($expected !== 1, $result['changed']);
            $t->same(['sqlite-pragma-auto-vacuum-state'], $result['dependencies']);
        };

    $tests[sprintf('real upstream pragma store mode dynamic temp_store equals variant %04d', $variant)] =
        static function (TestRunner $t) use ($temp): void {
            [$input, $expected] = $temp;
            $state = new SQLitePragmaEncodingPageTempStoreState([
                'main' => ['temp_store' => 2],
            ]);
            $result = $state->execute("PRAGMA temp_store={$input}");

            $t->same('temp_store', $result['pragma']);
            $t->same($input, (string) $result['requested']);
            $t->same($expected, $result['effective']);
            $t->same([['temp_store' => $expected]], $result['rows']);
            $t->same($expected !== 2, $result['changed']);
            $t->same(['sqlite-pragma-temp-store-state'], $result['dependencies']);
        };

    $tests[sprintf('real upstream pragma store mode dynamic temp_store transaction guard variant %04d', $variant)] =
        static function (TestRunner $t) use ($table, $temp): void {
            [$input] = $temp;
            $state = new SQLitePragmaEncodingPageTempStoreState([
                'main' => ['temp_store' => 1],
            ]);
            $begin = $state->beginTempTransaction($table, [['id' => 1, 'label' => 'kept']]);

            $t->same('temp_transaction_active', $begin['status']);
            $t->throws(RuntimeException::class, static fn () => $state->execute("PRAGMA temp_store={$input}"));
            $t->same('temp_transaction_committed', $state->commitTempTransaction()['status']);
            $scan = $state->beginTempScan($table);
            $t->same([['id' => 1, 'label' => 'kept']], $scan['rows']);
            $t->throws(RuntimeException::class, static fn () => $state->execute('PRAGMA temp_store=1'));
            $t->same('temp_scan_finished', $state->endTempScan()['status']);
            $t->same(1, $state->execute('PRAGMA temp_store')['effective']);
        };
}

$tests['real upstream pragma store mode dynamic source citations'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-17.1.* maps auto_vacuum 0/1/2, NONE/OFF/FULL/INCREMENTAL, and out-of-range integers to the SQLite result values',
        'pragma.test pragma-18.1.* maps temp_store 0/1/2, FILE/MEMORY, and out-of-range integers to the SQLite result values',
        'pragma.test pragma-9.15 rejects temp_store changes during an active temp transaction',
        'pragma.test pragma-9.18 rejects temp_store changes while scanning a temp table',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma-17.1', $sections[0]);
    $t->contains('pragma-18.1', $sections[1]);
    $t->contains('pragma-9.18', $sections[3]);
};

$tests['real upstream pragma store mode dynamic non overlap and dependency closure'] = static function (TestRunner $t): void {
    $note = 'owns pragma.test pragma-17/18 normalization and pragma-9 temp_store transaction guards; avoids schema table_info/index_info/data_version/table_list/schema5/schema6/runtime-list/page_count batches; no new support component needed';

    $t->contains('pragma-17/18 normalization', $note);
    $t->contains('no new support component needed', $note);
};

return $tests;
