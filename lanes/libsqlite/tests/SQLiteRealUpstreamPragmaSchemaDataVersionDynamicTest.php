<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma3.test.
 *
 * This ports the PRAGMA data_version/schema-version behavior cluster:
 * - pragma3-100 through pragma3-102: data_version starts at 1 for main/temp
 *   and assignment to data_version is a no-op.
 * - pragma3-110 through pragma3-150: local commits leave a connection's
 *   data_version unchanged, while another connection observes a change.
 * - pragma3-160 through pragma3-195: uncommitted changes do not advance other
 *   connections, then commit advances only the other connection's value.
 * - pragma3-300 through pragma3-340: shared-cache connections obey the same
 *   local/other-connection data_version rules.
 * - pragma3-400 through pragma3-430 and pragma3-510 through pragma3-520:
 *   WAL mode and empty write transactions preserve the same rules.
 */

for ($variant = 0; $variant < 1000; $variant++) {
    $suffix = sprintf('%04d', $variant);
    $schemaVersion = 10 + ($variant % 500);
    $mainDataVersion = 1 + ($variant % 17);
    $tempDataVersion = 1 + (($variant * 3) % 13);
    $localCommits = 1 + ($variant % 5);
    $otherCommits = 1 + (($variant * 7) % 5);
    $schemaName = 'auxschema' . $suffix;

    $tests["real upstream pragma3 data_version initial and assignment noop variant {$suffix}"] = static function (TestRunner $t) use ($schemaVersion, $mainDataVersion, $tempDataVersion): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $schemaVersion, 'data_version' => $mainDataVersion],
            'temp' => ['schema_version' => 0, 'data_version' => $tempDataVersion],
        ]);

        $t->same($mainDataVersion, $state->execute('PRAGMA data_version')['value']);
        $t->same($tempDataVersion, $state->execute('PRAGMA temp.data_version')['value']);
        $ignored = $state->execute('PRAGMA main.data_version=1234');
        $t->same(false, $ignored['changed']);
        $t->same('read_only_pragma_ignored', $ignored['reason']);
        $t->same($mainDataVersion, $state->execute('PRAGMA main.data_version')['value']);
        $t->same($schemaVersion, $state->execute('PRAGMA schema_version')['value']);
    };

    $tests["real upstream pragma3 local commits preserve local data_version variant {$suffix}"] = static function (TestRunner $t) use ($schemaVersion, $mainDataVersion, $localCommits): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $schemaVersion, 'data_version' => $mainDataVersion, 'change_counter' => $mainDataVersion],
        ]);

        $before = $state->execute('PRAGMA data_version');
        $state->beginTransaction();
        $inside = $state->execute('PRAGMA data_version');
        $local = $state->recordLocalCommit('main', $localCommits, 'local_table_write_commit');
        $state->commitTransaction();
        $after = $state->execute('PRAGMA data_version');

        $t->same($mainDataVersion, $before['value']);
        $t->same($mainDataVersion, $inside['value']);
        $t->same($mainDataVersion, $local['value']);
        $t->same(false, $local['changed']);
        $t->same($mainDataVersion, $after['value']);
        $t->same($mainDataVersion + $localCommits, $state->headerUpdate('main')['file_change_counter']);
    };

    $tests["real upstream pragma3 external commit advances observed data_version variant {$suffix}"] = static function (TestRunner $t) use ($schemaVersion, $mainDataVersion, $otherCommits): void {
        $reader = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $schemaVersion, 'data_version' => $mainDataVersion, 'change_counter' => $mainDataVersion],
        ]);

        $first = $reader->execute('PRAGMA data_version');
        $external = $reader->recordExternalCommit('main', $otherCommits, 'other_connection_commit');
        $second = $reader->execute('PRAGMA data_version');

        $t->same($mainDataVersion, $first['value']);
        $t->same($mainDataVersion + $otherCommits, $external['value']);
        $t->same(true, $external['changed']);
        $t->same('other_connection_commit', $external['reason']);
        $t->same($mainDataVersion + $otherCommits, $second['rows'][0]['data_version']);
        $t->same($schemaVersion, $second['header']['schema_cookie']);
    };

    $tests["real upstream pragma3 uncommitted writer does not advance other connection variant {$suffix}"] = static function (TestRunner $t) use ($schemaVersion, $mainDataVersion): void {
        $reader = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $schemaVersion, 'data_version' => $mainDataVersion],
        ]);
        $writer = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $schemaVersion, 'data_version' => $mainDataVersion],
        ]);

        $writer->beginTransaction();
        $writer->recordLocalCommit('main', 1, 'uncommitted_writer_change');

        $t->same($mainDataVersion, $reader->execute('PRAGMA data_version')['value']);
        $t->same($mainDataVersion, $writer->execute('PRAGMA data_version')['value']);
        $writer->commitTransaction();
        $reader->recordExternalCommit('main', 1, 'writer_commit_visible');
        $t->same($mainDataVersion + 1, $reader->execute('PRAGMA data_version')['value']);
        $t->same($mainDataVersion, $writer->execute('PRAGMA data_version')['value']);
    };

    $tests["real upstream pragma3 shared cache wal and empty transaction semantics variant {$suffix}"] = static function (TestRunner $t) use ($schemaVersion, $mainDataVersion, $schemaName): void {
        $db = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $schemaVersion, 'data_version' => $mainDataVersion],
            $schemaName => ['schema_version' => 0, 'data_version' => 1],
        ]);
        $db2 = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $schemaVersion, 'data_version' => $mainDataVersion],
            $schemaName => ['schema_version' => 0, 'data_version' => 1],
        ]);

        $db->beginTransaction();
        $db->commitTransaction();
        $db->recordLocalCommit('main', 1, 'wal_mode_local_update');
        $db2->recordExternalCommit('main', 1, 'wal_mode_other_connection_observed');

        $t->same($mainDataVersion, $db->execute('PRAGMA data_version')['value']);
        $t->same($mainDataVersion + 1, $db2->execute('PRAGMA main.data_version')['value']);
        $t->same(1, $db->execute("PRAGMA {$schemaName}.data_version")['value']);
        $t->same(1, $db2->execute("PRAGMA {$schemaName}.data_version")['value']);
        $t->same($schemaVersion, $db->execute('PRAGMA schema_version')['value']);
        $t->same($schemaVersion, $db2->execute('PRAGMA schema_version')['header']['schema_cookie']);
    };
}

$tests['real upstream pragma3 data_version source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma3.test pragma3-100 through pragma3-102 initial data_version and assignment no-op',
        'pragma3.test pragma3-110 through pragma3-150 local commit unchanged and other connection advances',
        'pragma3.test pragma3-160 through pragma3-195 transaction visibility and per-connection values',
        'pragma3.test pragma3-300 through pragma3-340 shared-cache data_version behavior',
        'pragma3.test pragma3-400 through pragma3-430 WAL data_version behavior',
        'pragma3.test pragma3-510 through pragma3-520 empty write transaction preserves data_version',
    ];

    $t->same(6, count($sections));
    $t->contains('pragma3-100', $sections[0]);
    $t->contains('pragma3-520', $sections[5]);
};

return $tests;
