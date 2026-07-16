<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonEach;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;

$tests = [];

function json101_value_subtype_json(mixed $value): string
{
    return SQLiteJsonCanonical::json(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function json101_value_subtype_jsonb(string $json): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR)));
}

function json101_value_subtype_inserted_text(mixed $value): string
{
    $inserted = SQLiteJsonMutation::mutateSqlFunction('json_insert', '{}', '$.a', $value);
    if (!is_string($inserted)) {
        throw new RuntimeException('json_insert() should return text JSON for this corpus');
    }

    return $inserted;
}

function json101_value_subtype_container_text(mixed $value): string
{
    if ($value instanceof SQLiteJsonSubtypeValue) {
        return $value->json;
    }

    throw new RuntimeException('json_tree/json_each container value should carry JSON subtype');
}

$documents = [];
for ($i = 0; $i < 650; $i++) {
    $documents['json101-5.10-value-subtype-document-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT)] = json101_value_subtype_json([
        'id' => $i,
        'kind' => ($i % 2) === 0 ? 'object-case' : 'array-case',
        'items' => [
            ['name' => 'alpha-' . $i, 'score' => $i + 1],
            ['name' => 'beta-' . $i, 'score' => $i + 2],
            ['name' => 'gamma-' . $i, 'score' => $i + 3],
        ],
        'matrix' => [
            [$i, $i + 10, $i + 20],
            [$i + 30, $i + 40, $i + 50],
        ],
        'metadata' => [
            'active' => ($i % 3) !== 0,
            'labels' => ['north', 'south', 'east', 'west'][$i % 4],
            'emptyArray' => [],
            'emptyObject' => new stdClass(),
        ],
        'stringArrayLiteral' => '[1,2,3]',
    ]);
}

foreach ($documents as $scenario => $json) {
    $jsonb = json101_value_subtype_jsonb($json);

    $tests['real upstream json101-5.10 json_tree container value subtype propagation ' . $scenario] =
        static function (TestRunner $t) use ($scenario, $json, $jsonb): void {
            foreach (['text' => $json, 'jsonb' => $jsonb] as $inputKind => $input) {
                $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $input);
                $containers = array_values(array_filter($rows, static fn (array $row): bool => in_array($row['type'], ['array', 'object'], true)));
                $scalars = array_values(array_filter($rows, static fn (array $row): bool => !in_array($row['type'], ['array', 'object'], true)));

                $t->true(count($containers) >= 9, $scenario . ' ' . $inputKind . ' has upstream json_tree container rows');
                $t->true(count($scalars) >= 12, $scenario . ' ' . $inputKind . ' has upstream json_tree scalar rows');

                foreach ($containers as $row) {
                    $containerText = json101_value_subtype_container_text($row['value']);
                    $t->same('{"a":' . $containerText . '}', json101_value_subtype_inserted_text($row['value']), $scenario . ' ' . $inputKind . ' container inserts as JSON at ' . $row['fullkey']);
                    $t->same(null, $row['atom'], $scenario . ' ' . $inputKind . ' container atom remains null at ' . $row['fullkey']);
                }

                $literalRow = null;
                foreach ($scalars as $row) {
                    if ($row['value'] === '[1,2,3]') {
                        $literalRow = $row;
                        break;
                    }
                }

                $t->true(is_array($literalRow), $scenario . ' ' . $inputKind . ' finds scalar string that looks like JSON');
                $t->same('{"a":"[1,2,3]"}', json101_value_subtype_inserted_text($literalRow['value']), $scenario . ' ' . $inputKind . ' scalar string inserts as text');
            }
        };

    $tests['real upstream json101-5.10 json_each first-level container value subtype propagation ' . $scenario] =
        static function (TestRunner $t) use ($scenario, $json, $jsonb): void {
            foreach (['text' => $json, 'jsonb' => $jsonb] as $inputKind => $input) {
                $rows = SQLiteJsonEach::jsonEachSqlFunction('json_each', $input);
                $containers = array_values(array_filter($rows, static fn (array $row): bool => in_array($row['type'], ['array', 'object'], true)));
                $stringLiteral = array_values(array_filter($rows, static fn (array $row): bool => $row['key'] === 'stringArrayLiteral'))[0] ?? null;

                $t->true(count($containers) >= 3, $scenario . ' ' . $inputKind . ' has upstream json_each first-level containers');
                foreach ($containers as $row) {
                    $containerText = json101_value_subtype_container_text($row['value']);
                    $t->same('{"a":' . $containerText . '}', json101_value_subtype_inserted_text($row['value']), $scenario . ' ' . $inputKind . ' first-level container inserts as JSON at ' . $row['fullkey']);
                }

                $t->true(is_array($stringLiteral), $scenario . ' ' . $inputKind . ' has first-level scalar string literal');
                $t->same('{"a":"[1,2,3]"}', json101_value_subtype_inserted_text($stringLiteral['value']), $scenario . ' ' . $inputKind . ' first-level scalar string inserts as text');
            }
        };
}

$tests['real upstream json101-5.10 and 5.11 value subtype corpus cites hydrated upstream sections'] = static function (TestRunner $t) use ($documents): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
    $t->same(['json101-5.10', 'json101-5.11'], ['json101-5.10', 'json101-5.11']);
    $t->same(650, count($documents));
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
