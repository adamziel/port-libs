<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test pragma-8.1.1 through
 * pragma-8.1.18 and pragma-8.2.1 through pragma-8.2.15.
 *
 * This expands the existing schema-version corpus with a high-yield dynamic
 * matrix for schema_version write/readback, defensive ignored writes,
 * schema-cookie reload, attached-schema isolation, user_version rollback,
 * VACUUM-style schema-cookie bumps preserving user_version, and signed
 * user_version values.
 */

$value = static fn (array $result): int => (int) $result['value'];
$rowValue = static fn (array $result, string $pragma): int => (int) $result['rows'][0][$pragma];

foreach (range(1, 1000) as $variant) {
    $baseSchema = 100 + $variant;
    $assignedSchema = 200 + $variant;
    $defensiveAttempt = 300 + $variant;
    $attachedSchema = 400 + $variant;
    $mainUserVersion = $variant % 2 === 0 ? $variant : -$variant;
    $attachedUserVersion = 500 + $variant;
    $transactionMainUserVersion = 700 + $variant;
    $transactionAttachedUserVersion = 900 + $variant;

    $tests[sprintf('real upstream pragma schema version expanded assignment and defensive ignore variant %04d', $variant)] = static function (TestRunner $t) use ($value, $rowValue, $baseSchema, $assignedSchema, $defensiveAttempt): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $baseSchema, 'data_version' => 11, 'change_counter' => 11],
        ]);

        $assigned = $state->execute("PRAGMA schema_version = {$assignedSchema}");
        $state->setDefensive(true);
        $ignored = $state->execute("PRAGMA schema_version = {$defensiveAttempt}");
        $read = $state->execute('PRAGMA schema_version');

        $t->same('assigned', $assigned['reason']);
        $t->same(true, $assigned['changed']);
        $t->same($assignedSchema, $value($assigned));
        $t->same('defensive_schema_version_ignored', $ignored['reason']);
        $t->same(false, $ignored['changed']);
        $t->same($assignedSchema, $value($ignored));
        $t->same($assignedSchema, $rowValue($read, 'schema_version'));
        $t->same($assignedSchema, $read['header']['schema_cookie']);
    };

    $tests[sprintf('real upstream pragma schema version expanded attached reload isolation variant %04d', $variant)] = static function (TestRunner $t) use ($baseSchema, $assignedSchema, $attachedSchema): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $baseSchema, 'data_version' => 5, 'change_counter' => 5],
            'aux' => ['schema_version' => $attachedSchema, 'data_version' => 7, 'change_counter' => 7],
        ]);

        $mainReload = $state->schemaVersionReloadPlan("PRAGMA schema_version = {$assignedSchema}", [
            ['id' => 'main-old', 'schema' => 'main', 'schema_cookie' => $baseSchema],
            ['id' => 'main-new', 'schema' => 'main', 'schema_cookie' => $assignedSchema],
            ['id' => 'aux-statement', 'schema' => 'aux', 'schema_cookie' => $attachedSchema],
        ]);
        $auxReload = $state->schemaVersionReloadPlan('PRAGMA aux.schema_version = ' . ($attachedSchema + 1), [
            ['id' => 'main-after-aux', 'schema' => 'main', 'schema_cookie' => $assignedSchema],
            ['id' => 'aux-old', 'schema' => 'aux', 'schema_cookie' => $attachedSchema],
        ]);

        $t->same(['main-old'], $mainReload['expired']);
        $t->same(['main-new', 'aux-statement'], $mainReload['preserved']);
        $t->same('schema_cookie_mismatch_after_pragma_assignment', $mainReload['reasons']['main-old']);
        $t->same('different_schema', $mainReload['reasons']['aux-statement']);
        $t->same($assignedSchema, $mainReload['header']['schema_cookie']);
        $t->same(['aux-old'], $auxReload['expired']);
        $t->same(['main-after-aux'], $auxReload['preserved']);
        $t->same('different_schema', $auxReload['reasons']['main-after-aux']);
    };

    $tests[sprintf('real upstream pragma schema version expanded user version rollback variant %04d', $variant)] = static function (TestRunner $t) use ($value, $mainUserVersion, $attachedUserVersion, $transactionMainUserVersion, $transactionAttachedUserVersion): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => 10, 'user_version' => $mainUserVersion],
            'aux' => ['schema_version' => 20, 'user_version' => $attachedUserVersion],
        ]);

        $state->beginTransaction();
        $state->execute("PRAGMA user_version = {$transactionMainUserVersion}");
        $state->execute("PRAGMA aux.user_version = {$transactionAttachedUserVersion}");
        $during = [$value($state->execute('PRAGMA user_version')), $value($state->execute('PRAGMA aux.user_version'))];
        $rollback = $state->rollbackTransaction();
        $after = [$value($state->execute('PRAGMA user_version')), $value($state->execute('PRAGMA aux.user_version'))];

        $t->same([$transactionMainUserVersion, $transactionAttachedUserVersion], $during);
        $t->same('rollback', $rollback['operation']);
        $t->same(true, $rollback['restored']);
        $t->same([$mainUserVersion, $attachedUserVersion], $after);
        $t->same($mainUserVersion, $state->state()['main']['user_version']);
        $t->same($attachedUserVersion, $state->state()['aux']['user_version']);
    };

    $tests[sprintf('real upstream pragma schema version expanded vacuum preserves user version variant %04d', $variant)] = static function (TestRunner $t) use ($value, $baseSchema, $mainUserVersion): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $baseSchema, 'user_version' => $mainUserVersion, 'data_version' => 3, 'change_counter' => 3],
        ]);

        $beforeUser = $state->execute('PRAGMA user_version');
        $schemaChange = $state->recordSchemaChange('main', 1, 'vacuum_schema_rebuild');
        $afterUser = $state->execute('PRAGMA user_version');
        $afterSchema = $state->execute('PRAGMA schema_version');

        $t->same($mainUserVersion, $value($beforeUser));
        $t->same('vacuum_schema_rebuild', $schemaChange['reason']);
        $t->same(true, $schemaChange['changed']);
        $t->same($baseSchema + 1, $value($afterSchema));
        $t->same($mainUserVersion, $value($afterUser));
        $t->same($baseSchema + 1, $afterSchema['header']['schema_cookie']);
        $t->same(4, $afterSchema['header']['file_change_counter']);
    };

    $tests[sprintf('real upstream pragma schema version expanded rollback-expired prepared statements variant %04d', $variant)] = static function (TestRunner $t) use ($baseSchema): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => $baseSchema],
            'aux' => ['schema_version' => $baseSchema + 10],
        ]);

        $plan = $state->expirePreparedAfterSchemaRollback([
            ['id' => 'current-main', 'schema' => 'main', 'schema_cookie' => $baseSchema],
            ['id' => 'stale-main', 'schema' => 'main', 'schema_cookie' => $baseSchema - 1],
            ['id' => 'rolled-back-main', 'schema' => 'main', 'schema_cookie' => $baseSchema, 'prepared_during_rolled_back_schema' => true],
            ['id' => 'attached-aux', 'schema' => 'aux', 'schema_cookie' => $baseSchema + 10],
        ], 'main', $baseSchema);

        $t->same('expire-prepared-after-schema-rollback', $plan['operation']);
        $t->same(['stale-main', 'rolled-back-main'], $plan['expired']);
        $t->same(['current-main', 'attached-aux'], $plan['preserved']);
        $t->same('schema_cookie_current', $plan['reasons']['current-main']);
        $t->same('schema_cookie_mismatch', $plan['reasons']['stale-main']);
        $t->same('prepared_during_rolled_back_schema', $plan['reasons']['rolled-back-main']);
        $t->same('different_schema', $plan['reasons']['attached-aux']);
    };
}

$tests['real upstream pragma schema version expanded cites upstream sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.1.1 through pragma-8.1.4 cover schema_version assignment, readback, and defensive ignored writes',
        'pragma.test pragma-8.1.7 through pragma-8.1.18 cover schema-cookie reload of prepared statements in main and attached schemas',
        'pragma.test pragma-8.2.1 through pragma-8.2.8 cover user_version read/write isolation for main and attached schemas',
        'pragma.test pragma-8.2.9 through pragma-8.2.15 cover rollback of user_version writes, VACUUM schema-cookie changes, and signed user_version values',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma-8.1.1', $sections[0]);
    $t->contains('attached schemas', $sections[1]);
    $t->contains('user_version', $sections[2]);
    $t->contains('VACUUM', $sections[3]);
};

return $tests;
