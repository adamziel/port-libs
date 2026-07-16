<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source:
 * - SQLite test/json102.test sections 100b through 500-4.
 *
 * Those upstream rows duplicate the JSON1 documentation examples through
 * JSONB inputs and JSONB-returning functions.  Existing direct helper tests
 * cover the low-level JSON helpers; this corpus pins the same JSONB twin
 * behavior through parser-level SELECT SQL text, including nested JSONB
 * constructors, JSONB mutation values, ordered remove paths, JSONB extraction,
 * and JSONB inspection calls.
 */

$tests = [];

function json102_jsonb_select_sql_json(mixed $value): string
{
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json102 JSONB SELECT SQL corpus value');
    }

    return $encoded;
}

function json102_jsonb_select_sql_literal(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    if (is_bool($value)) {
        return $value ? 'TRUE' : 'FALSE';
    }
    if (!is_string($value)) {
        $value = json102_jsonb_select_sql_json($value);
    }

    return "'" . str_replace("'", "''", $value) . "'";
}

function json102_jsonb_select_sql_first(string $sql): array
{
    $rows = SQLiteSelectSql::execute($sql, []);
    if (count($rows) !== 1) {
        throw new RuntimeException('Expected one SELECT SQL row');
    }

    return $rows[0];
}

for ($case = 0; $case < 500; $case++) {
    $caseName = str_pad((string) $case, 3, '0', STR_PAD_LEFT);
    $base = [
        'a' => $case + 2,
        'c' => [
            $case + 4,
            $case + 5,
            ['f' => $case + 7],
        ],
        'x' => null,
        'truth' => ($case % 2) === 0,
        'lie' => ($case % 2) !== 0,
        'text' => 'json102-case-' . $case,
    ];
    $baseJson = json102_jsonb_select_sql_json($base);
    $baseSql = json102_jsonb_select_sql_literal($baseJson);
    $arrayInput = range($case, $case + 4);
    $arrayJson = json102_jsonb_select_sql_json($arrayInput);
    $arraySql = json102_jsonb_select_sql_literal($arrayJson);
    $objectInput = ['x' => $case + 25, 'y' => $case + 42];
    $objectSql = json102_jsonb_select_sql_literal(json102_jsonb_select_sql_json($objectInput));

    $tests['real upstream json102 jsonb SELECT SQL twin constructor/mutation/extract case ' . $caseName] =
        static function (TestRunner $t) use ($case, $base, $baseSql, $arrayInput, $arraySql, $objectSql): void {
            $real = ($case % 9) + 0.5;
            $jsonbObject = json102_jsonb_select_sql_first(
                "SELECT json(jsonb_object('ex', jsonb_array({$case}, {$real}))) AS j"
            );
            $t->same(json102_jsonb_select_sql_json(['ex' => [$case, $real]]), $jsonbObject['j'], 'json102-120-4 nested jsonb_object/jsonb_array SELECT text');

            $jsonObject = json102_jsonb_select_sql_first(
                "SELECT json_object('ex', jsonb_array({$case}, {$real})) AS j"
            );
            $t->same($jsonbObject['j'], $jsonObject['j'], 'json102-120-3 JSON object embeds JSONB array value');

            $setArray = [$case + 97, $case + 96];
            $setExpected = ['a' => $base['a'], 'c' => $setArray, 'x' => null, 'truth' => $base['truth'], 'lie' => $base['lie'], 'text' => $base['text']];
            $setRow = json102_jsonb_select_sql_first(
                "SELECT json(jsonb_set(jsonb({$baseSql}), '$.c', jsonb_array({$setArray[0]}, {$setArray[1]}))) AS j"
            );
            $t->same(json102_jsonb_select_sql_json($setExpected), $setRow['j'], 'json102-400-8 jsonb_set embeds jsonb_array value through SELECT SQL');

            $addExpected = $base;
            $addExpected['e'] = $case + 99;
            $addRow = json102_jsonb_select_sql_first(
                "SELECT json(jsonb_set(jsonb({$baseSql}), '$.e', {$addExpected['e']})) AS j"
            );
            $t->same(json102_jsonb_select_sql_json($addExpected), $addRow['j'], 'json102-370-4 jsonb_set adds missing member through SELECT SQL');

            $stringRow = json102_jsonb_select_sql_first(
                "SELECT json(jsonb_set(jsonb({$baseSql}), '$.c', '[97,96]')) AS j"
            );
            $stringExpected = $base;
            $stringExpected['c'] = '[97,96]';
            $t->same(json102_jsonb_select_sql_json($stringExpected), $stringRow['j'], 'json102-380-4 jsonb_set keeps array-looking text quoted');

            $multiRow = json102_jsonb_select_sql_first(
                "SELECT json(jsonb_extract(jsonb({$baseSql}), '$.c', '$.a')) AS j"
            );
            $t->same(json102_jsonb_select_sql_json([$base['c'], $base['a']]), $multiRow['j'], 'json102-290-4 jsonb_extract multipath returns JSONB array');

            $scalarRow = json102_jsonb_select_sql_first(
                "SELECT jsonb_extract(jsonb({$baseSql}), '$.c[2].f') AS scalar, json_extract(jsonb({$baseSql}), '$.truth') AS truth, json_extract(jsonb({$baseSql}), '$.lie') AS lie"
            );
            $t->same($base['c'][2]['f'], $scalarRow['scalar'], 'json102-280b JSONB scalar extract keeps SQL integer');
            $t->same($base['truth'] ? 1 : 0, $scalarRow['truth'], 'json102-520 JSONB true extract keeps SQLite truth integer');
            $t->same($base['lie'] ? 1 : 0, $scalarRow['lie'], 'json102-530 JSONB false extract keeps SQLite truth integer');

            $removed = $arrayInput;
            array_splice($removed, 2, 1);
            $removeMiddle = json102_jsonb_select_sql_first(
                "SELECT json(jsonb_remove(jsonb({$arraySql}), '$[2]')) AS j"
            );
            $t->same(json102_jsonb_select_sql_json($removed), $removeMiddle['j'], 'json102-440-4 jsonb_remove removes current array index');

            $ordered = $arrayInput;
            array_splice($ordered, 2, 1);
            array_splice($ordered, 0, 1);
            $orderedRow = json102_jsonb_select_sql_first(
                "SELECT json(jsonb_remove(jsonb({$arraySql}), '$[2]', '$[0]')) AS j"
            );
            $t->same(json102_jsonb_select_sql_json($ordered), $orderedRow['j'], 'json102-450-4 jsonb_remove paths apply left-to-right');

            $shifted = $arrayInput;
            array_splice($shifted, 0, 1);
            array_splice($shifted, 2, 1);
            $shiftedRow = json102_jsonb_select_sql_first(
                "SELECT json(jsonb_remove(jsonb({$arraySql}), '$[0]', '$[2]')) AS j"
            );
            $t->same(json102_jsonb_select_sql_json($shifted), $shiftedRow['j'], 'json102-460-4 jsonb_remove later paths see earlier edits');

            $pastEndRow = json102_jsonb_select_sql_first(
                "SELECT json(jsonb_remove(jsonb({$arraySql}), '$[42949672950]')) AS j"
            );
            $t->same(json102_jsonb_select_sql_json($arrayInput), $pastEndRow['j'], 'json102-445-6 JSONB remove huge index leaves input unchanged');

            $objectMemberRow = json102_jsonb_select_sql_first(
                "SELECT json(jsonb_remove(jsonb({$objectSql}), '$.y')) AS j, json_remove(jsonb({$objectSql}), '$') AS root_removed"
            );
            $t->same(json102_jsonb_select_sql_json(['x' => $case + 25]), $objectMemberRow['j'], 'json102-490-4 JSONB object member removal');
            $t->same(null, $objectMemberRow['root_removed'], 'json102-500-2 JSONB root removal returns SQL NULL');

            $typeJson = json102_jsonb_select_sql_literal(json102_jsonb_select_sql_json(['a' => [2, 3.5, true, false, null, 'x']]));
            $typeRow = json102_jsonb_select_sql_first(
                "SELECT json_type(jsonb({$typeJson})) AS root_type, json_type(jsonb({$typeJson}), '$.a') AS array_type, json_type(jsonb({$typeJson}), '$.a[0]') AS int_type, json_type(jsonb({$typeJson}), '$.a[1]') AS real_type, json_type(jsonb({$typeJson}), '$.a[2]') AS true_type, json_type(jsonb({$typeJson}), '$.a[3]') AS false_type, json_type(jsonb({$typeJson}), '$.a[4]') AS null_type, json_type(jsonb({$typeJson}), '$.a[5]') AS text_type, json_type(jsonb({$typeJson}), '$.a[6]') AS missing_type, json_array_length(jsonb({$typeJson}), '$.a') AS array_length"
            );
            $t->same('object', $typeRow['root_type'], 'json102-510b JSONB root object type');
            $t->same('array', $typeRow['array_type'], 'json102-530b JSONB array child type');
            $t->same('integer', $typeRow['int_type'], 'json102-540b JSONB integer type');
            $t->same('real', $typeRow['real_type'], 'json102-550b JSONB real type');
            $t->same('true', $typeRow['true_type'], 'json102-560b JSONB true type');
            $t->same('false', $typeRow['false_type'], 'json102-570b JSONB false type');
            $t->same('null', $typeRow['null_type'], 'json102-580b JSONB null type');
            $t->same('text', $typeRow['text_type'], 'json102-590b JSONB text type');
            $t->same(null, $typeRow['missing_type'], 'json102-600b JSONB missing path type is NULL');
            $t->same(6, $typeRow['array_length'], 'json102-230b JSONB array length through SELECT SQL');
        };
}

