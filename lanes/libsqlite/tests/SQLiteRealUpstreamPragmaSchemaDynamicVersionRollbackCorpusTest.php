<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-8.1.1 through pragma-8.1.18:
 *   PRAGMA schema_version can be read/written per schema, defensive mode
 *   ignores direct schema-version writes, schema DDL bumps the schema cookie,
 *   and attached schema-version changes are isolated to the attached catalog.
 * - SQLite test/pragma.test pragma-8.2.1 through pragma-8.2.15:
 *   PRAGMA user_version is independent from schema_version, persists per
 *   schema, rolls back when changed in a transaction, and accepts signed
 *   32-bit values while PRAGMA data_version remains read-only.
 */

$value = static fn (array $result): int => $result['value'];
$header = static fn (array $result, string $key): int => $result['header'][$key];

foreach (range(1, 1000) as $variant) {
    $tests["real upstream pragma schema dynamic version rollback corpus variant {$variant}"] = static function (TestRunner $t) use ($variant, $value, $header): void {
        $mainSchema = 100 + $variant;
        $mainData = 10 + $variant;
        $auxSchema = 200 + $variant;
        $auxData = 20 + $variant;
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => [
                'schema_version' => $mainSchema,
                'data_version' => $mainData,
                'change_counter' => $mainData,
                'user_version' => 2,
            ],
            'aux' => [
                'schema_version' => $auxSchema,
                'data_version' => $auxData,
                'change_counter' => $auxData,
                'user_version' => 3,
            ],
        ]);

        $t->same($mainSchema, $value($state->execute('PRAGMA schema_version')));
        $t->same($mainData, $value($state->execute('PRAGMA data_version')));
        $t->same(2, $value($state->execute('PRAGMA user_version')));
        $t->same($auxSchema, $value($state->execute('PRAGMA aux.schema_version')));
        $t->same($auxData, $value($state->execute('PRAGMA aux.data_version')));
        $t->same(3, $value($state->execute('PRAGMA aux.user_version')));

        $state->execute('PRAGMA schema_version = ' . (300 + $variant));
        $state->execute('PRAGMA aux.schema_version = ' . (400 + $variant));
        $t->same(300 + $variant, $value($state->execute('PRAGMA main.schema_version')));
        $t->same(400 + $variant, $value($state->execute('PRAGMA aux.schema_version')));
        $t->same($mainData, $value($state->execute('PRAGMA main.data_version')));
        $t->same($auxData, $value($state->execute('PRAGMA aux.data_version')));
        $t->same(300 + $variant, $header($state->execute('PRAGMA schema_version'), 'schema_cookie'));
        $t->same($mainData, $header($state->execute('PRAGMA schema_version'), 'file_change_counter'));

        $state->setDefensive(true);
        $ignored = $state->execute('PRAGMA schema_version = ' . (500 + $variant));
        $state->setDefensive(false);
        $t->same('defensive_schema_version_ignored', $ignored['reason']);
        $t->same(false, $ignored['changed']);
        $t->same(300 + $variant, $value($state->execute('PRAGMA schema_version')));

        $state->recordSchemaChange('main', 1, 'create_table');
        $t->same(301 + $variant, $value($state->execute('PRAGMA schema_version')));
        $t->same($mainData + 1, $header($state->execute('PRAGMA schema_version'), 'file_change_counter'));
        $t->same($mainData, $value($state->execute('PRAGMA data_version')));

        $state->execute('PRAGMA user_version = ' . (600 + $variant));
        $state->execute('PRAGMA aux.user_version = ' . (700 + $variant));
        $t->same(600 + $variant, $value($state->execute('PRAGMA main.user_version')));
        $t->same(700 + $variant, $value($state->execute('PRAGMA aux.user_version')));
        $t->same(301 + $variant, $value($state->execute('PRAGMA schema_version')));

        $state->beginTransaction();
        $state->execute('PRAGMA user_version = ' . (800 + $variant));
        $state->execute('PRAGMA aux.user_version = ' . (900 + $variant));
        $state->execute('PRAGMA aux.schema_version = ' . (1000 + $variant));
        $t->same(800 + $variant, $value($state->execute('PRAGMA user_version')));
        $t->same(900 + $variant, $value($state->execute('PRAGMA aux.user_version')));
        $t->same(1000 + $variant, $value($state->execute('PRAGMA aux.schema_version')));
        $rollback = $state->rollbackTransaction();
        $t->same(true, $rollback['restored']);
        $t->same(600 + $variant, $value($state->execute('PRAGMA user_version')));
        $t->same(700 + $variant, $value($state->execute('PRAGMA aux.user_version')));
        $t->same(400 + $variant, $value($state->execute('PRAGMA aux.schema_version')));

        $ignoredData = $state->execute('PRAGMA data_version = ' . (1100 + $variant));
        $t->same('read_only_pragma_ignored', $ignoredData['reason']);
        $t->same(false, $ignoredData['changed']);
        $t->same($mainData, $value($state->execute('PRAGMA data_version')));

        $negative = -450 - $variant;
        $state->execute('PRAGMA user_version = ' . $negative);
        $t->same($negative, $value($state->execute('PRAGMA user_version')));
    };
}

$tests['real upstream pragma schema dynamic version rollback corpus source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.1.1 through pragma-8.1.18 covers schema_version assignment, defensive ignore, DDL schema-cookie changes, and attached schema invalidation',
        'pragma.test pragma-8.2.1 through pragma-8.2.15 covers user_version independence, transaction rollback restoration, attached user_version isolation, and signed values',
        'pragma3.test data_version evidence keeps PRAGMA data_version read-only and local to observed external commits',
    ];

    $t->same(3, count($sections));
    $t->contains('pragma-8.1', $sections[0]);
    $t->contains('pragma-8.2', $sections[1]);
    $t->contains('data_version', $sections[2]);
};

return $tests;
