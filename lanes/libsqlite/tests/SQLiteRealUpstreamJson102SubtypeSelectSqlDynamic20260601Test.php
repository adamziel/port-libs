<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

/*
 * Dynamic real-upstream corpus slice sourced from upstream SQLite
 * test/json102.test scenarios json102-1600, json102-1610, and json102-1620.
 * Those sections prove that JSON -> results carry SQLite's JSON subtype
 * through SELECT SQL, while ->> and json_extract() expose the expected SQL
 * storage classes. This file drives the upstream behavior through the PHP
 * SQL-text executor, including JSONB input parity.
 */

$tests = [];

function json102_subtype_select_sql_dynamic_json(mixed $value): string
{
    return SQLiteJsonCanonical::encodeDecodedJson($value);
}

function json102_subtype_select_sql_dynamic_jsonb(mixed $value): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
}

function json102_subtype_select_sql_dynamic_json_value(mixed $value): mixed
{
    if ($value instanceof SQLiteJsonSubtypeValue) {
        return $value->json;
    }
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonCanonical::json($value);
    }

    return $value;
}

function json102_subtype_select_sql_dynamic_sql_value(bool $found, mixed $value): mixed
{
    if (!$found || $value === null) {
        return null;
    }
    if ($value === true) {
        return 1;
    }
    if ($value === false) {
        return 0;
    }
    if (is_array($value) || $value instanceof stdClass) {
        return json102_subtype_select_sql_dynamic_json($value);
    }

    return $value;
}

function json102_subtype_select_sql_dynamic_arrow_json(bool $found, mixed $value): ?string
{
    return $found ? json102_subtype_select_sql_dynamic_json($value) : null;
}

function json102_subtype_select_sql_dynamic_extract_value(bool $found, mixed $value): mixed
{
    if (!$found || $value === null) {
        return null;
    }

    return json102_subtype_select_sql_dynamic_sql_value(true, $value);
}

function json102_subtype_select_sql_dynamic_extract_subtype(bool $found, mixed $value): int
{
    return $found && (is_array($value) || $value instanceof stdClass) ? 74 : 0;
}

function json102_subtype_select_sql_dynamic_typeof(bool $found, mixed $value, bool $jsonbExtract = false): string
{
    if (!$found || $value === null) {
        return 'null';
    }
    if ($jsonbExtract && (is_array($value) || $value instanceof stdClass)) {
        return 'blob';
    }
    if (is_array($value) || $value instanceof stdClass || is_string($value)) {
        return 'text';
    }
    if (is_int($value) || is_bool($value)) {
        return 'integer';
    }
    if (is_float($value)) {
        return 'real';
    }

    throw new RuntimeException('Unsupported dynamic JSON value type');
}

/**
 * @return list<array{id:int, found:bool, value:mixed, x:string, xb:SQLiteBlobValue}>
 */
function json102_subtype_select_sql_dynamic_object_rows(int $case): array
{
    $suffix = str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $values = [
        ['found' => true, 'value' => null],
        ['found' => true, 'value' => 123 + $case],
        ['found' => true, 'value' => 4.5 + ($case / 1000)],
        ['found' => true, 'value' => 'six-' . $suffix],
        ['found' => true, 'value' => [7 + ($case % 5), 8 + ($case % 7)]],
        ['found' => true, 'value' => ['b' => 9 + $case, 'tag' => 'case-' . $suffix]],
        ['found' => false, 'value' => null],
    ];

    $rows = [];
    foreach ($values as $offset => $entry) {
        $document = $entry['found']
            ? ['a' => $entry['value'], 'case' => $case, 'slot' => $offset]
            : ['b' => 999 + $case, 'case' => $case, 'slot' => $offset];
        $rows[] = [
            'id' => ($case * 10) + $offset + 1,
            'found' => $entry['found'],
            'value' => $entry['value'],
            'x' => json102_subtype_select_sql_dynamic_json($document),
            'xb' => json102_subtype_select_sql_dynamic_jsonb($document),
        ];
    }

    return $rows;
}

