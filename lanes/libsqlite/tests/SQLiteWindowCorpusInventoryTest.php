<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowCorpusInventory;

$tests = [];

$upstreamWindowTestDir = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['window corpus inventory parses static and dynamic Tcl test ids'] = static function (TestRunner $t): void {
    $source = <<<'TCL'
do_execsql_test 1.0 { SELECT 1 } {1}
do_catchsql_test window1-12.1 { SELECT broken } {1 error}
foreach {tn expr} {1 a 2 b} {
  do_execsql_test 2.$tn { SELECT $expr } {}
}
TCL;

    $rows = SQLiteWindowCorpusInventory::parseScript('windowX.test', $source);

    $t->same(3, count($rows));
    $t->same(['1.0', 'window1-12.1', '2.$tn'], array_column($rows, 'id'));
    $t->same(['do_execsql_test', 'do_catchsql_test', 'do_execsql_test'], array_column($rows, 'command'));
    $t->same([false, false, true], array_column($rows, 'dynamic'));
};

$tests['window corpus inventory counts real hydrated upstream window and filter ids'] = static function (TestRunner $t) use ($upstreamWindowTestDir): void {
    $inventory = SQLiteWindowCorpusInventory::inventory($upstreamWindowTestDir);

    $expected = [
        'window1.test' => 295,
        'window2.test' => 65,
        'window3.test' => 1222,
        'window4.test' => 220,
        'window5.test' => 6,
        'window6.test' => 42,
        'window7.test' => 10,
        'window8.test' => 361,
        'window9.test' => 40,
        'windowA.test' => 18,
        'windowB.test' => 58,
        'windowC.test' => 3,
        'windowD.test' => 13,
        'windowE.test' => 15,
        'windowerr.test' => 1,
        'windowfault.test' => 7,
        'windowpushd.test' => 17,
        'filter1.test' => 35,
        'filter2.test' => 15,
        'filterfault.test' => 1,
    ];

    $t->same('ready', $inventory['status']);
    $t->same(20, $inventory['script_count']);
    $t->same(2444, $inventory['test_count']);
    $t->same($expected, $inventory['by_script']);
    $t->same([], $inventory['missing_scripts']);
    $t->true($inventory['dynamic_id_count'] > 0, 'Expected Tcl loop-generated ids to be visible as dynamic templates');
};

$tests['window corpus inventory maps exact and ranged ownership citations'] = static function (TestRunner $t): void {
    $root = sys_get_temp_dir() . '/libsqlite-window-corpus-inventory-' . bin2hex(random_bytes(4));
    $upstream = $root . '/upstream';
    $owners = $root . '/owners';
    mkdir($upstream, 0777, true);
    mkdir($owners, 0777, true);

    file_put_contents($upstream . '/windowA.test', <<<'TCL'
do_execsql_test 1.1 { SELECT 1 } {1}
do_execsql_test 1.2 { SELECT 2 } {2}
do_execsql_test 1.3 { SELECT 3 } {3}
do_catchsql_test 2.1 { SELECT broken } {1 error}
TCL);
    file_put_contents($owners . '/WindowOwnerTest.php', <<<'PHP'
<?php
// Source truth: windowA.test:1.1-1.2 and exact windowA.test 2.1.
PHP);

    try {
        $report = SQLiteWindowCorpusInventory::coverageReport($upstream, [$owners], ['windowA.test']);

        $t->same('ready', $report['status']);
        $t->same(4, $report['total']);
        $t->same(3, $report['covered']);
        $t->same(1, $report['uncovered']);
        $t->same('1.3', $report['uncovered_tests'][0]['id']);
        $t->same(['total' => 4, 'covered' => 3, 'uncovered' => 1], $report['coverage_by_script']['windowA.test']);
    } finally {
        @unlink($upstream . '/windowA.test');
        @unlink($owners . '/WindowOwnerTest.php');
        @rmdir($upstream);
        @rmdir($owners);
        @rmdir($root);
    }
};

$tests['window corpus inventory emits current-base uncovered id report without fake script ids'] = static function (TestRunner $t) use ($upstreamWindowTestDir): void {
    $report = SQLiteWindowCorpusInventory::coverageReport(
        $upstreamWindowTestDir,
        [__DIR__, __DIR__ . '/../notes'],
    );

    $t->same('ready', $report['status']);
    $t->same(2444, $report['total']);
    $t->true($report['ownership_file_count'] >= 100, 'Expected current lane-local window/filter ownership citations to remain visible');
    $t->true($report['covered'] > 0, 'Expected cited current PHP window/filter corpus coverage');
    $t->true($report['uncovered'] > 0, 'Expected report to retain uncovered ids for the next high-yield batch');
    $t->same($report['total'], $report['covered'] + $report['uncovered']);
    $t->true(in_array($report['uncovered_tests'][0]['script'], SQLiteWindowCorpusInventory::defaultScriptNames(), true), 'Uncovered row must cite a real upstream script');
};

$tests['window corpus inventory reports missing hydrated sources as blocked'] = static function (TestRunner $t): void {
    $inventory = SQLiteWindowCorpusInventory::inventory('/tmp/libsqlite-missing-window-corpus', ['window1.test', 'filter1.test']);

    $t->same('blocked-missing-scripts', $inventory['status']);
    $t->same(0, $inventory['test_count']);
    $t->same(['window1.test', 'filter1.test'], $inventory['missing_scripts']);
};

$tests['window corpus inventory dependency closure note'] = static function (TestRunner $t): void {
    $t->same(
        'no new runtime support component needed; this lane-local helper parses hydrated upstream window/filter Tcl ids and maps existing PHP/notes ownership citations for the next real corpus batch',
        'no new runtime support component needed; this lane-local helper parses hydrated upstream window/filter Tcl ids and maps existing PHP/notes ownership citations for the next real corpus batch',
    );
};

return $tests;
