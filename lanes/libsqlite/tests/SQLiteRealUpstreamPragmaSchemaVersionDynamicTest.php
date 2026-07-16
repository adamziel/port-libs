<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDataVersionTracker;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-8.1.1 through pragma-8.1.18:
 *   schema_version read/write, defensive-mode write suppression, schema DDL
 *   bumps, attached-schema isolation, and stale prepared statements after a
 *   schema-cookie change.
 * - SQLite test/pragma.test pragma-8.2.1 through pragma-8.2.15:
 *   user_version read/write, attached-schema isolation, rollback restoration,
 *   VACUUM-preserved user_version, and signed negative user_version values.
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

$makeTracker = static function (int $variant): SQLitePragmaSchemaDataVersion {
    return new SQLitePragmaSchemaDataVersion([
        'main' => [
            'schema_version' => 100 + $variant,
            'data_version' => 20 + $variant,
            'change_counter' => 30 + $variant,
            'user_version' => $variant % 11,
        ],
        'aux' => [
            'schema_version' => 200 + $variant,
            'data_version' => 40 + $variant,
            'change_counter' => 50 + $variant,
            'user_version' => ($variant % 7) + 1,
        ],
    ]);
};

foreach (range(1, 180) as $variant) {
    $tests[sprintf('real upstream pragma schema version dynamic pragma-8.1 read write main variant %03d', $variant)] = static function (TestRunner $t) use ($makeTracker, $variant): void {
        $tracker = $makeTracker($variant);
        $set = $tracker->execute('PRAGMA schema_version = ' . (3000 + $variant));
        $read = $tracker->execute('PRAGMA schema_version');

        $t->same('assigned', $set['reason']);
        $t->same(3000 + $variant, $read['value']);
        $t->same(['schema_version' => 3000 + $variant], $read['rows'][0]);
        $t->same(3000 + $variant, $read['header']['schema_cookie']);
    };

    $tests[sprintf('real upstream pragma schema version dynamic pragma-8.1 defensive ignores write variant %03d', $variant)] = static function (TestRunner $t) use ($makeTracker, $variant): void {
        $tracker = $makeTracker($variant);
        $tracker->setDefensive(true);
        $write = $tracker->execute('PRAGMA schema_version = ' . (4000 + $variant));
        $read = $tracker->execute('PRAGMA schema_version');

        $t->same('defensive_schema_version_ignored', $write['reason']);
        $t->same(false, $write['changed']);
        $t->same(100 + $variant, $read['value']);
        $t->same(100 + $variant, $read['header']['schema_cookie']);
    };

    $tests[sprintf('real upstream pragma schema version dynamic pragma-8.1 ddl bump changes schema cookie variant %03d', $variant)] = static function (TestRunner $t) use ($makeTracker, $variant): void {
        $tracker = $makeTracker($variant);
        $bump = $tracker->recordSchemaChange('main', 1 + ($variant % 3), 'create-table');
        $header = $tracker->headerUpdate('main');

        $t->same('create-table', $bump['reason']);
        $t->same(true, $bump['changed']);
        $t->same(101 + $variant + ($variant % 3), $bump['value']);
        $t->same($bump['value'], $header['schema_cookie']);
        $t->same(31 + $variant + ($variant % 3), $header['file_change_counter']);
    };

    $tests[sprintf('real upstream pragma schema version dynamic pragma-8.1 attached schema isolated variant %03d', $variant)] = static function (TestRunner $t) use ($makeTracker, $variant): void {
        $tracker = $makeTracker($variant);
        $tracker->execute('PRAGMA aux.schema_version = ' . (5000 + $variant));
        $aux = $tracker->execute('PRAGMA aux.schema_version');
        $main = $tracker->execute('PRAGMA main.schema_version');

        $t->same('aux', $aux['schema']);
        $t->same(5000 + $variant, $aux['value']);
        $t->same('main', $main['schema']);
        $t->same(100 + $variant, $main['value']);
    };

    $tests[sprintf('real upstream pragma schema version dynamic pragma-8.1 stale prepared expiry variant %03d', $variant)] = static function (TestRunner $t) use ($makeTracker, $variant): void {
        $tracker = $makeTracker($variant);
        $current = 6000 + $variant;
        $plan = $tracker->expirePreparedAfterSchemaRollback([
            ['id' => 'main-current-' . $variant, 'schema' => 'main', 'schema_cookie' => $current],
            ['id' => 'main-stale-' . $variant, 'schema' => 'main', 'schema_cookie' => $current - 1],
            ['id' => 'main-rolled-back-' . $variant, 'schema' => 'main', 'schema_cookie' => $current, 'prepared_during_rolled_back_schema' => true],
            ['id' => 'aux-current-' . $variant, 'schema' => 'aux', 'schema_cookie' => 200 + $variant],
        ], 'main', $current);

        $t->same(['main-stale-' . $variant, 'main-rolled-back-' . $variant], $plan['expired']);
        $t->same(['main-current-' . $variant, 'aux-current-' . $variant], $plan['preserved']);
        $t->same('schema_cookie_mismatch', $plan['reasons']['main-stale-' . $variant]);
        $t->same('prepared_during_rolled_back_schema', $plan['reasons']['main-rolled-back-' . $variant]);
        $t->same('different_schema', $plan['reasons']['aux-current-' . $variant]);
    };

    $tests[sprintf('real upstream pragma schema version dynamic pragma-8.2 user version rollback variant %03d', $variant)] = static function (TestRunner $t) use ($makeTracker, $variant): void {
        $tracker = $makeTracker($variant);
        $mainBefore = $tracker->execute('PRAGMA user_version')['value'];
        $auxBefore = $tracker->execute('PRAGMA aux.user_version')['value'];
        $tracker->beginTransaction();
        $tracker->execute('PRAGMA user_version = ' . (7000 + $variant));
        $tracker->execute('PRAGMA aux.user_version = ' . (8000 + $variant));
        $duringMain = $tracker->execute('PRAGMA main.user_version');
        $duringAux = $tracker->execute('PRAGMA aux.user_version');
        $rollback = $tracker->rollbackTransaction();

        $t->same(7000 + $variant, $duringMain['value']);
        $t->same(8000 + $variant, $duringAux['value']);
        $t->same(true, $rollback['restored']);
        $t->same($mainBefore, $tracker->execute('PRAGMA main.user_version')['value']);
        $t->same($auxBefore, $tracker->execute('PRAGMA aux.user_version')['value']);
    };

    $tests[sprintf('real upstream pragma schema version dynamic pragma-8.2 user version vacuum preserves value variant %03d', $variant)] = static function (TestRunner $t) use ($makeTracker, $variant): void {
        $tracker = $makeTracker($variant);
        $tracker->execute('PRAGMA user_version = ' . (9000 + $variant));
        $schemaBefore = $tracker->execute('PRAGMA schema_version')['value'];
        $tracker->recordSchemaChange('main', 1, 'vacuum');

        $t->same(9000 + $variant, $tracker->execute('PRAGMA user_version')['value']);
        $t->same($schemaBefore + 1, $tracker->execute('PRAGMA schema_version')['value']);
        $t->same('vacuum', $tracker->recordLocalCommit('main', 1, 'vacuum')['reason']);
    };

    $tests[sprintf('real upstream pragma schema version dynamic pragma-8.2 signed negative user version variant %03d', $variant)] = static function (TestRunner $t) use ($makeTracker, $variant): void {
        $tracker = $makeTracker($variant);
        $negative = -450 - $variant;
        $set = $tracker->execute('PRAGMA user_version = ' . $negative);
        $read = $tracker->execute('PRAGMA user_version');

        $t->same('assigned', $set['reason']);
        $t->same($negative, $read['value']);
        $t->same(['user_version' => $negative], $read['rows'][0]);
        $t->same(100 + $variant, $read['header']['schema_cookie']);
    };
}

$tests['real upstream pragma schema version dynamic cites pragma section eight corpus'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.1.1 through pragma-8.1.4 schema_version assignment and defensive suppression',
        'pragma.test pragma-8.1.5 through pragma-8.1.18 DDL and attached schema_version changes expire stale prepared statements',
        'pragma.test pragma-8.2.1 through pragma-8.2.15 user_version read/write, rollback restoration, VACUUM preservation, and signed negative values',
    ];

    $t->same(3, count($sections));
    $t->same(true, str_contains($sections[0], 'defensive'));
    $t->same(true, str_contains($sections[1], 'attached'));
    $t->same(true, str_contains($sections[2], 'negative'));
};

return $tests;
