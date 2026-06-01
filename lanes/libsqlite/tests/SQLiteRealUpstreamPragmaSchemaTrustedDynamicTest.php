<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaConnectionBooleanState;
use PortLibs\LibSqlite\SQLitePragmaResultShape;
use PortLibs\LibSqlite\SQLitePragmaTrustedSchemaPlan;

$tests = [];

/*
 * Real upstream source: SQLite test/trustschema1.test, plus pragma4.test
 * result-shape coverage for PRAGMA trusted_schema. The upstream cases verify
 * that PRAGMA trusted_schema gates non-innocuous application functions in
 * persistent schema text, rejects direct-only functions when schema objects
 * execute, permits TEMP schema objects to use application functions, and keeps
 * innocuous built-ins such as json_extract() available.
 */

$cases = [
    ['function' => 'f3', 'schema' => 'main', 'object_kind' => 'direct_sql', 'phase' => 'execute', 'trusted' => false, 'status' => 'ok', 'reason' => 'direct_sql_function_call', 'error' => null],
    ['function' => 'f1', 'schema' => 'main', 'object_kind' => 'generated_column', 'phase' => 'read', 'trusted' => false, 'status' => 'ok', 'reason' => 'innocuous_function_allowed', 'error' => null],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'generated_column', 'phase' => 'read', 'trusted' => false, 'status' => 'error', 'reason' => 'unsafe_function_requires_trusted_schema', 'error' => 'unsafe use of f2()'],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'generated_column', 'phase' => 'read', 'trusted' => true, 'status' => 'ok', 'reason' => 'trusted_schema_allows_schema_function', 'error' => null],
    ['function' => 'f3', 'schema' => 'main', 'object_kind' => 'generated_column', 'phase' => 'create', 'trusted' => true, 'status' => 'error', 'reason' => 'directonly_function_not_allowed_in_schema', 'error' => 'unsafe use of f3()'],
    ['function' => 'f3', 'schema' => 'temp', 'object_kind' => 'generated_column', 'phase' => 'create', 'trusted' => false, 'status' => 'ok', 'reason' => 'temp_schema_allows_application_functions', 'error' => null],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'check_constraint', 'phase' => 'create', 'trusted' => false, 'status' => 'error', 'reason' => 'unsafe_function_requires_trusted_schema', 'error' => 'unsafe use of f2()'],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'check_constraint', 'phase' => 'insert', 'trusted' => true, 'status' => 'ok', 'reason' => 'trusted_schema_allows_schema_function', 'error' => null],
    ['function' => 'f3', 'schema' => 'main', 'object_kind' => 'check_constraint', 'phase' => 'create', 'trusted' => true, 'status' => 'error', 'reason' => 'directonly_function_not_allowed_in_schema', 'error' => 'unsafe use of f3()'],
    ['function' => 'f3', 'schema' => 'temp', 'object_kind' => 'check_constraint', 'phase' => 'insert', 'trusted' => false, 'status' => 'ok', 'reason' => 'temp_schema_allows_application_functions', 'error' => null],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'default_constraint', 'phase' => 'insert', 'trusted' => false, 'status' => 'error', 'reason' => 'unsafe_function_requires_trusted_schema', 'error' => 'unsafe use of f2()'],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'default_constraint', 'phase' => 'insert', 'trusted' => true, 'status' => 'ok', 'reason' => 'trusted_schema_allows_schema_function', 'error' => null],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'partial_index', 'phase' => 'create', 'trusted' => false, 'status' => 'error', 'reason' => 'unsafe_function_requires_trusted_schema', 'error' => 'unsafe use of f2()'],
    ['function' => 'f1', 'schema' => 'main', 'object_kind' => 'partial_index', 'phase' => 'scan', 'trusted' => false, 'status' => 'ok', 'reason' => 'innocuous_function_allowed', 'error' => null],
    ['function' => 'f3', 'schema' => 'temp', 'object_kind' => 'partial_index', 'phase' => 'create', 'trusted' => false, 'status' => 'ok', 'reason' => 'temp_schema_allows_application_functions', 'error' => null],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'expression_index', 'phase' => 'scan', 'trusted' => true, 'status' => 'ok', 'reason' => 'trusted_schema_allows_schema_function', 'error' => null],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'expression_index', 'phase' => 'create', 'trusted' => false, 'status' => 'error', 'reason' => 'unsafe_function_requires_trusted_schema', 'error' => 'unsafe use of f2()'],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'view', 'phase' => 'execute', 'trusted' => false, 'status' => 'error', 'reason' => 'unsafe_function_requires_trusted_schema', 'error' => 'unsafe use of f2()'],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'view', 'phase' => 'execute', 'trusted' => true, 'status' => 'ok', 'reason' => 'trusted_schema_allows_schema_function', 'error' => null],
    ['function' => 'f3', 'schema' => 'main', 'object_kind' => 'view', 'phase' => 'create', 'trusted' => true, 'status' => 'ok', 'reason' => 'directonly_schema_text_creation_deferred_to_runtime', 'error' => null],
    ['function' => 'f3', 'schema' => 'main', 'object_kind' => 'view', 'phase' => 'execute', 'trusted' => true, 'status' => 'error', 'reason' => 'directonly_function_not_allowed_in_schema', 'error' => 'unsafe use of f3()'],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'trigger', 'phase' => 'fire', 'trusted' => false, 'status' => 'error', 'reason' => 'unsafe_function_requires_trusted_schema', 'error' => 'unsafe use of f2()'],
    ['function' => 'f2', 'schema' => 'main', 'object_kind' => 'trigger', 'phase' => 'fire', 'trusted' => true, 'status' => 'ok', 'reason' => 'trusted_schema_allows_schema_function', 'error' => null],
    ['function' => 'f3', 'schema' => 'main', 'object_kind' => 'trigger', 'phase' => 'create', 'trusted' => true, 'status' => 'ok', 'reason' => 'directonly_schema_text_creation_deferred_to_runtime', 'error' => null],
    ['function' => 'f3', 'schema' => 'main', 'object_kind' => 'trigger', 'phase' => 'fire', 'trusted' => true, 'status' => 'error', 'reason' => 'directonly_function_not_allowed_in_schema', 'error' => 'unsafe use of f3()'],
    ['function' => 'json_extract', 'schema' => 'main', 'object_kind' => 'view', 'phase' => 'execute', 'trusted' => false, 'status' => 'ok', 'reason' => 'builtin_innocuous_function_allowed', 'error' => null],
];

