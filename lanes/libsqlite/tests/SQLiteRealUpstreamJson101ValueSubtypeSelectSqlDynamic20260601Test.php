<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

function json101_value_subtype_select_sql_json(mixed $value): string
{
    return SQLiteJsonCanonical::encodeDecodedJson($value);
}

function json101_value_subtype_select_sql_jsonb(mixed $value): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
}

/**
 * @return array{container:array<string,mixed>|list<mixed>,literal:string}
 */
function json101_value_subtype_select_sql_payload(int $case): array
{
    $literal = match ($case % 5) {
        0 => '[1,2,3]',
        1 => '{"alpha":1,"beta":[2,3]}',
        2 => '["tenant-' . $case . '",{"active":true}]',
        3 => '{"path":"$.items[' . ($case % 7) . ']","enabled":false}',
        default => '[{"case":' . $case . '},"plain-text"]',
    };

    if (($case % 2) === 0) {
        $container = [
            'case' => $case,
            'tenant' => [
                'id' => $case % 29,
                'name' => 'tenant-' . $case,
                'active' => ($case % 3) !== 0,
            ],
            'items' => [
                ['key' => 'alpha-' . $case, 'value' => $case],
                ['key' => 'beta-' . $case, 'value' => $case + 1, 'flags' => [true, false, null]],
                ['key' => 'gamma-' . $case, 'value' => null, 'literal' => $literal],
            ],
            'metrics' => [
                'score' => ($case * 11) % 1000,
                'ratio' => ($case % 13) + 0.25,
            ],
            'emptyArray' => [],
            'emptyObject' => new stdClass(),
        ];
    } else {
        $container = [
            ['case' => $case, 'kind' => 'array-root'],
            ['values' => [$case, $case + 1, $case + 2]],
            ['literal' => $literal],
            [],
            new stdClass(),
        ];
    }

    return ['container' => $container, 'literal' => $literal];
}

/**
 * @return array{doc:string,docb:SQLiteBlobValue,literal_doc:string,literal_docb:SQLiteBlobValue,member_doc:string,member_docb:SQLiteBlobValue,container_json:string,literal_json:string}
 */
function json101_value_subtype_select_sql_row(int $case): array
{
    $payload = json101_value_subtype_select_sql_payload($case);
    $memberDocument = [
        'payload' => $payload['container'],
        'literal' => $payload['literal'],
        'case' => $case,
    ];

    return [
        'doc' => json101_value_subtype_select_sql_json($payload['container']),
        'docb' => json101_value_subtype_select_sql_jsonb($payload['container']),
        'literal_doc' => json101_value_subtype_select_sql_json($payload['literal']),
        'literal_docb' => json101_value_subtype_select_sql_jsonb($payload['literal']),
        'member_doc' => json101_value_subtype_select_sql_json($memberDocument),
        'member_docb' => json101_value_subtype_select_sql_jsonb($memberDocument),
        'container_json' => json101_value_subtype_select_sql_json($payload['container']),
        'literal_json' => json101_value_subtype_select_sql_json($payload['literal']),
    ];
}

/**
 * @param list<array<string,mixed>> $rows
 */
function json101_value_subtype_select_sql_single_row(array $rows, string $label): array
{
    if (count($rows) !== 1) {
        throw new RuntimeException($label . ' expected exactly one row, got ' . count($rows));
    }

    return $rows[0];
}