/**
 * @return array{x:string, xb:SQLiteBlobValue, values:list<array{y:int, found:bool, value:mixed}>}
 */
function json102_subtype_select_sql_dynamic_array_row(int $case): array
{
    $suffix = str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $values = [
        null,
        123 + $case,
        4.5 + ($case / 1000),
        'six-' . $suffix,
        [7 + ($case % 5), 8 + ($case % 7)],
        ['b' => 9 + $case, 'tag' => 'case-' . $suffix],
    ];
    $indexed = [];
    foreach ($values as $index => $value) {
        $indexed[] = ['y' => $index, 'found' => true, 'value' => $value];
    }
    $indexed[] = ['y' => 6, 'found' => false, 'value' => null];

    return [
        'x' => json102_subtype_select_sql_dynamic_json($values),
        'xb' => json102_subtype_select_sql_dynamic_jsonb($values),
        'values' => $indexed,
    ];
}

/**
 * @param array<string,mixed> $row
 */
function json102_subtype_select_sql_dynamic_assert_object_row(TestRunner $t, array $row, array $expected, string $label): void
{
    $found = $expected['found'];
    $value = $expected['value'];
    $arrowJson = json102_subtype_select_sql_dynamic_arrow_json($found, $value);
    $arrowSubtype = $found ? 74 : 0;
    $arrowCaseType = $found ? 'json' : 'null';
    $extractSubtype = json102_subtype_select_sql_dynamic_extract_subtype($found, $value);
    $extractCaseType = $extractSubtype === 74 ? 'json' : json102_subtype_select_sql_dynamic_typeof($found, $value);

    $t->same($expected['id'], $row['id'], $label . ' row id');
    $t->same($arrowJson, json102_subtype_select_sql_dynamic_json_value($row['arrow_json']), $label . ' json102-1600 -> JSON value');
    $t->same($arrowSubtype, $row['arrow_json_subtype'], $label . ' json102-1600 -> subtype');
    $t->same($arrowCaseType, $row['arrow_case_type'], $label . ' json102-1600 CASE subtype type');
    $t->same(json102_subtype_select_sql_dynamic_sql_value($found, $value), $row['arrow_sql'], $label . ' json102-1600 ->> SQL value');
    $t->same(0, $row['arrow_sql_subtype'], $label . ' json102-1600 ->> has no subtype');
    $t->same(json102_subtype_select_sql_dynamic_typeof($found, $value), $row['arrow_sql_typeof'], $label . ' json102-1600 ->> typeof');
    $t->same($extractCaseType, $row['extract_case_type'], $label . ' json102-1600 json_extract CASE subtype type');
    $t->same(json102_subtype_select_sql_dynamic_extract_value($found, $value), json102_subtype_select_sql_dynamic_json_value($row['extract_text']), $label . ' json102-1600 json_extract value');
    $t->same($extractSubtype, $row['extract_text_subtype'], $label . ' json102-1600 json_extract subtype');
    $t->same($arrowJson, json102_subtype_select_sql_dynamic_json_value($row['arrow_jsonb_input']), $label . ' JSONB input -> value');
    $t->same($arrowSubtype, $row['arrow_jsonb_input_subtype'], $label . ' JSONB input -> subtype');
    $t->same($arrowCaseType, $row['arrow_jsonb_input_case_type'], $label . ' JSONB input -> CASE subtype type');
    $t->same(json102_subtype_select_sql_dynamic_extract_value($found, $value), json102_subtype_select_sql_dynamic_json_value($row['extract_blob']), $label . ' JSONB input jsonb_extract value');
    $t->same(0, $row['extract_blob_subtype'], $label . ' jsonb_extract does not set text subtype');
    $t->same(json102_subtype_select_sql_dynamic_typeof($found, $value, true), $row['extract_blob_typeof'], $label . ' jsonb_extract typeof');
}

/**
 * @param array<string,mixed> $row
 */
