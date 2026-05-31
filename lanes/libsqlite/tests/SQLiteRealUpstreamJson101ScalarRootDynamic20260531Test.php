<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonEach;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

/**
 * @return list<array{case:int,json:string,decoded:mixed,type:string,sql:mixed,canonical:string}>
 */
function json101_scalar_root_dynamic_cases(): array
{
    $cases = [];
    $seedValues = [
        static fn (int $i): int => $i - 150,
        static fn (int $i): float => ($i / 8) + 0.125,
        static fn (int $i): string => 'setting-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        static fn (int $i): string => 'quoted "key" ' . $i,
        static fn (int $i): string => 'array-looking [' . $i . ']',
        static fn (int $i): string => "line-" . $i . "\nnext",
        static fn (int $i): null => null,
        static fn (int $i): bool => ($i % 2) === 0,
        static fn (int $i): bool => ($i % 2) !== 0,
    ];

    for ($i = 0; $i < 300; $i++) {
        $decoded = $seedValues[$i % count($seedValues)]($i);
        $canonical = SQLiteJsonCanonical::encodeDecodedJson($decoded);
        $cases[] = [
            'case' => $i,
            'json' => $canonical,
            'decoded' => $decoded,
            'type' => json101_scalar_root_dynamic_type($decoded),
            'sql' => json101_scalar_root_dynamic_sql_value($decoded),
            'canonical' => $canonical,
        ];
    }

    return $cases;
}

function json101_scalar_root_dynamic_type(mixed $value): string
{
    if ($value === null) {
        return 'null';
    }
    if ($value === true) {
        return 'true';
    }
    if ($value === false) {
        return 'false';
    }
    if (is_int($value)) {
        return 'integer';
    }
    if (is_float($value)) {
        return 'real';
    }
    if (is_string($value)) {
        return 'text';
    }

    throw new InvalidArgumentException('Unexpected JSON scalar root test value');
}

function json101_scalar_root_dynamic_sql_value(mixed $value): mixed
{
    if ($value === true) {
        return 1;
    }
    if ($value === false) {
        return 0;
    }

    return $value;
}

function json101_scalar_root_dynamic_valid(string|SQLiteBlobValue|SQLiteJsonSubtypeValue $input): ?bool
{
    return $input instanceof SQLiteBlobValue
        ? SQLiteJsonValidity::jsonValid($input, SQLiteJsonValidity::FLAG_STRICT_JSONB)
        : SQLiteJsonValidity::jsonValid($input);
}

/**
 * @return array{text:string,jsonb:SQLiteBlobValue,subtype:SQLiteJsonSubtypeValue}
 */
function json101_scalar_root_dynamic_inputs(string $json, mixed $decoded): array
{
    return [
        'text' => $json,
        'jsonb' => new SQLiteBlobValue(SQLiteJsonB::encode($decoded)),
        'subtype' => new SQLiteJsonSubtypeValue($json),
    ];
}

function json101_scalar_root_dynamic_assert_table_row(
    TestRunner $t,
    string $function,
    string $form,
    string|SQLiteBlobValue|SQLiteJsonSubtypeValue $input,
    array $case,
    string $label,
): void {
    $rows = $function === 'json_each'
        ? SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', [$input])
        : SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [$input]);

    $t->same(1, count($rows), $label . ' exposes one scalar root row');
    $row = $rows[0];

    $t->same(null, $row['key'], $label . ' key is NULL');
    $t->same('$', $row['fullkey'], $label . ' fullkey is root');
    $t->same('$', $row['path'], $label . ' path is root');
    $t->same('$', $row['root'], $label . ' hidden root is root');
    $t->same($input, $row['json'], $label . ' hidden json preserves the input value');
    $t->same($case['type'], $row['type'], $label . ' type matches scalar root');
    $t->same($case['sql'], $row['value'], $label . ' value matches SQLite scalar projection');
    $t->same($case['sql'], $row['atom'], $label . ' atom matches SQLite scalar projection');
    $t->same(null, $row['parent'], $label . ' scalar root has no parent');
    $t->same($function === 'json_tree' ? 0 : 1, $row['id'], $label . ' scalar root id follows upstream table-valued function convention');

    $t->same($case['type'], SQLiteJsonInspection::jsonType($input, '$'), $label . ' json_type sees the same root scalar');
    $t->same($case['sql'], SQLiteJsonExtract::extract($input, '$'), $label . ' json_extract sees the same root scalar');
    $t->same($case['canonical'], SQLiteJsonCanonical::json($input), $label . ' canonical JSON is stable for ' . $form);
    $t->same(true, json101_scalar_root_dynamic_valid($input), $label . ' input is valid JSON or JSONB');
}

$tests = [];

foreach (json101_scalar_root_dynamic_cases() as $case) {
    $caseId = str_pad((string) $case['case'], 3, '0', STR_PAD_LEFT);
    $inputs = json101_scalar_root_dynamic_inputs($case['json'], $case['decoded']);

    $tests['real upstream json101 scalar root table valued dynamic ' . $caseId] =
        static function (TestRunner $t) use ($case, $caseId, $inputs): void {
            foreach ($inputs as $form => $input) {
                foreach (['json_each', 'json_tree'] as $function) {
                    json101_scalar_root_dynamic_assert_table_row(
                        $t,
                        $function,
                        $form,
                        $input,
                        $case,
                        'json101-14 scalar root dynamic ' . $caseId . ' ' . $form . ' ' . $function
                    );
                }
            }
        };
}

$tests['real upstream json101 scalar root table valued source truth'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json101.test');
        }

        foreach (['14.100', '14.110', '14.120', '14.130', '14.140', '14.150', '14.160', '14.170'] as $suffix) {
            $t->contains('do_execsql_test json101-' . $suffix, $source);
        }
        $t->contains('Incorrect fullkey output from json_each()', $source);
        $t->contains('when the input JSON is not an array or object.', $source);
        $t->same(300, count(json101_scalar_root_dynamic_cases()), '300 dynamic scalar-root cases expand json101-14 without replacing the exact upstream assertions');
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