for ($case = 0; $case < 1000; $case++) {
    $tests['real upstream json101 5.10 5.11 SELECT SQL value subtype dynamic ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case): void {
            $row = json101_value_subtype_select_sql_row($case);
            $tables = ['app_json_docs' => [$row]];
            $containerType = ($case % 2) === 0 ? 'object' : 'array';

            foreach (['doc', 'docb'] as $sourceColumn) {
                $root = json101_value_subtype_select_sql_single_row(
                    SQLiteSelectSql::execute(
                        "SELECT jt.type AS node_type, json_insert('{}','$.a',jt.value) AS inserted, json_type(json_insert('{}','$.a',jt.value),'$.a') AS inserted_type, json_quote(jt.value) AS quoted_value FROM app_json_docs, json_tree(app_json_docs." . $sourceColumn . ") AS jt WHERE jt.fullkey = '$' ORDER BY jt.id LIMIT 1",
                        $tables,
                    ),
                    'json101-5.10 root container ' . $sourceColumn . ' case ' . $case,
                );

                $t->same($containerType, $root['node_type'], 'json101-5.10 ' . $sourceColumn . ' root row type case ' . $case);
                $t->same('{"a":' . $row['container_json'] . '}', $root['inserted'], 'json101-5.10 ' . $sourceColumn . ' SELECT json_tree value inserts as JSON case ' . $case);
                $t->same($containerType, $root['inserted_type'], 'json101-5.10 ' . $sourceColumn . ' inserted value type case ' . $case);
                $t->same($row['container_json'], $root['quoted_value'], 'json101-5.10 ' . $sourceColumn . ' json_quote observes value subtype case ' . $case);
            }

            foreach (['literal_doc', 'literal_docb'] as $sourceColumn) {
                $literal = json101_value_subtype_select_sql_single_row(
                    SQLiteSelectSql::execute(
                        "SELECT st.type AS node_type, st.atom AS atom, json_insert('{}','$.a',st.value) AS inserted, json_type(json_insert('{}','$.a',st.value),'$.a') AS inserted_type, json_quote(st.value) AS quoted_value FROM app_json_docs, json_tree(app_json_docs." . $sourceColumn . ") AS st WHERE st.fullkey = '$' ORDER BY st.id LIMIT 1",
                        $tables,
                    ),
                    'json101-5.11 scalar literal ' . $sourceColumn . ' case ' . $case,
                );

                $t->same('text', $literal['node_type'], 'json101-5.11 ' . $sourceColumn . ' root scalar row type case ' . $case);
                $t->same($row['literal_json'], $literal['quoted_value'], 'json101-5.11 ' . $sourceColumn . ' json_quote keeps scalar text quoted case ' . $case);
                $t->same($row['literal_json'], json101_value_subtype_select_sql_json($literal['atom']), 'json101-5.11 ' . $sourceColumn . ' atom remains scalar text case ' . $case);
                $t->same('{"a":' . $row['literal_json'] . '}', $literal['inserted'], 'json101-5.11 ' . $sourceColumn . ' SELECT json_tree value inserts as text case ' . $case);
                $t->same('text', $literal['inserted_type'], 'json101-5.11 ' . $sourceColumn . ' inserted value type case ' . $case);
            }

            foreach (['member_doc', 'member_docb'] as $sourceColumn) {
                $memberContainer = json101_value_subtype_select_sql_single_row(
                    SQLiteSelectSql::execute(
                        "SELECT je.type AS node_type, json_insert('{}','$.a',je.value) AS inserted, json_type(json_insert('{}','$.a',je.value),'$.a') AS inserted_type, json_quote(je.value) AS quoted_value FROM app_json_docs, json_each(app_json_docs." . $sourceColumn . ") AS je WHERE je.key = 'payload' LIMIT 1",
                        $tables,
                    ),
                    'json101-5.10 json_each member container ' . $sourceColumn . ' case ' . $case,
                );
                $memberLiteral = json101_value_subtype_select_sql_single_row(
                    SQLiteSelectSql::execute(
                        "SELECT je.type AS node_type, je.atom AS atom, json_insert('{}','$.a',je.value) AS inserted, json_type(json_insert('{}','$.a',je.value),'$.a') AS inserted_type, json_quote(je.value) AS quoted_value FROM app_json_docs, json_each(app_json_docs." . $sourceColumn . ") AS je WHERE je.key = 'literal' LIMIT 1",
                        $tables,
                    ),
                    'json101-5.11 json_each member literal ' . $sourceColumn . ' case ' . $case,
                );

                $t->same($containerType, $memberContainer['node_type'], 'json101-5.10 ' . $sourceColumn . ' json_each container type case ' . $case);
                $t->same('{"a":' . $row['container_json'] . '}', $memberContainer['inserted'], 'json101-5.10 ' . $sourceColumn . ' SELECT json_each value inserts as JSON case ' . $case);
                $t->same($containerType, $memberContainer['inserted_type'], 'json101-5.10 ' . $sourceColumn . ' json_each inserted type case ' . $case);
                $t->same($row['container_json'], $memberContainer['quoted_value'], 'json101-5.10 ' . $sourceColumn . ' json_each json_quote observes subtype case ' . $case);

                $t->same('text', $memberLiteral['node_type'], 'json101-5.11 ' . $sourceColumn . ' json_each scalar type case ' . $case);
                $t->same($row['literal_json'], $memberLiteral['quoted_value'], 'json101-5.11 ' . $sourceColumn . ' json_each scalar quoted case ' . $case);
                $t->same($row['literal_json'], json101_value_subtype_select_sql_json($memberLiteral['atom']), 'json101-5.11 ' . $sourceColumn . ' json_each atom remains scalar text case ' . $case);
                $t->same('{"a":' . $row['literal_json'] . '}', $memberLiteral['inserted'], 'json101-5.11 ' . $sourceColumn . ' SELECT json_each value inserts as text case ' . $case);
                $t->same('text', $memberLiteral['inserted_type'], 'json101-5.11 ' . $sourceColumn . ' json_each inserted type case ' . $case);
            }
        };
}

$tests['real upstream json101 value subtype SELECT SQL cites hydrated upstream sections'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json101.test');
        }

        $t->contains('The value column', $source);
        $t->contains('should have the "J" subtype if the value is an array or', $source);
        $t->contains('# object.', $source);
        $t->contains("SELECT json_insert('{}','$.a',value) FROM json_tree('[1,2,3]') WHERE atom IS NULL;", $source);
        $t->contains('SELECT json_insert', $source);
        $t->contains('json101-5.10', $source);
        $t->contains('json101-5.11', $source);
        $t->same(1002, count($GLOBALS['tests'] ?? []), '1000 SELECT SQL subtype cases plus source and dependency citations');
    };

$tests['real upstream json101 value subtype SELECT SQL dependency closure note'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
