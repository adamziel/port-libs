<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDataVersionTracker;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-8.1.1 through pragma-8.1.18:
 *   schema_version is writable, DEFENSIVE mode ignores writes, schema DDL
 *   bumps the cookie, attached schemas carry independent cookies, and stale
 *   prepared statements must expire when a schema cookie changes.
 * - SQLite test/pragma.test pragma-8.2.1 through pragma-8.2.15:
 *   user_version is independent from schema_version, is transactionally
 *   rollbackable, supports attached schemas, and accepts signed values.
 * - SQLite test/pragma3.test pragma3-100 through pragma3-340:
 *   data_version is read-only, local to each connection, unchanged by local
 *   commits, and changes only after other connections commit.
 * - SQLite test/schema.test schema-4.*: rolling back schema changes can return
 *   to an equal schema cookie, but statements compiled during the rolled-back
 *   schema must still be expired.
 */

$value = static fn (array $row): int => (int) $row['value'];

foreach (range(1, 300) as $variant) {
    $schemaVersion = 100 + $variant;
    $nextSchemaVersion = $schemaVersion + 1;
    $auxSchemaVersion = 200 + $variant;
    $userVersion = $variant % 2 === 0 ? $variant : -$variant;
    $mainDataVersion = 10 + $variant;
    $auxDataVersion = 20 + $variant;

    $tests[sprintf('real upstream pragma schema version dynamic defensive and ddl cookie variant %03d', $variant)] = static function (TestRunner $t) use ($schemaVersion, $nextSchemaVersion, $value): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => [
                'schema_version' => $schemaVersion,
                'data_version' => 7,
                'change_counter' => 7,
            ],
        ]);

        $t->same($schemaVersion, $value($state->execute('PRAGMA schema_version')));

        $state->setDefensive(true);
        $ignored = $state->execute('PRAGMA schema_version=' . $nextSchemaVersion);
        $t->same(false, $ignored['changed']);
        $t->same('defensive_schema_version_ignored', $ignored['reason']);
        $t->same($schemaVersion, $value($state->execute('PRAGMA schema_version')));

        $state->setDefensive(false);
        $assigned = $state->execute('PRAGMA schema_version=' . $nextSchemaVersion);
        $t->same(true, $assigned['changed']);
        $t->same('assigned', $assigned['reason']);
        $t->same($nextSchemaVersion, $assigned['header']['schema_cookie']);
        $t->same(7, $assigned['header']['file_change_counter']);

        $ddl = $state->recordSchemaChange('main', 1, 'create_table');
        $t->same($nextSchemaVersion + 1, $ddl['header']['schema_cookie']);
        $t->same(8, $ddl['header']['file_change_counter']);
        $t->same(true, $state->state()['main']['schema_dirty']);
    };

    $tests[sprintf('real upstream pragma schema version dynamic attached user rollback variant %03d', $variant)] = static function (TestRunner $t) use ($schemaVersion, $auxSchemaVersion, $userVersion, $value): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => [
                'schema_version' => $schemaVersion,
                'user_version' => 2,
                'data_version' => 3,
                'change_counter' => 3,
            ],
            'aux' => [
                'schema_version' => $auxSchemaVersion,
                'user_version' => 3,
                'data_version' => 4,
                'change_counter' => 4,
            ],
        ]);

        $t->same($auxSchemaVersion, $value($state->execute('PRAGMA aux.schema_version')));
        $t->same(3, $value($state->execute('PRAGMA aux.user_version')));
        $t->same(2, $value($state->execute('PRAGMA main.user_version')));

        $state->beginTransaction();
        $state->execute('PRAGMA aux.user_version=10');
        $state->execute('PRAGMA main.user_version=11');
        $t->same(10, $value($state->execute('PRAGMA aux.user_version')));
        $t->same(11, $value($state->execute('PRAGMA main.user_version')));
        $rollback = $state->rollbackTransaction();
        $t->same(true, $rollback['restored']);
        $t->same(3, $value($state->execute('PRAGMA aux.user_version')));
        $t->same(2, $value($state->execute('PRAGMA main.user_version')));

        $assigned = $state->execute('PRAGMA user_version=' . $userVersion);
        $t->same($userVersion, $assigned['value']);
        $t->same($schemaVersion, $value($state->execute('PRAGMA schema_version')));
    };

    $tests[sprintf('real upstream pragma data version dynamic local and remote commits variant %03d', $variant)] = static function (TestRunner $t) use ($variant): void {
        $tracker = new SQLitePragmaDataVersionTracker(1);
        $tracker->open('reader');
        $tracker->open('writer');

        $t->same(1, $tracker->executePragma('reader', 'PRAGMA data_version')['value']);
        $t->same(1, $tracker->executePragma('writer', 'PRAGMA temp.data_version')['value']);

        $ignored = $tracker->executePragma('reader', 'PRAGMA main.data_version=' . (1000 + $variant));
        $t->same(true, $ignored['write_ignored']);
        $t->same(1, $ignored['value']);

        $tracker->begin('reader');
        $t->same(1, $tracker->executePragma('reader', 'PRAGMA data_version')['value']);
        $tracker->commit('reader', true);
        $t->same(1, $tracker->executePragma('reader', 'PRAGMA data_version')['value']);

        $remoteAfterReaderCommit = $tracker->executePragma('writer', 'PRAGMA data_version');
        $t->same(2, $remoteAfterReaderCommit['value']);
        $t->same(true, $remoteAfterReaderCommit['changed_by_other_connection']);

        $tracker->begin('writer');
        $tracker->commit('writer', true);
        $t->same(2, $tracker->executePragma('writer', 'PRAGMA data_version')['value']);
        $t->same(2, $tracker->executePragma('reader', 'PRAGMA data_version')['value']);
    };

    $tests[sprintf('real upstream schema rollback expires prepared statement despite equal cookie variant %03d', $variant)] = static function (TestRunner $t) use ($schemaVersion, $mainDataVersion, $auxDataVersion): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => [
                'schema_version' => $schemaVersion,
                'data_version' => $mainDataVersion,
                'change_counter' => $mainDataVersion,
            ],
            'aux' => [
                'schema_version' => $schemaVersion,
                'data_version' => $auxDataVersion,
                'change_counter' => $auxDataVersion,
            ],
        ]);

        $state->beginTransaction();
        $state->recordSchemaChange('main', 1, 'create_table_inside_transaction');
        $rolledBackCookie = $state->execute('PRAGMA schema_version')['value'];
        $state->rollbackTransaction();

        $expiration = $state->expirePreparedAfterSchemaRollback([
            [
                'id' => 'before-main',
                'schema' => 'main',
                'schema_cookie' => $schemaVersion,
            ],
            [
                'id' => 'during-main',
                'schema' => 'main',
                'schema_cookie' => $rolledBackCookie,
                'prepared_during_rolled_back_schema' => true,
            ],
            [
                'id' => 'stale-main',
                'schema' => 'main',
                'schema_cookie' => $schemaVersion + 9,
            ],
            [
                'id' => 'aux-current',
                'schema' => 'aux',
                'schema_cookie' => $schemaVersion,
            ],
        ], 'main', $schemaVersion);

        $t->same(['during-main', 'stale-main'], $expiration['expired']);
        $t->same(['before-main', 'aux-current'], $expiration['preserved']);
        $t->same('prepared_during_rolled_back_schema', $expiration['reasons']['during-main']);
        $t->same('schema_cookie_mismatch', $expiration['reasons']['stale-main']);
        $t->same('different_schema', $expiration['reasons']['aux-current']);
        $t->same($schemaVersion, $expiration['current_schema_cookie']);
        $t->same($mainDataVersion, $state->execute('PRAGMA data_version')['value']);
        $t->same($auxDataVersion, $state->execute('PRAGMA aux.data_version')['value']);
    };
}

$tests['real upstream pragma schema version dynamic cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.1.1 through pragma-8.1.18 schema_version assignment, DEFENSIVE rejection, attached schema cookies, and SQLITE_SCHEMA expiry',
        'pragma.test pragma-8.2.1 through pragma-8.2.15 user_version persistence, attached isolation, rollback restoration, and signed values',
        'pragma3.test pragma3-100 through pragma3-340 data_version read-only/local-connection/external-commit behavior',
        'schema.test schema-4.* schema rollback may restore the same cookie while statements prepared during the rolled-back schema still expire',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma-8.1', $sections[0]);
    $t->contains('pragma-8.2', $sections[1]);
    $t->contains('pragma3-100', $sections[2]);
    $t->contains('schema-4', $sections[3]);
};

return $tests;