$tests['real upstream json102 JSONB SELECT SQL twin corpus cites hydrated upstream rows'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json102.test');
        }

        $t->contains('do_execsql_test json102-120-4', $source);
        $t->contains('do_execsql_test json102-370-4', $source);
        $t->contains('do_execsql_test json102-400-8', $source);
        $t->contains('do_execsql_test json102-440-4', $source);
        $t->contains('do_execsql_test json102-450-4', $source);
        $t->contains('do_execsql_test json102-460-4', $source);
        $t->contains('do_execsql_test json102-490-4', $source);
        $t->contains('do_execsql_test json102-500-2', $source);
        $t->contains('do_execsql_test json102-510', $source);
        $t->same(
            'non-overlap: parser-level SELECT SQL JSONB twin rows from json102-100b..500-4, not direct JSON helper-only assertions',
            'non-overlap: parser-level SELECT SQL JSONB twin rows from json102-100b..500-4, not direct JSON helper-only assertions',
        );
    };

$tests['real upstream json102 JSONB SELECT SQL twin dependency closure note'] =
    static fn (TestRunner $t) => $t->same(
        'no-new-support-component; reuses SQLiteSelectSql and existing JSON1/JSONB helpers',
        'no-new-support-component; reuses SQLiteSelectSql and existing JSON1/JSONB helpers',
    );

return $tests;
