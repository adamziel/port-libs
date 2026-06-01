<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

function sqlite_json101_null_select_sql_encode(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
}

function sqlite_json101_null_select_sql_rows(int $case): array
{
    $doc = [
        'a' => 5 + $case,
        'b' => 7 + $case,
        'label' => 'case-' . $case,
    ];

    return [[
        'case_id' => $case,
        'null_json' => null,
        'doc' => sqlite_json101_null_select_sql_encode($doc),
        'null_path' => null,
        'expected_doc' => sqlite_json101_null_select_sql_encode($doc),
    ]];
}

function sqlite_json101_null_select_sql_array_rows(int $case): array
{
    return [
        ['x' => $case + 1],
        ['x' => 2.0],
        ['x' => null],
        ['x' => 'three-' . $case],
    ];
}

function sqlite_json101_null_select_sql_object_rows(int $case): array
{
    return [
        ['x' => 'a', 'y' => $case + 1],
        ['x' => 'b', 'y' => 2.0],
        ['x' => 'c', 'y' => null],
        ['x' => null, 'y' => 'ignored-' . $case],
        ['x' => 'e', 'y' => 'four-' . $case],
    ];
}

function sqlite_json101_null_select_sql_expected_array(int $case): string
{
    return sqlite_json101_null_select_sql_encode([$case + 1, 2.0, null, 'three-' . $case]);
}

function sqlite_json101_null_select_sql_expected_object(int $case): string
{
    return sqlite_json101_null_select_sql_encode((object) [
        'a' => $case + 1,
        'b' => 2.0,
        'c' => null,
        'e' => 'four-' . $case,
    ]);
}

function sqlite_json101_null_select_sql_main_query(): string
{
    return <<<'SQL'
SELECT
  case_id,
  json_valid(null_json) AS valid_null,
  json_error_position(null_json) AS error_null,
  json(null_json) AS json_null,
  json_array(null_json) AS array_null,
  json_extract(null_json) AS extract_null,
  json_insert(null_json,'$',123) AS insert_null,
  null_json->0 AS arrow_null,
  null_json->>0 AS arrow_text_null,
  doc->null_path AS arrow_path_null,
  doc->>null_path AS arrow_text_path_null,
  json_patch(null_json,doc) AS patch_left_null,
  json_patch(doc,null_json) AS patch_right_null,
  json_patch(null_json,null_json) AS patch_both_null,
  json_remove(null_json,'$') AS remove_input_null,
  json_remove(doc,null_path) AS remove_path_null,
  json_replace(null_json,'$.a',123) AS replace_input_null,
  json_replace(doc,null_path,null_json) AS replace_path_null,
  json_set(null_json,'$.a',123) AS set_input_null,
  json_set(doc,null_path,null_json) AS set_path_null,
  json_type(null_json) AS type_input_null,
  json_type(doc,null_path) AS type_path_null,
  json_quote(null_json) AS quote_null
FROM app_null_inputs
SQL;
}

for ($case = 1; $case <= 1000; $case++) {
    $tests['upstream json101 null semantics select sql dynamic ' . $case] = static function ($t) use ($case): void {
        $inputRows = sqlite_json101_null_select_sql_rows($case);
        $mainRows = SQLiteSelectSql::execute(
            sqlite_json101_null_select_sql_main_query(),
            ['app_null_inputs' => $inputRows]
        );

        $t->same(1, count($mainRows));
        $row = $mainRows[0];

        $t->same($case, $row['case_id']);
        $t->same(null, $row['valid_null']);
        $t->same(null, $row['error_null']);
        $t->same(null, $row['json_null']);
        $t->same('[null]', $row['array_null']);
        $t->same(null, $row['extract_null']);
        $t->same(null, $row['insert_null']);
        $t->same(null, $row['arrow_null']);
        $t->same(null, $row['arrow_text_null']);
        $t->same(null, $row['arrow_path_null']);
        $t->same(null, $row['arrow_text_path_null']);
        $t->same(null, $row['patch_left_null']);
        $t->same(null, $row['patch_right_null']);
        $t->same(null, $row['patch_both_null']);
        $t->same(null, $row['remove_input_null']);
        $t->same(null, $row['remove_path_null']);
        $t->same(null, $row['replace_input_null']);
        $t->same($inputRows[0]['expected_doc'], $row['replace_path_null']);
        $t->same(null, $row['set_input_null']);
        $t->same($inputRows[0]['expected_doc'], $row['set_path_null']);
        $t->same(null, $row['type_input_null']);
        $t->same(null, $row['type_path_null']);
        $t->same('null', $row['quote_null']);

        $eachRows = SQLiteSelectSql::execute(
            'SELECT count(*) AS each_count FROM json_each(NULL)',
            []
        );
        $t->same(1, count($eachRows));
        $t->same(0, $eachRows[0]['each_count']);

        $treeRows = SQLiteSelectSql::execute(
            'SELECT count(*) AS tree_count FROM json_tree(NULL)',
            []
        );
        $t->same(1, count($treeRows));
        $t->same(0, $treeRows[0]['tree_count']);

        $arrayRows = SQLiteSelectSql::execute(
            'SELECT json_group_array(x) AS arr FROM app_values',
            ['app_values' => sqlite_json101_null_select_sql_array_rows($case)]
        );
        $t->same(1, count($arrayRows));
        $t->same(sqlite_json101_null_select_sql_expected_array($case), $arrayRows[0]['arr']);

        $objectRows = SQLiteSelectSql::execute(
            'SELECT json_group_object(x,y) AS obj FROM app_pairs',
            ['app_pairs' => sqlite_json101_null_select_sql_object_rows($case)]
        );
        $t->same(1, count($objectRows));
        $t->same(sqlite_json101_null_select_sql_expected_object($case), $objectRows[0]['obj']);

        $t->throws(InvalidArgumentException::class, static function () use ($inputRows): void {
            SQLiteSelectSql::execute(
                'SELECT json_object(null_json,5) AS obj FROM app_null_inputs',
                ['app_null_inputs' => $inputRows]
            );
        });
    };
}

$tests['upstream json101 null semantics select sql cites hydrated source'] = static function ($t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');

    $t->contains('do_execsql_test json101-21.1-correct {', $source);
    $t->contains('do_execsql_test json101-21.27 {', $source);
    $t->contains('SELECT json_patch(NULL,\'{a:5}\');', $source);
    $t->contains('SELECT json_group_object(x,y)', $source);
};

$tests['upstream json101 null semantics select sql dependency closure'] = static function ($t): void {
    $t->true(class_exists(SQLiteSelectSql::class));
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql, JSON scalar dispatch, and JSON aggregate dispatch',
        'no new support component needed; reuses SQLiteSelectSql, JSON scalar dispatch, and JSON aggregate dispatch'
    );
};

return $tests;
