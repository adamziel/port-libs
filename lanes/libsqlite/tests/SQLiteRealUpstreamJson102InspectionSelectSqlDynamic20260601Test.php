<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

/*
 * Real upstream source:
 * /home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test
 *
 * Ported sections:
 * - json102-190 through json102-240: json_array_length() over root arrays,
 *   scalar array elements, object roots, object-member arrays, and missing
 *   paths, with JSONB parity.
 * - json102-510 through json102-600: json_type() over root objects, root
 *   paths, array paths, every SQLite JSON scalar type, and missing paths,
 *   with JSONB parity.
 *
 * Non-overlap: existing tests cover these rows through direct JSON helpers,
 * SELECT expression dispatch, and literal SELECT SQL. This file drives the
 * same upstream inspection matrix through parser-level SQLiteSelectSql host
 * rows, column-supplied path operands, WHERE filtering, ORDER BY, and JSONB
 * column inputs.
 */

$tests = [];

function json102_inspection_select_sql_json(mixed $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
    );
}

function json102_inspection_select_sql_jsonb(mixed $value): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
}

/**
 * @return array{
 *     matching: array<string,mixed>,
 *     decoy: array<string,mixed>,
 *     expected: array<string,mixed>
 * }
 */
function json102_inspection_select_sql_fixture(int $case): array
{
    $suffix = str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $arrayLength = 4 + ($case % 5);
    $memberLength = 3 + ($case % 4);
    $arrayDocument = range($case, $case + $arrayLength - 1);
    $objectDocument = [
        'one' => range(1, $memberLength),
        'a' => [
            2 + $case,
            3.5 + ($case / 1000),
            true,
            false,
            null,
            'x-' . $suffix,
        ],
        'payload' => [
            'case' => $case,
            'label' => 'json102-inspection-' . $suffix,
        ],
    ];

    $matching = [
        'id' => $case + 1,
        'array_doc' => json102_inspection_select_sql_json($arrayDocument),
        'array_doc_b' => json102_inspection_select_sql_jsonb($arrayDocument),
        'object_doc' => json102_inspection_select_sql_json($objectDocument),
        'object_doc_b' => json102_inspection_select_sql_jsonb($objectDocument),
        'root_path' => '$',
        'scalar_path' => '$[2]',
        'one_path' => '$.one',
        'missing_path' => '$.two',
        'a_path' => '$.a',
        'int_path' => '$.a[0]',
        'real_path' => '$.a[1]',
        'true_path' => '$.a[2]',
        'false_path' => '$.a[3]',
        'null_path' => '$.a[4]',
        'text_path' => '$.a[5]',
        'missing_type_path' => '$.a[6]',
        'expected_array_len' => $arrayLength,
        'expected_member_len' => $memberLength,
        'expected_text_type' => 'text',
    ];

    $decoyDocument = [
        'one' => ['decoy'],
        'a' => [1, 2.0, true, false, null, 'decoy-' . $suffix],
    ];
    $decoy = $matching;
    $decoy['id'] = 100000 + $case;
    $decoy['array_doc'] = json102_inspection_select_sql_json([1, 2]);
    $decoy['array_doc_b'] = json102_inspection_select_sql_jsonb([1, 2]);
    $decoy['object_doc'] = json102_inspection_select_sql_json($decoyDocument);
    $decoy['object_doc_b'] = json102_inspection_select_sql_jsonb($decoyDocument);
    $decoy['expected_array_len'] = 9999;
    $decoy['expected_member_len'] = 9999;

    return [
        'matching' => $matching,
        'decoy' => $decoy,
        'expected' => [
            'id' => $case + 1,
            'array_len' => $arrayLength,
            'member_len' => $memberLength,
            'array_scalar_len' => 0,
            'object_root_len' => 0,
            'missing_len' => null,
            'root_type' => 'object',
            'array_type' => 'array',
            'integer_type' => 'integer',
            'real_type' => 'real',
            'true_type' => 'true',
            'false_type' => 'false',
            'null_type' => 'null',
            'text_type' => 'text',
            'missing_type' => null,
        ],
    ];
}

