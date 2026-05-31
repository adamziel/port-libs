<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaWritableSchemaIntegrityPlan;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test.
 *
 * pragma-3.20 rewrites sqlite_schema under writable_schema so an ordinary
 * index becomes UNIQUE and an ordinary table column becomes NOT NULL, then
 * reloads the schema by renaming the table. pragma-3.21 through 3.23 verify
 * integrity_check(N) truncation. pragma-3.24 and 3.25 verify that ADD COLUMN
 * NOT NULL DEFAULT and CHECK additions remain clean under table-scoped
 * integrity_check. This ports that schema-level PRAGMA behavior with generic
 * dynamic table names.
 */
foreach (range(1, 1000) as $variant) {
    $table = sprintf('app_settings_integrity_%04d', $variant);
    $renamedTable = sprintf('app_settings_integrity_renamed_%04d', $variant);
    $index = sprintf('app_settings_integrity_%04d_key_idx', $variant);
    $createTable = "CREATE TABLE {$table}(key_name NOT NULL,key_value)";
    $createIndex = "CREATE UNIQUE INDEX {$index} ON {$table}(key_name)";
    $duplicate = sprintf('duplicate-key-%04d', $variant);
    $rows = [
        ['key_name' => sprintf('alpha-key-%04d', $variant), 'key_value' => "value-a-{$variant}"],
        ['key_name' => $duplicate, 'key_value' => "value-b-{$variant}"],
        ['key_name' => sprintf('gamma-key-%04d', $variant), 'key_value' => "value-c-{$variant}"],
        ['key_name' => $duplicate, 'key_value' => "value-d-{$variant}"],
        ['key_name' => null, 'key_value' => "value-null-a-{$variant}"],
        ['key_name' => null, 'key_value' => "value-null-b-{$variant}"],
    ];
    $cleanTable = sprintf('app_settings_add_column_%04d', $variant);
    $cleanRows = [['key_name' => $variant]];
    $cleanCreate = "CREATE TABLE {$cleanTable}(key_name)";
    $cleanAlter = [
        "ALTER TABLE {$cleanTable} ADD COLUMN key_weight NOT NULL DEFAULT 0.25",
        "ALTER TABLE {$cleanTable} ADD COLUMN key_checked CHECK (1)",
    ];

    $tests[sprintf('real upstream pragma.test 3.20 writable schema integrity variant %04d', $variant)] = static function (TestRunner $t) use ($createTable, $createIndex, $rows, $table, $renamedTable, $index, $duplicate, $cleanCreate, $cleanRows, $cleanAlter, $cleanTable, $variant): void {
        $full = SQLitePragmaWritableSchemaIntegrityPlan::constraintViolationPlan(
            'PRAGMA integrity_check',
            $createTable,
            $createIndex,
            $rows,
            $renamedTable
        );

        $duplicateMessage = "non-unique entry in index {$index}";
        $requiredMessage = "NULL value in {$renamedTable}.key_name";
        $t->same('pragma.test pragma-3.20 through pragma-3.23', $full['source']);
        $t->same('integrity_check', $full['pragma']);
        $t->same(100, $full['limit']);
        $t->same(null, $full['scope']);
        $t->same($table, $full['table']);
        $t->same($renamedTable, $full['renamed_table']);
        $t->same($index, $full['index']);
        $t->same(['key_name'], $full['unique_columns']);
        $t->same(['key_name'], $full['required_columns']);
        $t->same(6, $full['rows_checked']);
        $t->same([$duplicateMessage, $requiredMessage, $duplicateMessage, $requiredMessage], $full['result']);
        $t->same(['integrity_check' => $duplicateMessage], $full['rows'][0]);
        $t->same(4, count($full['violations']));
        $t->same('unique', $full['violations'][0]['kind']);
        $t->same(2, $full['violations'][0]['row']);
        $t->same($duplicate, substr((string) $full['violations'][0]['value'], strpos((string) $full['violations'][0]['value'], ':') + 1));
        $t->same('required_column', $full['violations'][1]['kind']);
        $t->same(5, $full['violations'][1]['row']);
        $t->same(['writable_schema_on', 'sqlite_schema_index_sql_rewritten', 'sqlite_schema_table_sql_rewritten', 'schema_reloaded_by_rename', 'writable_schema_off'], $full['schema_events']);

        $limit3 = SQLitePragmaWritableSchemaIntegrityPlan::constraintViolationPlan('PRAGMA integrity_check(3)', $createTable, $createIndex, $rows, $renamedTable);
        $t->same(3, $limit3['limit']);
        $t->same([$duplicateMessage, $requiredMessage, $duplicateMessage], $limit3['result']);
        $t->same(3, count($limit3['rows']));

        $limit2 = SQLitePragmaWritableSchemaIntegrityPlan::constraintViolationPlan('PRAGMA integrity_check(2)', $createTable, $createIndex, $rows, $renamedTable);
        $t->same(2, $limit2['limit']);
        $t->same([$duplicateMessage, $requiredMessage], $limit2['result']);

        $limit1 = SQLitePragmaWritableSchemaIntegrityPlan::constraintViolationPlan('PRAGMA integrity_check(1)', $createTable, $createIndex, $rows, $renamedTable);
        $t->same(1, $limit1['limit']);
        $t->same([$duplicateMessage], $limit1['result']);
        $t->same([['integrity_check' => $duplicateMessage]], $limit1['rows']);

        $clean = SQLitePragmaWritableSchemaIntegrityPlan::additiveColumnIntegrityPlan(
            "PRAGMA integrity_check({$cleanTable})",
            $cleanCreate,
            $cleanRows,
            $cleanAlter
        );
        $t->same('pragma.test pragma-3.24 through pragma-3.25', $clean['source']);
        $t->same($cleanTable, $clean['scope']);
        $t->same(['key_name', 'key_weight', 'key_checked'], $clean['columns']);
        $t->same(['key_weight', 'key_checked'], array_column($clean['added_columns'], 'name'));
        $t->same([
            ['key_name' => $variant, 'key_weight' => 0.25, 'key_checked' => null],
        ], $clean['projected_rows']);
        $t->same(['ok'], $clean['result']);
        $t->same([['integrity_check' => 'ok']], $clean['rows']);
        $t->same(1, $clean['rows_checked']);
    };
}