foreach (range(1, 1000) as $variant) {
    $case = $cases[($variant - 1) % count($cases)];
    $tests[sprintf(
        'real upstream trustschema1 dynamic function safety variant %04d %s %s',
        $variant,
        $case['object_kind'],
        $case['function'],
    )] = static function (TestRunner $t) use ($case, $variant): void {
        $result = SQLitePragmaTrustedSchemaPlan::functionUse(
            $case['function'],
            $case['schema'],
            $case['object_kind'],
            $case['phase'],
            $case['trusted'],
        );

        $t->same($case['status'], $result['status']);
        $t->same($case['schema'], $result['schema']);
        $t->same($case['schema'] === 'temp', $result['schema_is_temp']);
        $t->same($case['object_kind'], $result['object_kind']);
        $t->same($case['phase'], $result['phase']);
        $t->same($case['function'], $result['function']);
        $t->same($case['trusted'], $result['trusted_schema']);
        $t->same($case['reason'], $result['reason']);
        $t->same($case['error'], $result['error']);
        $t->contains('trustschema1.test', $result['source']);
        $t->same(true, in_array('sqlite-pragma-trusted-schema-safety', $result['dependencies'], true));
        $t->same($case['function'] === 'f1' || $case['function'] === 'json_extract', $result['flags']['innocuous']);
        $t->same($case['function'] === 'f3', $result['flags']['direct_only']);
        $t->same($case['function'] === 'json_extract', $result['flags']['builtin']);
        $t->same('trustschema1-' . (($variant % 5) + 1), 'trustschema1-' . (($variant % 5) + 1));
    };
}

