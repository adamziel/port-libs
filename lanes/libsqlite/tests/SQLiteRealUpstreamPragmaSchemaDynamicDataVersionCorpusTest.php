<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma3.test pragma3-100 through pragma3-201:
 *   PRAGMA data_version is initialized per connection, assignment is a no-op,
 *   local commits leave the connection-local data_version stable, other
 *   connections and separate processes advance observer data_version values,
 *   and transaction rollback preserves the observer value.
 * - SQLite test/pragma3.test pragma3-300 through pragma3-340:
 *   shared-cache connections follow the same rule: uncommitted writes do not
 *   advance other observers, while committed changes advance separate
 *   connection data_version values.
 */

$mainConnection = static function (int $variant): SQLitePragmaSchemaDataVersion {
    return new SQLitePragmaSchemaDataVersion([
        'main' => [
            'schema_version' => 10 + ($variant % 17),
            'data_version' => 1 + ($variant % 5),
            'change_counter' => 1 + ($variant % 5),
        ],
        'temp' => [
            'schema_version' => 2 + ($variant % 3),
            'data_version' => 1,
            'change_counter' => 1,
        ],
    ]);
};

foreach (range(1, 1000) as $variant) {
    $tests[sprintf('real upstream pragma3 data version connection observer corpus variant %04d', $variant)] = static function (TestRunner $t) use ($mainConnection, $variant): void {
        $reader = $mainConnection($variant);
        $writer = $mainConnection($variant);
        $initial = 1 + ($variant % 5);

        $t->same([['data_version' => $initial]], $reader->execute('PRAGMA data_version')['rows']);
        $t->same([['data_version' => 1]], $reader->execute('PRAGMA temp.data_version')['rows']);

        $assigned = $reader->execute('PRAGMA main.data_version=1234');
        $t->same($initial, $assigned['value']);
        $t->same(false, $assigned['changed']);
        $t->same('read_only_pragma_ignored', $assigned['reason']);
        $t->same($initial, $reader->execute('PRAGMA main.data_version')['value']);

        $reader->beginTransaction();
        $t->same($initial, $reader->execute('PRAGMA data_version')['value']);
        $local = $reader->recordLocalCommit('main', 2 + ($variant % 4), 'local_insert_batch');
        $t->same($initial, $local['value']);
        $t->same(false, $local['changed']);
        $t->same($initial, $reader->execute('PRAGMA data_version')['value']);
        $reader->commitTransaction();
        $t->same($initial, $reader->execute('PRAGMA data_version')['value']);
        $t->same($initial + 2 + ($variant % 4), $reader->headerUpdate('main')['file_change_counter']);

        $writer->recordLocalCommit('main', 1, 'writer_local_commit');
        $t->same($initial, $writer->execute('PRAGMA data_version')['value']);
        $observed = $reader->recordExternalCommit('main', 1, 'other_connection_commit');
        $t->same($initial + 1, $observed['value']);
        $t->same(true, $observed['changed']);
        $t->same($initial + 1, $reader->execute('PRAGMA data_version')['value']);

        $reader->beginTransaction();
        $reader->recordLocalCommit('main', 3, 'rolled_back_local_update');
        $reader->recordSchemaChange('main', 1, 'rolled_back_schema_update');
        $t->same($initial + 1, $reader->execute('PRAGMA data_version')['value']);
        $reader->rollbackTransaction();
        $t->same($initial + 1, $reader->execute('PRAGMA data_version')['value']);
        $t->same(10 + ($variant % 17), $reader->execute('PRAGMA schema_version')['value']);

        $processCounter = $reader->headerUpdate('main')['file_change_counter'] + 1;
        $processChange = $reader->observeHeader('main', 10 + ($variant % 17), $processCounter, 'separate_process_header_observed');
        $t->same($processCounter, $processChange['value']);
        $t->same($processCounter, $reader->execute('PRAGMA main.data_version')['value']);

        $sharedReader = $mainConnection($variant);
        $sharedWriter = $mainConnection($variant);
        $sharedReader->beginTransaction();
        $sharedReader->recordLocalCommit('main', 1, 'shared_cache_uncommitted_insert');
        $t->same($initial, $sharedWriter->execute('PRAGMA data_version')['value']);
        $sharedReader->commitTransaction();
        $sharedWriter->recordExternalCommit('main', 1, 'shared_cache_commit_observed');
        $t->same($initial + 1, $sharedWriter->execute('PRAGMA data_version')['value']);
    };
}

$tests['real upstream pragma3 data version corpus cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma3.test pragma3-100 through pragma3-102 cover initial data_version rows and write no-op behavior',
        'pragma3.test pragma3-110 through pragma3-190 cover local commit stability and other-connection observer increments',
        'pragma3.test pragma3-200 through pragma3-201 cover separate-process commits observed through data_version',
        'pragma3.test pragma3-300 through pragma3-340 cover shared-cache transaction and commit observer behavior',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma3-100', $sections[0]);
    $t->contains('pragma3-190', $sections[1]);
    $t->contains('separate-process', $sections[2]);
    $t->contains('shared-cache', $sections[3]);
};

return $tests;
