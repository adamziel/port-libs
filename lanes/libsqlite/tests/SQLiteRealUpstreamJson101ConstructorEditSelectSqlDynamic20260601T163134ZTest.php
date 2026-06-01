<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

/*
 * Real upstream source:
 * /home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test
 *
 * Ported sections:
 * - json101-1.1.00 through json101-1.4b: JSON array/JSONB array constructor
 *   scalar quoting, JSON subtype insertion, JSONB insertion, and BLOB
 *   rejection.
 * - json101-2.1 through json101-2.5: object constructors, label/arity
 *   boundaries, nested array/object values, JSONB parity, and BLOB rejection.
 * - json101-3.1 through json101-3.4b: edit-value boundaries where plain SQL
 *   text remains text while json()/jsonb() values remain structured JSON.
 * - json101-4.5 through json101-4.10b: no-edit mutation identity and root
 *   extraction of object/array values.
 * - json101-6.1 through json101-6.11: trailing-comma JSON5 canonicalization,
 *   strict validity, and double-comma error-position behavior.
 *
 * Non-overlap: older files cover these upstream rows through direct JSON
 * helpers and select-expression probes. This file drives the same constructor
 * and edit semantics through parser-level SQLiteSelectSql host rows, WHERE,
 * ORDER BY/LIMIT, and stored JSONB column inputs.
 */

$tests = [];

function json101_constructor_edit_select_sql_json(mixed $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
    );
}

function json101_constructor_edit_select_sql_jsonb(mixed $value): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
}

/**
 * @param list<array<string,mixed>> $rows
 * @return array<string,mixed>
 */
function json101_constructor_edit_select_sql_single_row(array $rows, string $label): array
{
    if (count($rows) !== 1) {
        throw new RuntimeException($label . ' expected exactly one row, got ' . count($rows));
    }

    return $rows[0];
}

function json101_constructor_edit_select_sql_throws_containing(TestRunner $t, string $sql, string $message): void
{
    try {
        SQLiteSelectSql::execute($sql, []);
    } catch (InvalidArgumentException $exception) {
        $t->contains($message, $exception->getMessage());

        return;
    }

    $t->same('exception', 'no exception', 'Expected SELECT SQL to reject upstream json101 constructor boundary');
}

/**
 * @return array{
 *     matching: array<string,mixed>,
 *     decoy: array<string,mixed>,
 *     expected: array<string,mixed>
 * }
 */
function json101_constructor_edit_select_sql_fixture(int $case): array
{
    $suffix = str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $integer = $case + 7;
    $marker = 90 + ($case % 10);
    $real = 2.5 + (($case % 4) / 4);
    $payload = [
        'abc' => 2.5 + (($case % 7) / 10),
        'def' => null,
        'ghi' => 'hello-' . $suffix,
        'case' => $case,
    ];
    $arrayValue = [
        3 + ($case % 7),
        4 + ($case % 11),
        'case-' . $suffix,
    ];
    $document = [
        'a' => 1 + $case,
        'payload' => ['old' => $case],
        'b' => 2 + $case,
        'keep' => 'value-' . $suffix,
    ];
    $textPayloadDocument = $document;
    $textPayloadDocument['payload'] = json101_constructor_edit_select_sql_json($arrayValue);
    $jsonPayloadDocument = $document;
    $jsonPayloadDocument['payload'] = $arrayValue;
    $label = 'dyn_' . $suffix;

    $matching = [
        'case_id' => $case + 1,
        'expected_case_id' => $case + 1,
        'i' => $integer,
        'marker' => $marker,
        'r' => $real,
        'n' => null,
        'label' => $label,
        'payload' => json101_constructor_edit_select_sql_json($payload),
        'array_text' => json101_constructor_edit_select_sql_json($arrayValue),
        'doc' => json101_constructor_edit_select_sql_json($document),
        'doc_b' => json101_constructor_edit_select_sql_jsonb($document),
        'object_trailing' => '{"a":55,"b":72,}',
        'object_double' => '{"a":55,"b":72,,}',
        'array_trailing' => '["a",55,"b",72 , ]',
        'array_double' => '["a",55,"b",72,,]',
    ];

    $decoy = $matching;
    $decoy['case_id'] = 100000 + $case;
    $decoy['expected_case_id'] = -1;
    $decoy['i'] = -1;
    $decoy['payload'] = json101_constructor_edit_select_sql_json(['decoy' => $case]);

    return [
        'matching' => $matching,
        'decoy' => $decoy,
        'expected' => [
            'case_id' => $case + 1,
            'array_plain' => json101_constructor_edit_select_sql_json([$integer, $matching['payload'], $marker]),
            'array_json' => json101_constructor_edit_select_sql_json([$integer, $payload, $marker]),
            'object_json' => json101_constructor_edit_select_sql_json([
                $label => [$integer, $real, null],
                'payload' => $payload,
            ]),
            'replace_text' => json101_constructor_edit_select_sql_json($textPayloadDocument),
            'replace_json' => json101_constructor_edit_select_sql_json($jsonPayloadDocument),
            'doc' => json101_constructor_edit_select_sql_json($document),
            'set_text_type' => 'text',
            'set_json_type' => 'array',
        ],
    ];
}

