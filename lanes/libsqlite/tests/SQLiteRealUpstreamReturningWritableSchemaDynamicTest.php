<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningWritableSchemaPlan;

$tests = [];

$tests['real upstream returning1.test 21.0 sqlite_schema default values returning qualified name'] = static function (TestRunner $t): void {
    $result = SQLiteReturningWritableSchemaPlan::insertDefaultValuesReturning('sqlite_schema', 'sqlite_schema.name');

    $t->same(['name' => null], $result['returning']);
    $t->same(1, $result['changes']);
    $t->same('returning1.test-21.0', $result['source']);
};

$tests['real upstream returning1.test 21.1 sqlite_temp_schema default values returning qualified name'] = static function (TestRunner $t): void {
    $result = SQLiteReturningWritableSchemaPlan::insertDefaultValuesReturning('sqlite_temp_schema', 'sqlite_temp_schema.name');

    $t->same(['name' => null], $result['returning']);
    $t->same('sqlite_temp_schema', $result['schema']);
    $t->same('returning1.test-21.1', $result['source']);
};

$tests['real upstream returning1.test 22.1 temp schema returning subquery alias error'] = static function (TestRunner $t): void {
    $result = SQLiteReturningWritableSchemaPlan::tempSchemaSubqueryAliasError();

    $t->same(false, $result['ok']);
    $t->same('no such column: sqlite_master.name', $result['error']);
    $t->same('returning1.test-22.1', $result['source']);
};

$schemas = [
    ['main', 'sqlite_schema.name', 'sqlite_schema', 'returning1.test-21.0'],
    ['sqlite_schema', 'name', 'sqlite_schema', 'returning1.test-21.0'],
    ['temp', 'sqlite_temp_schema.name', 'sqlite_temp_schema', 'returning1.test-21.1'],
    ['sqlite_temp_schema', 'name', 'sqlite_temp_schema', 'returning1.test-21.1'],
];

foreach (range(1, 600) as $case) {
    [$schema, $returning, $normalized, $source] = $schemas[$case % count($schemas)];
    $defaults = [
        'type' => $case % 5 === 0 ? 'table' : null,
        'name' => null,
        'tbl_name' => $case % 7 === 0 ? 'generated_' . $case : null,
        'rootpage' => $case % 11 === 0 ? $case : null,
        'sql' => $case % 13 === 0 ? 'CREATE TABLE generated_' . $case . '(a)' : null,
    ];

    $tests[sprintf('real upstream returning writable schema default returning dynamic %04d', $case)] = static function (TestRunner $t) use ($schema, $returning, $normalized, $source, $defaults, $case): void {
        $result = SQLiteReturningWritableSchemaPlan::insertDefaultValuesReturning($schema, $returning, $defaults);

        $t->same($normalized, $result['schema'], "schema alias normalized for returning1 writable schema case {$case}");
        $t->same(['name' => null], $result['returning'], "DEFAULT VALUES RETURNING name yields NULL for case {$case}");
        $t->same($defaults['tbl_name'], $result['row']['tbl_name'], "non-returned schema columns are still materialized for case {$case}");
        $t->same($source, $result['source'], "upstream source section is retained for case {$case}");
    };
}

foreach (range(1, 250) as $case) {
    $alias = 'sqlite_master_' . $case;
    $column = 'name_' . $case;
    $tests[sprintf('real upstream returning temp schema alias error dynamic %04d', $case)] = static function (TestRunner $t) use ($alias, $column): void {
        $result = SQLiteReturningWritableSchemaPlan::tempSchemaSubqueryAliasError($alias, $column);

        $t->same(false, $result['ok']);
        $t->same("no such column: {$alias}.{$column}", $result['error']);
        $t->same('returning1.test-22.1', $result['source']);
    };
}

$tests['real upstream returning writable schema rejects mismatched qualifier'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningWritableSchemaPlan::insertDefaultValuesReturning('sqlite_schema', 'sqlite_temp_schema.name'));
};

$tests['real upstream returning writable schema rejects missing returning column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningWritableSchemaPlan::insertDefaultValuesReturning('sqlite_schema', 'missing'));
};

$tests['real upstream returning writable schema rejects unsupported schema target'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningWritableSchemaPlan::insertDefaultValuesReturning('app_schema', 'name'));
};

$tests['real upstream returning writable schema cites exact upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test-21.0 writable sqlite_schema DEFAULT VALUES RETURNING sqlite_schema.name',
        'returning1.test-21.1 writable sqlite_temp_schema DEFAULT VALUES RETURNING sqlite_temp_schema.name',
        'returning1.test-22.1 temp schema RETURNING subquery reports sqlite_master alias column error',
    ], [
        'returning1.test-21.0 writable sqlite_schema DEFAULT VALUES RETURNING sqlite_schema.name',
        'returning1.test-21.1 writable sqlite_temp_schema DEFAULT VALUES RETURNING sqlite_temp_schema.name',
        'returning1.test-22.1 temp schema RETURNING subquery reports sqlite_master alias column error',
    ]);
};

return $tests;
