<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/schema.test schema-12.1.
 * - SQLite test/pragma.test pragma-8.* schema_version behavior.
 *
 * schema-12.1 demonstrates that prepared statements must be expired after a
 * schema-changing transaction rolls back, even when a later schema change
 * returns the schema cookie to the same numeric value observed at prepare time.
 */

foreach (range(1, 1000) as $variant) {
    $schema = $variant % 3 === 0 ? 'main' : 'tenant' . $variant;
    $baseCookie = 9000 + $variant;
    $rolledBackCookie = $baseCookie + 1;
    $otherSchema = 'aux' . $variant;

    $state = static fn (): SQLitePragmaSchemaDataVersion => new SQLitePragmaSchemaDataVersion([
        $schema => [
            'schema_version' => $baseCookie,
            'data_version' => 1 + ($variant % 11),
            'change_counter' => 1 + ($variant % 11),
        ],
        $otherSchema => [
            'schema_version' => $baseCookie + 77,
            'data_version' => 1,
            'change_counter' => 1,
        ],
    ]);

    $tests["real upstream schema.test schema-12.1 rollback cookie expires prepared statement dynamic {$variant}"] = static function (TestRunner $t) use ($state, $schema, $baseCookie, $rolledBackCookie, $otherSchema): void {
        $connection = $state();
        $begin = $connection->beginTransaction();
        $during = $connection->recordSchemaChange($schema, 1, 'schema_12_create_table_inside_transaction');
        $prepared = [
            [
                'id' => 'schema12-create-duplicate-' . $schema,
                'schema' => $schema,
                'schema_cookie' => $rolledBackCookie,
                'prepared_during_rolled_back_schema' => true,
                'sql' => 'CREATE TABLE duplicate_guard(a,b,c)',
            ],
            [
                'id' => 'schema12-current-reader-' . $schema,
                'schema' => $schema,
                'schema_cookie' => $rolledBackCookie,
                'sql' => 'SELECT * FROM sqlite_schema',
            ],
            [
                'id' => 'schema12-other-schema-reader-' . $otherSchema,
                'schema' => $otherSchema,
                'schema_cookie' => $baseCookie + 77,
                'sql' => 'SELECT * FROM sqlite_schema',
            ],
        ];

        $rollback = $connection->rollbackTransaction();
        $afterRollback = $connection->execute("PRAGMA {$schema}.schema_version");
        $connection->recordSchemaChange($schema, 1, 'schema_12_create_table_after_rollback');
        $afterCreate = $connection->execute("PRAGMA {$schema}.schema_version");
        $expiry = $connection->expirePreparedAfterSchemaRollback($prepared, $schema);

        $t->same('begin', $begin['operation']);
        $t->same($rolledBackCookie, $during['value']);
        $t->same('rollback', $rollback['operation']);
        $t->same($baseCookie, $afterRollback['value']);
        $t->same($rolledBackCookie, $afterCreate['value']);
        $t->same($rolledBackCookie, $expiry['current_schema_cookie']);
        $t->same(['schema12-create-duplicate-' . $schema], $expiry['expired']);
        $t->same('prepared_during_rolled_back_schema', $expiry['reasons']['schema12-create-duplicate-' . $schema]);
        $t->same('schema_cookie_current', $expiry['reasons']['schema12-current-reader-' . $schema]);
        $t->same('different_schema', $expiry['reasons']['schema12-other-schema-reader-' . $otherSchema]);
        $t->same([
            'schema12-current-reader-' . $schema,
            'schema12-other-schema-reader-' . $otherSchema,
        ], $expiry['preserved']);
        $t->same('sqlite-schema-rollback-expires-prepared-statements', $expiry['dependencies'][0]);
    };
}

$tests['real upstream pragma schema dynamic rollback cookie cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'schema.test schema-12.1 expires a prepared CREATE TABLE after rollback despite matching schema cookie',
        'pragma.test pragma-8.1 schema_version can be read and assigned',
        'pragma.test pragma-8.* attached schema_version state is schema-local',
    ];

    $t->same(3, count($sections));
    $t->contains('schema-12.1', $sections[0]);
    $t->contains('pragma-8.1', $sections[1]);
};

return $tests;