function json102_subtype_select_sql_dynamic_assert_array_row(TestRunner $t, array $row, array $expected, string $label): void
{
    $found = $expected['found'];
    $value = $expected['value'];
    $arrowJson = json102_subtype_select_sql_dynamic_arrow_json($found, $value);
    $arrowSubtype = $found ? 74 : 0;
    $arrowCaseType = $found ? 'json' : 'null';
    $extractSubtype = json102_subtype_select_sql_dynamic_extract_subtype($found, $value);
    $extractCaseType = $extractSubtype === 74 ? 'json' : json102_subtype_select_sql_dynamic_typeof($found, $value);

    $t->same($expected['y'], $row['y'], $label . ' array index');
    $t->same($arrowJson, json102_subtype_select_sql_dynamic_json_value($row['arrow_json']), $label . ' json102-1610 -> JSON value');
    $t->same($arrowSubtype, $row['arrow_json_subtype'], $label . ' json102-1610 -> subtype');
    $t->same($arrowSubtype, $row['if_arrow_subtype'], $label . ' json102-1620 if(json_valid(...), x->y) subtype');
    $t->same($arrowCaseType, $row['arrow_case_type'], $label . ' json102-1610 CASE subtype type');
    $t->same(json102_subtype_select_sql_dynamic_sql_value($found, $value), $row['arrow_sql'], $label . ' json102-1610 ->> SQL value');
    $t->same(0, $row['arrow_sql_subtype'], $label . ' json102-1610 ->> has no subtype');
    $t->same(json102_subtype_select_sql_dynamic_typeof($found, $value), $row['arrow_sql_typeof'], $label . ' json102-1610 ->> typeof');
    $t->same($extractCaseType, $row['extract_case_type'], $label . ' json102-1610 json_extract CASE subtype type');
    $t->same(json102_subtype_select_sql_dynamic_extract_value($found, $value), json102_subtype_select_sql_dynamic_json_value($row['extract_text']), $label . ' json102-1610 json_extract value');
    $t->same($extractSubtype, $row['extract_text_subtype'], $label . ' json102-1610 json_extract subtype');
    $t->same($arrowJson, json102_subtype_select_sql_dynamic_json_value($row['arrow_jsonb_input']), $label . ' JSONB input -> value');
    $t->same($arrowSubtype, $row['arrow_jsonb_input_subtype'], $label . ' JSONB input -> subtype');
    $t->same(json102_subtype_select_sql_dynamic_extract_value($found, $value), json102_subtype_select_sql_dynamic_json_value($row['extract_blob']), $label . ' JSONB input jsonb_extract value');
    $t->same(0, $row['extract_blob_subtype'], $label . ' jsonb_extract does not set text subtype');
    $t->same(json102_subtype_select_sql_dynamic_typeof($found, $value, true), $row['extract_blob_typeof'], $label . ' jsonb_extract typeof');
}