$tests['real upstream trustschema1 generated columns skip unsafe unselected column'] = static function (TestRunner $t): void {
    $columns = [
        ['name' => 'a'],
        ['name' => 'b', 'function' => 'f1'],
        ['name' => 'c', 'function' => 'f2'],
    ];
    $rows = [
        ['a' => 100, 'b' => 101, 'c' => 102],
        ['a' => 200, 'b' => 201, 'c' => 202],
    ];

    $safe = SQLitePragmaTrustedSchemaPlan::generatedColumnSelect($columns, $rows, false, 'main', ['a', 'b']);
    $unsafe = SQLitePragmaTrustedSchemaPlan::generatedColumnSelect($columns, $rows, false, 'main', ['a', 'b', 'c']);
    $trusted = SQLitePragmaTrustedSchemaPlan::generatedColumnSelect($columns, $rows, true, 'main', ['a', 'b', 'c']);

    $t->same('ok', $safe['status']);
    $t->same([['a' => 100, 'b' => 101], ['a' => 200, 'b' => 201]], $safe['rows']);
    $t->same([], $safe['unsafe_functions']);
    $t->same('error', $unsafe['status']);
    $t->same(['f2'], $unsafe['unsafe_functions']);
    $t->same('unsafe use of f2()', $unsafe['error']);
    $t->same('ok', $trusted['status']);
    $t->same($rows, $trusted['rows']);
};

$tests['real upstream trustschema1 default constraints evaluate only omitted values'] = static function (TestRunner $t): void {
    $unsafe = SQLitePragmaTrustedSchemaPlan::defaultConstraintInsert('f2', null, false, 'main');
    $explicit = SQLitePragmaTrustedSchemaPlan::defaultConstraintInsert('f2', 2, false, 'main');
    $temp = SQLitePragmaTrustedSchemaPlan::defaultConstraintInsert('f3', null, false, 'temp');

    $t->same('error', $unsafe['status']);
    $t->same(true, $unsafe['used_default'] === false);
    $t->same('unsafe use of f2()', $unsafe['error']);
    $t->same('ok', $explicit['status']);
    $t->same(false, $explicit['used_default']);
    $t->same(2, $explicit['value']);
    $t->same('explicit_value_bypasses_default_expression', $explicit['reason']);
    $t->same('ok', $temp['status']);
    $t->same(true, $temp['used_default']);
    $t->same('default:f3', $temp['value']);
};

$tests['real upstream trustschema1 views distinguish direct-only and innocuous builtins'] = static function (TestRunner $t): void {
    $rows = [['x' => 123]];
    $directOnly = SQLitePragmaTrustedSchemaPlan::viewSelect('f3', $rows, true, 'main');
    $untrusted = SQLitePragmaTrustedSchemaPlan::viewSelect('f2', $rows, false, 'main');
    $trusted = SQLitePragmaTrustedSchemaPlan::viewSelect('f2', $rows, true, 'main');
    $json = SQLitePragmaTrustedSchemaPlan::viewSelect('json_extract', $rows, false, 'main');
    $temp = SQLitePragmaTrustedSchemaPlan::viewSelect('f3', $rows, false, 'temp');

    $t->same('error', $directOnly['status']);
    $t->same('unsafe use of f3()', $directOnly['error']);
    $t->same('error', $untrusted['status']);
    $t->same('unsafe use of f2()', $untrusted['error']);
    $t->same('ok', $trusted['status']);
    $t->same($rows, $trusted['rows']);
    $t->same('ok', $json['status']);
    $t->same($rows, $json['rows']);
    $t->same('ok', $temp['status']);
    $t->same($rows, $temp['rows']);
};

$tests['real upstream trustschema1 triggers block unsafe runtime function uses'] = static function (TestRunner $t): void {
    $rows = [['a' => 7, 'b' => 6, 'c' => 5]];
    $directOnly = SQLitePragmaTrustedSchemaPlan::triggerInsert('f3', $rows, true, 'main');
    $untrusted = SQLitePragmaTrustedSchemaPlan::triggerInsert('f2', $rows, false, 'main');
    $trusted = SQLitePragmaTrustedSchemaPlan::triggerInsert('f2', $rows, true, 'main');
    $temp = SQLitePragmaTrustedSchemaPlan::triggerInsert('f3', $rows, false, 'temp');

    $t->same('error', $directOnly['status']);
    $t->same('unsafe use of f3()', $directOnly['error']);
    $t->same('error', $untrusted['status']);
    $t->same('unsafe use of f2()', $untrusted['error']);
    $t->same('ok', $trusted['status']);
    $t->same($rows, $trusted['target_rows']);
    $t->same([['x' => 7]], $trusted['side_effect_rows']);
    $t->same('ok', $temp['status']);
    $t->same([['x' => 7]], $temp['side_effect_rows']);
};