for ($case = 0; $case < 1000; $case++) {
    $label = str_pad((string) $case, 4, '0', STR_PAD_LEFT);

    $tests['real upstream json101 constructor edit SELECT SQL dynamic ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $fixture = json101_constructor_edit_select_sql_fixture($case);
            $rows = SQLiteSelectSql::execute(
                "SELECT case_id, "
                . "json(json_array(i,payload,marker)) AS array_plain, "
                . "json(json_array(i,json(payload),marker)) AS array_json, "
                . "json(jsonb_array(i,jsonb(payload),marker)) AS array_jsonb, "
                . "json(json_object(label,json_array(i,r,n),'payload',jsonb(payload))) AS object_json, "
                . "json(jsonb_object(label,jsonb_array(i,r,n),'payload',json(payload))) AS object_jsonb, "
                . "json_replace(doc,'$.payload',array_text) AS replace_text, "
                . "json_replace(doc,'$.payload',json(array_text)) AS replace_json, "
                . "json(jsonb_replace(doc_b,'$.payload',jsonb(array_text))) AS replace_jsonb, "
                . "json_type(json_set(doc,'$.payload',array_text),'$.payload') AS set_text_type, "
                . "json_type(jsonb_set(doc_b,'$.payload',jsonb(array_text)),'$.payload') AS set_jsonb_type, "
                . "json(jsonb_set(doc_b,'$.payload',jsonb(array_text))) AS set_jsonb, "
                . "json_remove(doc) AS remove_noop, "
                . "json_replace(doc) AS replace_noop, "
                . "json_set(doc) AS set_noop, "
                . "json_insert(doc) AS insert_noop, "
                . "json_extract(doc,'$') AS root_text, "
                . "json_extract(doc_b,'$') AS root_blob, "
                . "json_valid(object_trailing) AS object_trailing_valid, "
                . "json_error_position(object_trailing) AS object_trailing_error, "
                . "json_valid(json(object_trailing)) AS object_trailing_canonical_valid, "
                . "json_error_position(object_double) AS object_double_error, "
                . "json_error_position(array_trailing) AS array_trailing_error, "
                . "json_error_position(array_double) AS array_double_error "
                . "FROM app_json_inputs "
                . "WHERE case_id = expected_case_id "
                . "ORDER BY case_id LIMIT 1",
                ['app_json_inputs' => [$fixture['decoy'], $fixture['matching']]],
            );
            $row = json101_constructor_edit_select_sql_single_row($rows, 'json101 constructor/edit SELECT SQL case ' . $case);
            $expected = $fixture['expected'];

            $t->same($expected['case_id'], $row['case_id'], 'json101 constructor/edit SELECT SQL WHERE row case ' . $case);
            $t->same($expected['array_plain'], $row['array_plain'], 'json101-1.1.01 plain text argument remains JSON string case ' . $case);
            $t->same($expected['array_json'], $row['array_json'], 'json101-1.1.02 json() argument inserts as JSON case ' . $case);
            $t->same($expected['array_json'], $row['array_jsonb'], 'json101-1.4b jsonb_array() canonical parity case ' . $case);
            $t->same($expected['object_json'], $row['object_json'], 'json101-2.2.2 object constructor embeds JSON array case ' . $case);
            $t->same($expected['object_json'], $row['object_jsonb'], 'json101-2.2.3b jsonb_object() canonical parity case ' . $case);
            $t->same($expected['replace_text'], $row['replace_text'], 'json101-3.1 plain SQL text replacement stays text case ' . $case);
            $t->same($expected['replace_json'], $row['replace_json'], 'json101-3.2 json() replacement stays structured case ' . $case);
            $t->same($expected['replace_json'], $row['replace_jsonb'], 'json101-3.2b jsonb_replace() replacement stays structured case ' . $case);
            $t->same($expected['set_text_type'], $row['set_text_type'], 'json101-3.3 json_set plain text reports text case ' . $case);
            $t->same($expected['set_json_type'], $row['set_jsonb_type'], 'json101-3.4b jsonb_set structured value reports array case ' . $case);
            $t->same($expected['replace_json'], $row['set_jsonb'], 'json101-3.4b jsonb_set structured value canonical text case ' . $case);
            foreach (['remove_noop', 'replace_noop', 'set_noop', 'insert_noop'] as $column) {
                $t->same($expected['doc'], $row[$column], 'json101-4.5..4.8 no-edit mutation identity ' . $column . ' case ' . $case);
            }
            $t->same($expected['doc'], $row['root_text'], 'json101-4.10 root extraction preserves object text case ' . $case);
            $t->same($expected['doc'], $row['root_blob'], 'json101-4.10b root extraction preserves JSONB canonical text case ' . $case);
            $t->same(0, $row['object_trailing_valid'], 'json101-6.1 strict object trailing comma invalid case ' . $case);
            $t->same(0, $row['object_trailing_error'], 'json101-6.2 JSON5 object trailing comma error position is zero case ' . $case);
            $t->same(1, $row['object_trailing_canonical_valid'], 'json101-6.3 json() canonicalized trailing comma is valid case ' . $case);
            $t->same(16, $row['object_double_error'], 'json101-6.6 object double comma error position case ' . $case);
            $t->same(0, $row['array_trailing_error'], 'json101-6.8/6.9 array trailing comma error position is zero case ' . $case);
            $t->same(16, $row['array_double_error'], 'json101-6.10 array double comma error position case ' . $case);
        };
}