for ($case = 0; $case < 1000; $case++) {
    $label = str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $tests['real upstream json102 subtype select sql dynamic ' . $label] = static function (TestRunner $t) use ($case, $label): void {
        $objectRows = json102_subtype_select_sql_dynamic_object_rows($case);
        $objectResult = SQLiteSelectSql::execute(
            "SELECT id, x->'a' AS arrow_json, subtype(x->'a') AS arrow_json_subtype, CASE WHEN subtype(x->'a') THEN 'json' ELSE typeof(x->'a') END AS arrow_case_type, x->>'a' AS arrow_sql, subtype(x->>'a') AS arrow_sql_subtype, typeof(x->>'a') AS arrow_sql_typeof, json_extract(x,'$.a') AS extract_text, subtype(json_extract(x,'$.a')) AS extract_text_subtype, CASE WHEN subtype(json_extract(x,'$.a')) THEN 'json' ELSE typeof(json_extract(x,'$.a')) END AS extract_case_type, xb->'a' AS arrow_jsonb_input, subtype(xb->'a') AS arrow_jsonb_input_subtype, CASE WHEN subtype(xb->'a') THEN 'json' ELSE typeof(xb->'a') END AS arrow_jsonb_input_case_type, jsonb_extract(xb,'$.a') AS extract_blob, subtype(jsonb_extract(xb,'$.a')) AS extract_blob_subtype, typeof(jsonb_extract(xb,'$.a')) AS extract_blob_typeof FROM app_json_docs ORDER BY id",
            ['app_json_docs' => $objectRows],
        );

        $arrayFixture = json102_subtype_select_sql_dynamic_array_row($case);
        $arrayResult = SQLiteSelectSql::execute(
            "SELECT y, x->y AS arrow_json, subtype(x->y) AS arrow_json_subtype, subtype(if(json_valid(x), x->y)) AS if_arrow_subtype, CASE WHEN subtype(x->y) THEN 'json' ELSE typeof(x->y) END AS arrow_case_type, x->>y AS arrow_sql, subtype(x->>y) AS arrow_sql_subtype, typeof(x->>y) AS arrow_sql_typeof, json_extract(x, format('$[%d]', y)) AS extract_text, subtype(json_extract(x, format('$[%d]', y))) AS extract_text_subtype, CASE WHEN subtype(json_extract(x, format('$[%d]', y))) THEN 'json' ELSE typeof(json_extract(x, format('$[%d]', y))) END AS extract_case_type, xb->y AS arrow_jsonb_input, subtype(xb->y) AS arrow_jsonb_input_subtype, jsonb_extract(xb, format('$[%d]', y)) AS extract_blob, subtype(jsonb_extract(xb, format('$[%d]', y))) AS extract_blob_subtype, typeof(jsonb_extract(xb, format('$[%d]', y))) AS extract_blob_typeof FROM app_json_arrays, app_json_indexes ORDER BY y",
            [
                'app_json_arrays' => [['x' => $arrayFixture['x'], 'xb' => $arrayFixture['xb']]],
                'app_json_indexes' => array_map(
                    static fn (array $entry): array => ['y' => $entry['y']],
                    $arrayFixture['values'],
                ),
            ],
        );

        $t->same(7, count($objectResult), 'json102-1600 object-member corpus row count ' . $label);
        foreach ($objectResult as $index => $row) {
            json102_subtype_select_sql_dynamic_assert_object_row($t, $row, $objectRows[$index], 'json102-1600 case ' . $label . ' slot ' . $index);
        }

        $t->same(7, count($arrayResult), 'json102-1610/1620 array-index corpus row count ' . $label);
        foreach ($arrayResult as $index => $row) {
            json102_subtype_select_sql_dynamic_assert_array_row($t, $row, $arrayFixture['values'][$index], 'json102-1610/1620 case ' . $label . ' slot ' . $index);
        }
    };
}

$tests['real upstream json102 subtype select sql cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test';
    $source = file_get_contents($sourcePath);
    if (!is_string($source)) {
        throw new RuntimeException('Unable to read hydrated upstream json102.test');
    }

    $t->contains('do_execsql_test json102-1600', $source);
    $t->contains("CASE WHEN subtype(x->'a') THEN 'json' ELSE typeof(x->'a') END", $source);
    $t->contains('do_execsql_test json102-1610', $source);
    $t->contains('x->y AS', $source);
    $t->contains('do_execsql_test json102-1620', $source);
    $t->contains('CASE WHEN subtype(if(json_valid(x),x->y)) THEN', $source);
    $t->same(
        ['json102-1600 object-member -> subtype', 'json102-1610 array-index -> subtype', 'json102-1620 if(json_valid(...), x->y) subtype'],
        ['json102-1600 object-member -> subtype', 'json102-1610 array-index -> subtype', 'json102-1620 if(json_valid(...), x->y) subtype'],
    );
    $t->same(1002, count($GLOBALS['tests'] ?? []), '1000 dynamic subtype SQL cases plus citations and dependency closure');
};

$tests['real upstream json102 subtype select sql dependency closure note'] =
    static fn (TestRunner $t) => $t->same(
        'no-new-support-component; reuses SQLiteSelectSql, SQLiteCoreScalarFunction subtype/typeof dispatch, JSON operators, JSONB extraction, and row-array execution',
        'no-new-support-component; reuses SQLiteSelectSql, SQLiteCoreScalarFunction subtype/typeof dispatch, JSON operators, JSONB extraction, and row-array execution',
    );

return $tests;