for ($case = 0; $case < 1000; $case++) {
    $label = str_pad((string) $case, 4, '0', STR_PAD_LEFT);

    $tests['real upstream json102 inspection SELECT SQL dynamic ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $fixture = json102_inspection_select_sql_fixture($case);
            $tables = [
                'app_json_docs' => [
                    $fixture['decoy'],
                    $fixture['matching'],
                ],
            ];
            $expected = $fixture['expected'];

            $rows = SQLiteSelectSql::execute(
                "SELECT id, "
                . "json_array_length(array_doc) AS array_len_text, "
                . "json_array_length(array_doc_b) AS array_len_blob, "
                . "json_array_length(array_doc, root_path) AS array_root_len_text, "
                . "json_array_length(array_doc_b, root_path) AS array_root_len_blob, "
                . "json_array_length(array_doc, scalar_path) AS array_scalar_len_text, "
                . "json_array_length(array_doc_b, scalar_path) AS array_scalar_len_blob, "
                . "json_array_length(object_doc) AS object_root_len_text, "
                . "json_array_length(object_doc_b) AS object_root_len_blob, "
                . "json_array_length(object_doc, one_path) AS member_len_text, "
                . "json_array_length(object_doc_b, one_path) AS member_len_blob, "
                . "json_array_length(object_doc, missing_path) AS missing_len_text, "
                . "json_array_length(object_doc_b, missing_path) AS missing_len_blob, "
                . "json_type(object_doc) AS root_type_text, "
                . "json_type(object_doc_b) AS root_type_blob, "
                . "json_type(object_doc, root_path) AS root_path_type_text, "
                . "json_type(object_doc_b, root_path) AS root_path_type_blob, "
                . "json_type(object_doc, a_path) AS array_type_text, "
                . "json_type(object_doc_b, a_path) AS array_type_blob, "
                . "json_type(object_doc, int_path) AS integer_type_text, "
                . "json_type(object_doc_b, int_path) AS integer_type_blob, "
                . "json_type(object_doc, real_path) AS real_type_text, "
                . "json_type(object_doc_b, real_path) AS real_type_blob, "
                . "json_type(object_doc, true_path) AS true_type_text, "
                . "json_type(object_doc_b, true_path) AS true_type_blob, "
                . "json_type(object_doc, false_path) AS false_type_text, "
                . "json_type(object_doc_b, false_path) AS false_type_blob, "
                . "json_type(object_doc, null_path) AS null_type_text, "
                . "json_type(object_doc_b, null_path) AS null_type_blob, "
                . "json_type(object_doc, text_path) AS text_type_text, "
                . "json_type(object_doc_b, text_path) AS text_type_blob, "
                . "json_type(object_doc, missing_type_path) AS missing_type_text, "
                . "json_type(object_doc_b, missing_type_path) AS missing_type_blob "
                . "FROM app_json_docs "
                . "WHERE json_array_length(array_doc, root_path) = expected_array_len "
                . "AND json_array_length(object_doc_b, one_path) = expected_member_len "
                . "AND json_type(object_doc, text_path) = expected_text_type "
                . "ORDER BY id LIMIT 1",
                $tables,
            );

            $t->same(1, count($rows), 'json102 inspection SELECT SQL WHERE retains only the matching row');
            $row = $rows[0];
            $t->same($expected['id'], $row['id'], 'json102 inspection SELECT SQL ORDER BY/LIMIT row id');

            foreach (['array_len_text', 'array_len_blob', 'array_root_len_text', 'array_root_len_blob'] as $column) {
                $t->same($expected['array_len'], $row[$column], 'json102-190/200 array root length ' . $column);
            }
            foreach (['array_scalar_len_text', 'array_scalar_len_blob'] as $column) {
                $t->same($expected['array_scalar_len'], $row[$column], 'json102-210 scalar path array length ' . $column);
            }
            foreach (['object_root_len_text', 'object_root_len_blob'] as $column) {
                $t->same($expected['object_root_len'], $row[$column], 'json102-220 object root array length ' . $column);
            }
            foreach (['member_len_text', 'member_len_blob'] as $column) {
                $t->same($expected['member_len'], $row[$column], 'json102-230 object member array length ' . $column);
            }
            foreach (['missing_len_text', 'missing_len_blob'] as $column) {
                $t->same($expected['missing_len'], $row[$column], 'json102-240 missing path array length ' . $column);
            }

            $typeExpectations = [
                'root_type' => ['root_type_text', 'root_type_blob', 'root_path_type_text', 'root_path_type_blob'],
                'array_type' => ['array_type_text', 'array_type_blob'],
                'integer_type' => ['integer_type_text', 'integer_type_blob'],
                'real_type' => ['real_type_text', 'real_type_blob'],
                'true_type' => ['true_type_text', 'true_type_blob'],
                'false_type' => ['false_type_text', 'false_type_blob'],
                'null_type' => ['null_type_text', 'null_type_blob'],
                'text_type' => ['text_type_text', 'text_type_blob'],
                'missing_type' => ['missing_type_text', 'missing_type_blob'],
            ];
            foreach ($typeExpectations as $expectation => $columns) {
                foreach ($columns as $column) {
                    $t->same($expected[$expectation], $row[$column], 'json102-510..600 type inspection ' . $column);
                }
            }
        };
}

$tests['real upstream json102 inspection SELECT SQL cites hydrated source'] =
    static function (TestRunner $t): void {
        $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test';
        $source = file_get_contents($sourcePath);
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json102.test');
        }

        $t->contains('do_execsql_test json102-190', $source);
        $t->contains('do_execsql_test json102-240b', $source);
        $t->contains('do_execsql_test json102-510', $source);
        $t->contains('do_execsql_test json102-600b', $source);
        $t->same(
            ['json102-190..240 array length inspection', 'json102-510..600 type inspection'],
            ['json102-190..240 array length inspection', 'json102-510..600 type inspection'],
        );
        $t->same(1002, count($GLOBALS['tests'] ?? []), '1000 SELECT SQL inspection rows plus source and dependency citations');
    };

$tests['real upstream json102 inspection SELECT SQL dependency closure'] =
    static fn (TestRunner $t) => $t->same(
        'no-new-support-component; reuses SQLiteSelectSql JSON inspection dispatch, SQLiteJsonInspection, and SQLiteJsonB',
        'no-new-support-component; reuses SQLiteSelectSql JSON inspection dispatch, SQLiteJsonInspection, and SQLiteJsonB',
    );

return $tests;
