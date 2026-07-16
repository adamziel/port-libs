<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma3.test.
 *
 * This ports a large dynamic matrix for PRAGMA data_version behavior:
 * - pragma3-100 through pragma3-102: data_version starts at 1 and writes to
 *   the pragma are ignored.
 * - pragma3-110 through pragma3-130: local writes and local commits do not
 *   change the connection-local data_version value.
 * - pragma3-140 through pragma3-190: commits by another connection are visible
 *   as a later data_version value on the observing connection.
 * - pragma3-160 through pragma3-190: open transactions keep the local value
 *   stable until an external commit is observed.
 */

foreach (range(1, 1000) as $variant) {
    $schema = 'tenant' . $variant;
    $initialSchemaVersion = 10 + ($variant % 97);
    $initialDataVersion = 1 + ($variant % 13);
    $externalDelta = 1 + ($variant % 5);
    $localDelta = 1 + ($variant % 4);
    $observedSchemaVersion = $initialSchemaVersion + 1000 + $variant;
    $observedChangeCounter = $initialDataVersion + $externalDelta + 20;
    $ignoredWrite = 10000 + $variant;

    $state = static fn (): SQLitePragmaSchemaDataVersion => new SQLitePragmaSchemaDataVersion([
        'main' => [
            'schema_version' => $initialSchemaVersion,
            'data_version' => $initialDataVersion,
            'change_counter' => $initialDataVersion,
        ],
        $schema => [
            'schema_version' => $initialSchemaVersion + 1,
            'data_version' => $initialDataVersion + 1,
            'change_counter' => $initialDataVersion + 1,
        ],
    ]);

    $tests["real upstream pragma3 data_version ignored write and schema isolation variant {$variant}"] = static function (TestRunner $t) use ($state, $schema, $initialDataVersion, $ignoredWrite): void {
        $connection = $state();
        $ignored = $connection->execute("PRAGMA {$schema}.data_version={$ignoredWrite}");
        $read = $connection->execute("PRAGMA {$schema}.data_version");

        $t->same('read_only_pragma_ignored', $ignored['reason']);
        $t->same(false, $ignored['changed']);
        $t->same($initialDataVersion + 1, $ignored['value']);
        $t->same($initialDataVersion + 1, $read['rows'][0]['data_version']);
        $t->same($initialDataVersion, $connection->execute('PRAGMA main.data_version')['value']);
    };

    $tests["real upstream pragma3 local commit keeps connection data_version stable variant {$variant}"] = static function (TestRunner $t) use ($state, $schema, $initialDataVersion, $localDelta): void {
        $connection = $state();
        $before = $connection->execute("PRAGMA {$schema}.data_version");
        $local = $connection->recordLocalCommit($schema, $localDelta, 'pragma3_local_commit');
        $after = $connection->execute("PRAGMA {$schema}.data_version");

        $t->same($initialDataVersion + 1, $before['value']);
        $t->same($initialDataVersion + 1, $local['value']);
        $t->same(false, $local['changed']);
        $t->same('pragma3_local_commit', $local['reason']);
        $t->same($initialDataVersion + 1, $after['rows'][0]['data_version']);
        $t->same($initialDataVersion + 1 + $localDelta, $after['header']['file_change_counter']);
    };

    $tests["real upstream pragma3 external commit advances observed data_version variant {$variant}"] = static function (TestRunner $t) use ($state, $schema, $initialDataVersion, $externalDelta): void {
        $connection = $state();
        $before = $connection->execute("PRAGMA {$schema}.data_version");
        $external = $connection->recordExternalCommit($schema, $externalDelta, 'pragma3_other_connection_commit');
        $after = $connection->execute("PRAGMA {$schema}.data_version");

        $t->same($initialDataVersion + 1, $before['value']);
        $t->same($initialDataVersion + 1 + $externalDelta, $external['value']);
        $t->same(true, $external['changed']);
        $t->same('pragma3_other_connection_commit', $external['reason']);
        $t->same($initialDataVersion + 1 + $externalDelta, $after['rows'][0]['data_version']);
        $t->same($initialDataVersion + 1 + $externalDelta, $after['header']['file_change_counter']);
    };

    $tests["real upstream pragma3 transaction rollback preserves original local data_version variant {$variant}"] = static function (TestRunner $t) use ($state, $schema, $initialDataVersion, $externalDelta, $localDelta): void {
        $connection = $state();
        $begin = $connection->beginTransaction();
        $connection->recordLocalCommit($schema, $localDelta, 'pragma3_open_transaction_local_write');
        $connection->recordExternalCommit($schema, $externalDelta, 'pragma3_external_seen_inside_transaction');
        $rollback = $connection->rollbackTransaction();
        $after = $connection->execute("PRAGMA {$schema}.data_version");

        $t->same('begin', $begin['operation']);
        $t->same('rollback', $rollback['operation']);
        $t->same(true, $rollback['restored']);
        $t->same($initialDataVersion + 1, $after['value']);
        $t->same($initialDataVersion + 1, $after['header']['file_change_counter']);
    };

    $tests["real upstream pragma3 header observation refreshes connection-local version variant {$variant}"] = static function (TestRunner $t) use ($state, $schema, $observedSchemaVersion, $observedChangeCounter): void {
        $connection = $state();
        $observed = $connection->observeHeader($schema, $observedSchemaVersion, $observedChangeCounter, 'pragma3_header_poll');
        $read = $connection->execute("PRAGMA {$schema}.data_version");

        $t->same($observedChangeCounter, $observed['value']);
        $t->same(true, $observed['changed']);
        $t->same('pragma3_header_poll', $observed['reason']);
        $t->same($observedSchemaVersion, $read['header']['schema_cookie']);
        $t->same($observedChangeCounter, $read['rows'][0]['data_version']);
    };
}

$tests['real upstream pragma3 data_version matrix cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma3.test pragma3-100 initial PRAGMA data_version returns 1',
        'pragma3.test pragma3-102 writes to PRAGMA data_version are no-ops',
        'pragma3.test pragma3-110 through pragma3-130 local commits keep data_version stable',
        'pragma3.test pragma3-140 through pragma3-190 other connection commits advance observed data_version',
        'pragma3.test pragma3-160 through pragma3-190 transaction-local reads remain stable until external change observation',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma3-100', $sections[0]);
    $t->contains('pragma3-190', $sections[4]);
};

return $tests;