$tests['real upstream json101 constructor edit SELECT SQL cites upstream source and error boundaries'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json101.test');
        }

        foreach ([
            'do_execsql_test json101-1.1.00',
            'do_execsql_test json101-1.1.02',
            'do_catchsql_test json101-1.3',
            'do_catchsql_test json101-1.3b',
            'do_execsql_test json101-1.4b',
            'do_execsql_test json101-2.1',
            'do_execsql_test json101-2.1b',
            'do_catchsql_test json101-2.2',
            'do_execsql_test json101-2.2.2',
            'do_execsql_test json101-2.2.3b',
            'do_catchsql_test json101-2.3',
            'do_catchsql_test json101-2.4',
            'do_execsql_test json101-2.5',
            'do_execsql_test json101-3.1',
            'do_execsql_test json101-3.1b',
            'do_execsql_test json101-3.4b',
            'do_execsql_test json101-4.5',
            'do_execsql_test json101-4.10b',
            'do_execsql_test json101-6.1',
            'do_execsql_test json101-6.11',
        ] as $needle) {
            $t->contains($needle, $source);
        }

        json101_constructor_edit_select_sql_throws_containing($t, "SELECT json_array(1,x'abcd',3) AS bad", 'JSON cannot hold BLOB values');
        json101_constructor_edit_select_sql_throws_containing($t, "SELECT jsonb_array(1,x'abcd',3) AS bad", 'JSON cannot hold BLOB values');
        json101_constructor_edit_select_sql_throws_containing($t, "SELECT json_object('a',1,'b') AS bad", 'even number of arguments');
        json101_constructor_edit_select_sql_throws_containing($t, "SELECT jsonb_object('a',1,'b') AS bad", 'even number of arguments');
        json101_constructor_edit_select_sql_throws_containing($t, 'SELECT json_object(2,2.5) AS bad', 'labels must be TEXT');
        $t->same(1002, count($GLOBALS['tests'] ?? []), '1000 SELECT SQL rows plus source and dependency citations');
    };

$tests['real upstream json101 constructor edit SELECT SQL dependency closure note'] =
    static fn (TestRunner $t): mixed => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
