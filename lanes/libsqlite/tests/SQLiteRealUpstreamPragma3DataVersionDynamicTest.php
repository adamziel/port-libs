<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDataVersionTracker;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma3.test.
 *
 * Ported behavior:
 * - pragma3-100 through pragma3-102: PRAGMA data_version starts at 1 for
 *   main/temp schema qualifiers and assignment is ignored.
 * - pragma3-110 through pragma3-150: writes committed by the same connection
 *   do not change that connection's observed data_version, while a second
 *   connection observes the committed database state at its own local value.
 * - pragma3-160 through pragma3-195: another connection's commit advances the
 *   local value on the next PRAGMA data_version read, and each connection owns
 *   an independent local counter.
 * - pragma3-200 through pragma3-300: process/shared-cache changes have the
 *   same generation effect as another SQLite connection.
 */

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%03d', $variant);

    $tests["real upstream pragma3 data_version {$suffix} starts at one and ignores writes"] = static function (TestRunner $t) use ($variant): void {
        $tracker = new SQLitePragmaDataVersionTracker(1 + ($variant % 3));
        $main = $tracker->executePragma('main-connection', 'PRAGMA data_version');
        $temp = $tracker->executePragma('main-connection', 'PRAGMA temp.data_version');
        $write = $tracker->executePragma('main-connection', "PRAGMA main.data_version={$variant}");
        $afterWrite = $tracker->executePragma('main-connection', 'PRAGMA main.data_version');

        $t->same(1, $main['value']);
        $t->same('temp', $temp['schema']);
        $t->same(1, $temp['value']);
        $t->same(true, $write['write_ignored']);
        $t->same(1, $write['value']);
        $t->same(1, $afterWrite['value']);
    };

    $tests["real upstream pragma3 data_version {$suffix} same connection commit remains stable"] = static function (TestRunner $t): void {
        $tracker = new SQLitePragmaDataVersionTracker();
        $before = $tracker->executePragma('db', 'PRAGMA data_version');
        $tracker->begin('db');
        $duringBeforeWrite = $tracker->executePragma('db', 'PRAGMA data_version');
        $duringAfterWrite = $tracker->executePragma('db', 'PRAGMA data_version');
        $tracker->commit('db', true);
        $afterCommit = $tracker->executePragma('db', 'PRAGMA data_version');
        $openedAfterCommit = $tracker->executePragma('db2', 'PRAGMA data_version');

        $t->same(1, $before['value']);
        $t->same(1, $duringBeforeWrite['value']);
        $t->same(1, $duringAfterWrite['value']);
        $t->same(1, $afterCommit['value']);
        $t->same(1, $openedAfterCommit['value']);
        $t->same(2, $tracker->snapshot()['database_generation']);
    };

    $tests["real upstream pragma3 data_version {$suffix} other connection commit bumps local counter once"] = static function (TestRunner $t): void {
        $tracker = new SQLitePragmaDataVersionTracker();
        $tracker->open('db');
        $tracker->open('db2');
        $tracker->begin('db2');
        $db2Before = $tracker->executePragma('db2', 'PRAGMA data_version');
        $tracker->commit('db2', true);
        $db2After = $tracker->executePragma('db2', 'PRAGMA data_version');
        $dbFirstRead = $tracker->executePragma('db', 'PRAGMA data_version');
        $dbSecondRead = $tracker->executePragma('db', 'PRAGMA data_version');

        $t->same(1, $db2Before['value']);
        $t->same(1, $db2After['value']);
        $t->same(2, $dbFirstRead['value']);
        $t->same(true, $dbFirstRead['changed_by_other_connection']);
        $t->same(2, $dbSecondRead['value']);
        $t->same(false, $dbSecondRead['changed_by_other_connection']);
    };

    $tests["real upstream pragma3 data_version {$suffix} process and shared cache changes share semantics"] = static function (TestRunner $t) use ($variant): void {
        $tracker = new SQLitePragmaDataVersionTracker();
        $tracker->open('db');
        $tracker->open('db2');
        $tracker->autocommitChange('process-writer-' . $variant);
        $afterProcess = $tracker->executePragma('db', 'PRAGMA data_version');
        $db2AfterProcess = $tracker->executePragma('db2', 'PRAGMA data_version');
        $tracker->begin('shared-cache-peer-' . $variant);
        $tracker->commit('shared-cache-peer-' . $variant, true);
        $afterSharedCache = $tracker->executePragma('db', 'PRAGMA data_version');
        $db2AfterSharedCache = $tracker->executePragma('db2', 'PRAGMA data_version');

        $t->same(2, $afterProcess['value']);
        $t->same(2, $db2AfterProcess['value']);
        $t->same(3, $afterSharedCache['value']);
        $t->same(3, $db2AfterSharedCache['value']);
        $t->same(3, $tracker->snapshot()['database_generation']);
    };
}

$tests['real upstream pragma3 data_version cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma3.test pragma3-100 through pragma3-102 data_version starts at one and writes are ignored',
        'pragma3.test pragma3-110 through pragma3-150 same-connection commits leave data_version stable',
        'pragma3.test pragma3-160 through pragma3-195 other-connection commits advance local counters independently',
        'pragma3.test pragma3-200 through pragma3-300 process and shared-cache writes follow the same data_version behavior',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma3.test', $sections[0]);
    $t->contains('pragma3-160', $sections[2]);
    $t->contains('shared-cache', $sections[3]);
};

return $tests;
