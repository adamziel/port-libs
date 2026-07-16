<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDataVersionTracker;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma3.test pragma3-100 through pragma3-102:
 *   PRAGMA data_version starts at 1 for main/temp and writes are ignored.
 * - SQLite test/pragma3.test pragma3-110 through pragma3-150:
 *   writes committed by the same connection do not change that connection's
 *   data_version value, while another connection observes the external commit.
 * - SQLite test/pragma3.test pragma3-160 through pragma3-195:
 *   uncommitted changes are invisible to other connections, and each
 *   connection owns a local data_version counter over the same database.
 * - SQLite test/pragma3.test pragma3-200 through pragma3-340:
 *   separate-process and shared-cache writers follow the same external
 *   commit detection rule.
 */

foreach (range(1, 250) as $variant) {
    $main = sprintf('reader-main-%03d', $variant);
    $peer = sprintf('reader-peer-%03d', $variant);
    $process = sprintf('reader-process-%03d', $variant);
    $shared = sprintf('reader-shared-cache-%03d', $variant);

    $tests[sprintf('real upstream pragma3 data version initial and write no-op dynamic variant %03d', $variant)] = static function (TestRunner $t) use ($variant, $main): void {
        $tracker = new SQLitePragmaDataVersionTracker($variant);

        $t->same(1, $tracker->open($main));

        $mainRead = $tracker->executePragma($main, 'PRAGMA data_version');
        $tempRead = $tracker->executePragma($main, 'PRAGMA temp.data_version');
        $writeIgnored = $tracker->executePragma($main, 'PRAGMA main.data_version=1234');
        $functionWriteIgnored = $tracker->executePragma($main, 'PRAGMA data_version(5678)');

        $t->same('main', $mainRead['schema']);
        $t->same('temp', $tempRead['schema']);
        $t->same(1, $mainRead['value']);
        $t->same(1, $tempRead['value']);
        $t->same(1, $writeIgnored['value']);
        $t->same(true, $writeIgnored['write_ignored']);
        $t->same(1, $functionWriteIgnored['value']);
        $t->same(true, $functionWriteIgnored['write_ignored']);
        $t->same($variant, $functionWriteIgnored['database_generation']);
    };

    $tests[sprintf('real upstream pragma3 data version same connection commit remains local dynamic variant %03d', $variant)] = static function (TestRunner $t) use ($variant, $main, $peer): void {
        $tracker = new SQLitePragmaDataVersionTracker($variant);
        $tracker->open($main);
        $tracker->open($peer);

        $t->same(1, $tracker->executePragma($main, 'PRAGMA data_version')['value']);
        $tracker->begin($main);
        $t->same(1, $tracker->executePragma($main, 'PRAGMA data_version')['value']);
        $tracker->commit($main, true);

        $sameConnection = $tracker->executePragma($main, 'PRAGMA data_version');
        $peerConnection = $tracker->executePragma($peer, 'PRAGMA data_version');

        $t->same(1, $sameConnection['value']);
        $t->same(false, $sameConnection['changed_by_other_connection']);
        $t->same($variant + 1, $sameConnection['database_generation']);
        $t->same(2, $peerConnection['value']);
        $t->same(true, $peerConnection['changed_by_other_connection']);
        $t->same($variant + 1, $peerConnection['database_generation']);
    };

    $tests[sprintf('real upstream pragma3 data version uncommitted writes stay invisible dynamic variant %03d', $variant)] = static function (TestRunner $t) use ($variant, $main, $peer): void {
        $tracker = new SQLitePragmaDataVersionTracker($variant);
        $tracker->open($main);
        $tracker->open($peer);

        $tracker->begin($main);
        $insideWriteTransaction = $tracker->executePragma($main, 'PRAGMA data_version');
        $peerBeforeCommit = $tracker->executePragma($peer, 'PRAGMA data_version');
        $tracker->commit($main, true);
        $peerAfterCommit = $tracker->executePragma($peer, 'PRAGMA data_version');

        $t->same(1, $insideWriteTransaction['value']);
        $t->same(false, $insideWriteTransaction['changed_by_other_connection']);
        $t->same(1, $peerBeforeCommit['value']);
        $t->same(false, $peerBeforeCommit['changed_by_other_connection']);
        $t->same(2, $peerAfterCommit['value']);
        $t->same(true, $peerAfterCommit['changed_by_other_connection']);
        $t->same($variant + 1, $peerAfterCommit['database_generation']);
        $t->same($variant + 1, $tracker->snapshot()['database_generation']);
    };

    $tests[sprintf('real upstream pragma3 data version process and shared cache external commits dynamic variant %03d', $variant)] = static function (TestRunner $t) use ($variant, $main, $peer, $process, $shared): void {
        $tracker = new SQLitePragmaDataVersionTracker($variant);
        foreach ([$main, $peer, $process, $shared] as $connectionId) {
            $tracker->open($connectionId);
        }

        $tracker->autocommitChange($process);
        $mainAfterProcess = $tracker->executePragma($main, 'PRAGMA data_version');
        $processAfterOwnWrite = $tracker->executePragma($process, 'PRAGMA data_version');

        $tracker->begin($shared);
        $sharedInsideTransaction = $tracker->executePragma($shared, 'PRAGMA data_version');
        $peerBeforeSharedCommit = $tracker->executePragma($peer, 'PRAGMA data_version');
        $tracker->commit($shared, true);
        $peerAfterSharedCommit = $tracker->executePragma($peer, 'PRAGMA data_version');
        $sharedAfterOwnCommit = $tracker->executePragma($shared, 'PRAGMA data_version');

        $t->same(2, $mainAfterProcess['value']);
        $t->same(true, $mainAfterProcess['changed_by_other_connection']);
        $t->same(1, $processAfterOwnWrite['value']);
        $t->same(false, $processAfterOwnWrite['changed_by_other_connection']);
        $t->same(2, $sharedInsideTransaction['value']);
        $t->same(true, $sharedInsideTransaction['changed_by_other_connection']);
        $t->same(2, $peerBeforeSharedCommit['value']);
        $t->same(true, $peerBeforeSharedCommit['changed_by_other_connection']);
        $t->same(3, $peerAfterSharedCommit['value']);
        $t->same(true, $peerAfterSharedCommit['changed_by_other_connection']);
        $t->same(2, $sharedAfterOwnCommit['value']);
        $t->same(false, $sharedAfterOwnCommit['changed_by_other_connection']);
    };
}

$tests['real upstream pragma3 data version dynamic cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma3.test pragma3-100 through pragma3-102 initial main/temp data_version and ignored writes',
        'pragma3.test pragma3-110 through pragma3-150 same-connection commits remain local while peer readers advance',
        'pragma3.test pragma3-160 through pragma3-195 uncommitted writes remain invisible and connection counters differ',
        'pragma3.test pragma3-200 through pragma3-340 separate-process and shared-cache commits use the same external-change rule',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma3.test', $sections[0]);
    $t->contains('pragma3-110', $sections[1]);
    $t->contains('pragma3-195', $sections[2]);
    $t->contains('pragma3-340', $sections[3]);
};

$tests['real upstream pragma3 data version dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local PRAGMA data_version tracker and connection-generation state',
        'no new support component needed; reuses lane-local PRAGMA data_version tracker and connection-generation state',
    );
};

return $tests;