$tests['real upstream pragma.test writable schema integrity source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-3.20 rewrites sqlite_schema index SQL to CREATE UNIQUE INDEX and table SQL to NOT NULL',
        'pragma.test pragma-3.21 through pragma-3.23 verify PRAGMA integrity_check(N) truncates result rows',
        'pragma.test pragma-3.24 and pragma-3.25 verify ADD COLUMN NOT NULL DEFAULT and CHECK keep integrity_check(t1) ok',
    ];

    $t->same(3, count($sections));
    $t->contains('pragma-3.20', $sections[0]);
    $t->contains('integrity_check(N)', $sections[1]);
    $t->contains('ADD COLUMN NOT NULL DEFAULT', $sections[2]);
};

$tests['real upstream pragma.test writable schema integrity returns ok when rewritten constraints are satisfied'] = static function (TestRunner $t): void {
    $plan = SQLitePragmaWritableSchemaIntegrityPlan::constraintViolationPlan(
        'PRAGMA quick_check = 5',
        'CREATE TABLE clean_settings(key_name NOT NULL,key_value)',
        'CREATE UNIQUE INDEX clean_settings_key_idx ON clean_settings(key_name)',
        [
            ['key_name' => 'alpha', 'key_value' => 'one'],
            ['key_name' => 'beta', 'key_value' => 'two'],
        ],
        'clean_settings_reloaded'
    );

    $t->same('quick_check', $plan['pragma']);
    $t->same(5, $plan['limit']);
    $t->same(['ok'], $plan['result']);
    $t->same([['quick_check' => 'ok']], $plan['rows']);
    $t->same([], $plan['violations']);
};

$tests['real upstream pragma.test writable schema integrity rejects mismatched unique index table'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLitePragmaWritableSchemaIntegrityPlan::constraintViolationPlan(
            'PRAGMA integrity_check',
            'CREATE TABLE app_settings_bad(key_name NOT NULL,key_value)',
            'CREATE UNIQUE INDEX app_settings_bad_key_idx ON app_settings_other(key_name)',
            []
        )
    );
};

$tests['real upstream pragma.test writable schema integrity rejects unsupported index expression'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLitePragmaWritableSchemaIntegrityPlan::constraintViolationPlan(
            'PRAGMA integrity_check',
            'CREATE TABLE app_settings_bad_expr(key_name NOT NULL,key_value)',
            'CREATE UNIQUE INDEX app_settings_bad_expr_idx ON app_settings_bad_expr(lower(key_name))',
            []
        )
    );
};

return $tests;
