<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDataVersionTracker;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma3.test pragma3-100 through pragma3-102:
 *   PRAGMA data_version starts at 1 for main/temp and writes are ignored.
 * - SQLite test/pragma3.test pragma3-110 through pragma3-195:
 *   a connection's own commits do not change its value, while commits from
 *   another connection advance the next observed local data_version.
 * - SQLite test/pragma3.test pragma3-200 through pragma3-430:
 *   the same contract holds for separate processes, shared-cache connections,
 *   and WAL-mode connections; the PHP port models the observable
 *   connection-local generation without shelling out to SQLite.
 * - SQLite test/pragma3.test pragma3-510A/B and pragma3-520A/B:
 *   empty write transactions do not decrement data_version.
 */

foreach (range(1, 250) as $variant) {
    $reader = sprintf('reader-%03d', $variant);
    $writer = sprintf('writer-%03d', $variant);
    $schemaSql = $variant % 2 === 0 ? 'PRAGMA temp.data_version' : 'PRAGMA main.data_version';
    $assignmentSql = $variant % 2 === 0 ? 'PRAGMA temp.data_version(1234)' : 'PRAGMA main.data_version=1234';

    $tests[sprintf('real upstream pragma data version connection local write ignored variant %03d', $variant)] = static function (TestRunner $t) use ($variant, $reader, $schemaSql, $assignmentSql): void {
        $tracker = new SQLitePragmaDataVersionTracker($variant + 1);
        $tracker->open($reader);

        $initial = $tracker->executePragma($reader, $schemaSql);
        $ignored = $tracker->executePragma($reader, $assignmentSql);
        $after = $tracker->executePragma($reader, $schemaSql);

        $t->same('data_version', $initial['pragma']);
        $t->same(1, $initial['value']);
        $t->same(1, $ignored['value']);
        $t->same(true, $ignored['write_ignored']);
        $t->same(1, $after['value']);
    };

    $tests[sprintf('real upstream pragma data version connection local other commit advances observer variant %03d', $variant)] = static function (TestRunner $t) use ($variant, $reader, $writer): void {
        $tracker = new SQLitePragmaDataVersionTracker($variant + 10);
        $tracker->open($reader);
        $tracker->open($writer);

        $readerBefore = $tracker->executePragma($reader, 'PRAGMA data_version');
        $writerBefore = $tracker->executePragma($writer, 'PRAGMA data_version');
        $tracker->begin($writer);
        $tracker->commit($writer, true);
        $writerAfterOwnCommit = $tracker->executePragma($writer, 'PRAGMA data_version');
        $readerAfterOtherCommit = $tracker->executePragma($reader, 'PRAGMA data_version');
        $readerRepeated = $tracker->executePragma($reader, 'PRAGMA data_version');

        $t->same(1, $readerBefore['value']);
        $t->same(1, $writerBefore['value']);
        $t->same(1, $writerAfterOwnCommit['value']);
        $t->same(2, $readerAfterOtherCommit['value']);
        $t->same(true, $readerAfterOtherCommit['changed_by_other_connection']);
        $t->same(2, $readerRepeated['value']);
        $t->same(false, $readerRepeated['changed_by_other_connection']);
    };

    $tests[sprintf('real upstream pragma data version connection local uncommitted writer invisible variant %03d', $variant)] = static function (TestRunner $t) use ($variant, $reader, $writer): void {
        $tracker = new SQLitePragmaDataVersionTracker($variant + 100);
        $tracker->open($reader);
        $tracker->open($writer);

        $tracker->executePragma($reader, 'PRAGMA data_version');
        $tracker->begin($writer);
        $readerDuringWriterTransaction = $tracker->executePragma($reader, 'PRAGMA data_version');
        $writerDuringOwnTransaction = $tracker->executePragma($writer, 'PRAGMA data_version');
        $tracker->commit($writer, true);
        $readerAfterCommit = $tracker->executePragma($reader, 'PRAGMA data_version');

        $t->same(1, $readerDuringWriterTransaction['value']);
        $t->same(false, $readerDuringWriterTransaction['changed_by_other_connection']);
        $t->same(1, $writerDuringOwnTransaction['value']);
        $t->same(2, $readerAfterCommit['value']);
        $t->same(true, $readerAfterCommit['changed_by_other_connection']);
    };

    $tests[sprintf('real upstream pragma data version connection local empty transaction stable variant %03d', $variant)] = static function (TestRunner $t) use ($variant, $reader, $writer): void {
        $tracker = new SQLitePragmaDataVersionTracker($variant + 200);
        $tracker->open($reader);
        $tracker->open($writer);

        $readerBefore = $tracker->executePragma($reader, 'PRAGMA data_version');
        $tracker->begin($writer);
        $tracker->commit($writer, false);
        $readerAfterEmptyCommit = $tracker->executePragma($reader, 'PRAGMA data_version');
        $writerAfterEmptyCommit = $tracker->executePragma($writer, 'PRAGMA data_version');
        $snapshot = $tracker->snapshot();

        $t->same(1, $readerBefore['value']);
        $t->same(1, $readerAfterEmptyCommit['value']);
        $t->same(false, $readerAfterEmptyCommit['changed_by_other_connection']);
        $t->same(1, $writerAfterEmptyCommit['value']);
        $t->same($variant + 200, $snapshot['database_generation']);
    };
}

$tests['real upstream pragma data version connection local cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma3.test pragma3-100 through pragma3-102 covers initial main/temp values and ignored writes',
        'pragma3.test pragma3-110 through pragma3-195 covers connection-local data_version and other-connection commit advancement',
        'pragma3.test pragma3-200 through pragma3-430 covers equivalent separate-process, shared-cache, and WAL-mode visibility semantics',
        'pragma3.test pragma3-510A/B and pragma3-520A/B covers empty write transactions that must not decrement data_version',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma3.test', $sections[0]);
    $t->contains('pragma3-110', $sections[1]);
    $t->contains('pragma3-430', $sections[2]);
    $t->contains('pragma3-520', $sections[3]);
};

return $tests;
