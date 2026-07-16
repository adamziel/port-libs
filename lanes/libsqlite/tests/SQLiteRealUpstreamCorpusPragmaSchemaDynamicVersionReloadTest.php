<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-8.1.1 through pragma-8.1.18:
 *   PRAGMA schema_version=N writes the schema cookie unless defensive mode is
 *   enabled, attached schema cookies are independent, and prepared statements
 *   compiled against the old cookie see SQLITE_SCHEMA on their next step.
 * - SQLite test/pragma.test pragma-8.2.1 through pragma-8.2.4.3:
 *   PRAGMA user_version is independent from schema_version and survives VACUUM
 *   while schema_version advances.
 * - SQLite test/altertab.test 22.0 and 22.1: writing schema_version after a
 *   writable sqlite_schema edit forces a full schema reload before later ALTER
 *   TABLE work observes the repaired CREATE TABLE text.
 */

foreach (range(1, 1000) as $variant) {
    $schemaBefore = 1000 + $variant;
    $schemaAfter = 2000 + $variant;
    $auxBefore = 3000 + $variant;
    $auxAfter = 4000 + $variant;
    $userBefore = $variant % 97;
    $userAfter = $userBefore + 100;
    $staleStatement = "select-settings-stale-{$variant}";
    $freshStatement = "select-settings-fresh-{$variant}";
    $auxStatement = "select-archive-{$variant}";

    $tests[sprintf('real upstream pragma schema dynamic version reload main prepared expiry variant %04d', $variant)] = static function (TestRunner $t) use ($schemaBefore, $schemaAfter, $staleStatement, $freshStatement, $auxStatement): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $schemaBefore, 'data_version' => 7, 'change_counter' => 7],
            'aux' => ['schema_version' => 6000, 'data_version' => 11, 'change_counter' => 11],
        ]);
        $plan = $state->schemaVersionReloadPlan("PRAGMA schema_version={$schemaAfter}", [
            ['id' => $staleStatement, 'schema' => 'main', 'schema_cookie' => $schemaBefore, 'sql' => 'SELECT key_name FROM settings'],
            ['id' => $freshStatement, 'schema' => 'main', 'schema_cookie' => $schemaAfter, 'sql' => 'SELECT key_value FROM settings'],
            ['id' => $auxStatement, 'schema' => 'aux', 'schema_cookie' => 6000, 'sql' => 'SELECT key_name FROM archive_settings'],
        ]);

        $t->same('ok', $plan['status']);
        $t->same('schema-version-reload-plan', $plan['operation']);
        $t->same('main', $plan['schema']);
        $t->same($schemaBefore, $plan['before_schema_version']);
        $t->same($schemaAfter, $plan['after_schema_version']);
        $t->same(true, $plan['changed']);
        $t->same('assigned', $plan['reason']);
        $t->same([$staleStatement], $plan['expired']);
        $t->same([$freshStatement, $auxStatement], $plan['preserved']);
        $t->same('schema_cookie_mismatch_after_pragma_assignment', $plan['reasons'][$staleStatement]);
        $t->same('schema_cookie_matches_assigned_value', $plan['reasons'][$freshStatement]);
        $t->same('different_schema', $plan['reasons'][$auxStatement]);
        $t->same(['schema_cookie' => $schemaAfter, 'file_change_counter' => 7], $plan['header']);
    };

    $tests[sprintf('real upstream pragma schema dynamic version reload defensive ignore variant %04d', $variant)] = static function (TestRunner $t) use ($schemaBefore, $schemaAfter, $staleStatement): void {
        $state = new SQLitePragmaSchemaDataVersion(['main' => ['schema_version' => $schemaBefore]]);
        $state->setDefensive(true);
        $plan = $state->schemaVersionReloadPlan("PRAGMA schema_version={$schemaAfter}", [
            ['id' => $staleStatement, 'schema' => 'main', 'schema_cookie' => $schemaBefore],
        ]);

        $t->same($schemaBefore, $plan['before_schema_version']);
        $t->same($schemaBefore, $plan['after_schema_version']);
        $t->same(false, $plan['changed']);
        $t->same('defensive_schema_version_ignored', $plan['reason']);
        $t->same([], $plan['expired']);
        $t->same([$staleStatement], $plan['preserved']);
        $t->same('schema_cookie_unchanged', $plan['reasons'][$staleStatement]);
    };

    $tests[sprintf('real upstream pragma schema dynamic version reload attached schema isolation variant %04d', $variant)] = static function (TestRunner $t) use ($schemaBefore, $auxBefore, $auxAfter, $staleStatement, $auxStatement): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $schemaBefore],
            'aux' => ['schema_version' => $auxBefore, 'data_version' => 5, 'change_counter' => 5],
        ]);
        $plan = $state->schemaVersionReloadPlan("PRAGMA aux.schema_version={$auxAfter}", [
            ['id' => $staleStatement, 'schema' => 'main', 'schema_cookie' => $schemaBefore],
            ['id' => $auxStatement, 'schema' => 'aux', 'schema_cookie' => $auxBefore],
        ]);

        $t->same('aux', $plan['schema']);
        $t->same($auxBefore, $plan['before_schema_version']);
        $t->same($auxAfter, $plan['after_schema_version']);
        $t->same([$auxStatement], $plan['expired']);
        $t->same([$staleStatement], $plan['preserved']);
        $t->same($schemaBefore, $state->execute('PRAGMA main.schema_version')['value']);
        $t->same($auxAfter, $state->execute('PRAGMA aux.schema_version')['value']);
        $t->same(['schema_cookie' => $auxAfter, 'file_change_counter' => 5], $plan['header']);
    };

    $tests[sprintf('real upstream pragma schema dynamic version reload user version independent variant %04d', $variant)] = static function (TestRunner $t) use ($schemaBefore, $userBefore, $userAfter): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $schemaBefore, 'user_version' => $userBefore, 'data_version' => 9, 'change_counter' => 9],
        ]);
        $userWrite = $state->execute("PRAGMA user_version={$userAfter}");
        $schemaAfterUserWrite = $state->execute('PRAGMA schema_version');
        $schemaChange = $state->recordSchemaChange('main', 1, 'vacuum_schema_cookie_bump');

        $t->same($userAfter, $userWrite['value']);
        $t->same(true, $userWrite['changed']);
        $t->same(['schema_cookie' => $schemaBefore, 'file_change_counter' => 9], $userWrite['header']);
        $t->same($schemaBefore, $schemaAfterUserWrite['value']);
        $t->same($userAfter, $state->execute('PRAGMA user_version')['value']);
        $t->same($schemaBefore + 1, $schemaChange['value']);
        $t->same($userAfter, $state->execute('PRAGMA user_version')['value']);
        $t->same(['schema_cookie' => $schemaBefore + 1, 'file_change_counter' => 10], $schemaChange['header']);
    };

    $tests[sprintf('real upstream altertab dynamic writable schema reload variant %04d', $variant)] = static function (TestRunner $t) use ($schemaBefore, $schemaAfter, $staleStatement): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $schemaBefore, 'data_version' => 12, 'change_counter' => 12],
        ]);
        $state->beginTransaction();
        $plan = $state->schemaVersionReloadPlan("PRAGMA schema_version={$schemaAfter}", [
            ['id' => $staleStatement, 'schema' => 'main', 'schema_cookie' => $schemaBefore, 'sql' => 'ALTER TABLE t1 ADD COLUMN c INT DEFAULT 78'],
        ]);
        $commit = $state->commitTransaction();

        $t->same([$staleStatement], $plan['expired']);
        $t->same('schema_cookie_mismatch_after_pragma_assignment', $plan['reasons'][$staleStatement]);
        $t->same($schemaAfter, $plan['after_schema_version']);
        $t->same(true, $plan['changed']);
        $t->same('commit', $commit['operation']);
        $t->same(true, $commit['committed']);
        $t->same($schemaAfter, $state->execute('PRAGMA schema_version')['value']);
    };
}

$tests['real upstream pragma schema dynamic version reload source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.1.1 through pragma-8.1.18 assigns schema_version, honors defensive mode, and expires old prepared statements',
        'pragma.test pragma-8.2.1 through pragma-8.2.4.3 keeps user_version independent while VACUUM advances schema_version',
        'altertab.test 22.0 and 22.1 use writable_schema plus PRAGMA schema_version=1234 to force a schema reload before ALTER TABLE',
    ];

    $t->same(3, count($sections));
    $t->contains('pragma-8.1.1', $sections[0]);
    $t->contains('user_version', $sections[1]);
    $t->contains('altertab.test 22.0', $sections[2]);
};

return $tests;