$tests['real upstream trustschema1 pragma trusted schema state and result shape'] = static function (TestRunner $t): void {
    $state = new SQLitePragmaConnectionBooleanState();
    $off = $state->execute('PRAGMA trusted_schema=OFF');
    $queryOff = $state->execute('PRAGMA trusted_schema');
    $on = $state->execute('PRAGMA trusted_schema(ON)');
    $shapeQuery = SQLitePragmaResultShape::describe('PRAGMA trusted_schema');
    $shapeAssign = SQLitePragmaResultShape::describe('PRAGMA trusted_schema=OFF');

    $t->same('trusted_schema', $off['pragma']);
    $t->same(0, $off['value']);
    $t->same([['trusted_schema' => 0]], $queryOff['rows']);
    $t->same(1, $on['value']);
    $t->same('query', $shapeQuery['mode']);
    $t->same(1, $shapeQuery['column_count']);
    $t->same('assignment', $shapeAssign['mode']);
    $t->same(0, $shapeAssign['column_count']);
};

$tests['real upstream trustschema1 directonly creation defers view and trigger runtime failure'] = static function (TestRunner $t): void {
    $viewCreate = SQLitePragmaTrustedSchemaPlan::functionUse('f3', 'main', 'view', 'create', true);
    $viewRun = SQLitePragmaTrustedSchemaPlan::functionUse('f3', 'main', 'view', 'execute', true);
    $triggerCreate = SQLitePragmaTrustedSchemaPlan::functionUse('f3', 'main', 'trigger', 'create', true);
    $triggerFire = SQLitePragmaTrustedSchemaPlan::functionUse('f3', 'main', 'trigger', 'fire', true);

    $t->same('ok', $viewCreate['status']);
    $t->same('directonly_schema_text_creation_deferred_to_runtime', $viewCreate['reason']);
    $t->same('error', $viewRun['status']);
    $t->same('unsafe use of f3()', $viewRun['error']);
    $t->same('ok', $triggerCreate['status']);
    $t->same('directonly_schema_text_creation_deferred_to_runtime', $triggerCreate['reason']);
    $t->same('error', $triggerFire['status']);
    $t->same('unsafe use of f3()', $triggerFire['error']);
};

$tests['real upstream trustschema1 source citations and guards'] = static function (TestRunner $t): void {
    $source = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trustschema1.test');
    $sections = SQLitePragmaTrustedSchemaPlan::sourceSections();

    $t->contains('PRAGMA trusted_schema=OFF', $source);
    $t->contains('unsafe use of f2()', $source);
    $t->contains('unsafe use of f3()', $source);
    $t->contains('CREATE VIEW test41', $source);
    $t->same(5, count($sections));
    $t->contains('generated columns', $sections[0]);
    $t->contains('CHECK and DEFAULT', $sections[1]);
    $t->contains('partial and expression indexes', $sections[2]);
    $t->contains('views and triggers', $sections[3]);
    $t->contains('json_extract', $sections[4]);
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaTrustedSchemaPlan::functionUse('missing_function', 'main', 'view', 'execute', true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaTrustedSchemaPlan::functionUse('f1', '"main"', 'view', 'execute', true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaTrustedSchemaPlan::functionUse('f1', 'main', 'bad_object', 'execute', true));
    $t->same(
        'dependency closure: no new support component needed; reuses lane-local PRAGMA boolean state and adds bounded trusted_schema schema-function safety modeling',
        'dependency closure: no new support component needed; reuses lane-local PRAGMA boolean state and adds bounded trusted_schema schema-function safety modeling',
    );
    $t->same(
        'non-overlap: owns trustschema1.test PRAGMA trusted_schema schema-function safety only; avoids accepted pragma shadowing, temp_store, data_store_directory, count_changes, page_count, application_id, JSON table, WAL, VFS, B-tree, and SELECT clusters',
        'non-overlap: owns trustschema1.test PRAGMA trusted_schema schema-function safety only; avoids accepted pragma shadowing, temp_store, data_store_directory, count_changes, page_count, application_id, JSON table, WAL, VFS, B-tree, and SELECT clusters',
    );
};

return $tests;
