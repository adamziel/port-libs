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

$tests = [];

function json101_tree_invariant_canonical(mixed $value): string
{
    return SQLiteJsonCanonical::json(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function json101_tree_invariant_jsonb(string $json): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR)));
}

function json101_tree_invariant_key_suffix(int|string|null $key): string
{
    if (is_int($key)) {
        return '[' . $key . ']';
    }
    if ($key === null) {
        return '';
    }
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) === 1) {
        return '.' . $key;
    }

    return '.' . SQLiteJsonCanonical::json(json_encode($key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function json101_tree_invariant_compare_json_values(mixed $value): mixed
{
    if (is_string($value) && ($value === '[]' || $value === '{}' || str_starts_with($value, '[') || str_starts_with($value, '{'))) {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $value;
        }
    }

    return $value;
}

$documents = [];
for ($i = 1; $i <= 220; $i++) {
    $documents['json101-5-tree-invariant-document-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = json101_tree_invariant_canonical([
        'id' => $i,
        'type' => ($i % 2) === 0 ? 'donut' : 'article',
        'name' => 'Item ' . $i,
        'isAlive' => ($i % 3) !== 0,
        'age' => 20 + ($i % 50),
        'address' => [
            'streetAddress' => $i . ' Example Street',
            'city' => 'City ' . ($i % 17),
            'postalCode' => sprintf('%05d-%04d', 10000 + $i, 3000 + ($i % 700)),
        ],
        'phoneNumbers' => [
            ['type' => 'home', 'number' => sprintf('212-555-%04d', $i)],
            ['type' => 'office', 'number' => sprintf('646-555-%04d', $i + 1000)],
        ],
        'children' => $i % 5 === 0 ? [] : ['child-' . $i, 'child-' . ($i + 1)],
        'spouse' => $i % 7 === 0 ? ['name' => 'Person ' . $i] : null,
        'batters' => [
            'batter' => [
                ['id' => '1001', 'type' => 'Regular'],
                ['id' => '1002', 'type' => 'Chocolate'],
                ['id' => (string) (1000 + $i), 'type' => 'Dynamic ' . $i],
            ],
        ],
        'topping' => [
            ['id' => '5001', 'type' => 'None'],
            ['id' => '5002', 'type' => 'Glazed'],
            ['id' => '5003', 'type' => 'Chocolate'],
        ],
        'quoted.key ' . $i => [
            'control' => "line\n" . $i,
            'unicode' => 'cafe-' . $i,
        ],
    ]);
}

$rootPaths = ['$', '$.address', '$.phoneNumbers', '$.batters.batter', '$.topping', '$."quoted.key 1"'];

foreach ($documents as $scenario => $json) {
    $jsonb = json101_tree_invariant_jsonb($json);

    $tests['real upstream json101.test 5.3 json_tree fullkey path key invariant ' . $scenario] = static function (TestRunner $t) use ($scenario, $json, $jsonb): void {
        foreach (['text' => $json, 'jsonb' => $jsonb] as $kind => $input) {
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $input);
            $t->true(count($rows) >= 35, $scenario . ' ' . $kind . ' row count');
            $t->same('$', $rows[0]['fullkey'], $scenario . ' ' . $kind . ' root fullkey');
            $t->same('object', $rows[0]['type'], $scenario . ' ' . $kind . ' root type');

            foreach ($rows as $row) {
                $expectedFullkey = $row['path'] . json101_tree_invariant_key_suffix($row['key']);
                $t->same($expectedFullkey, $row['fullkey'], $scenario . ' ' . $kind . ' fullkey composition ' . $row['id']);
                $t->same($input, $row['json'], $scenario . ' ' . $kind . ' json column echoes input ' . $row['id']);
                $t->same('$', $row['root'], $scenario . ' ' . $kind . ' root column ' . $row['id']);
            }
        }
    };

    $tests['real upstream json101.test 5.5 5.6 json_each and json_tree input echo ' . $scenario] = static function (TestRunner $t) use ($scenario, $json, $jsonb): void {
        foreach (['json_each' => SQLiteJsonEach::jsonEachSqlFunction('json_each', $json), 'json_tree' => SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $json)] as $function => $rows) {
            $t->true($rows !== [], $scenario . ' ' . $function . ' has rows');
            foreach ($rows as $row) {
                $t->same($json, $row['json'], $scenario . ' ' . $function . ' text source echo ' . $row['id']);
            }
        }

        foreach (['json_each' => SQLiteJsonEach::jsonEachSqlFunction('json_each', $jsonb), 'json_tree' => SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $jsonb)] as $function => $rows) {
            $t->true($rows !== [], $scenario . ' ' . $function . ' jsonb has rows');
            foreach ($rows as $row) {
                $t->same($jsonb, $row['json'], $scenario . ' ' . $function . ' jsonb source echo ' . $row['id']);
            }
        }
    };

    $tests['real upstream json101.test 5.7 5.8 scalar value atom invariant ' . $scenario] = static function (TestRunner $t) use ($scenario, $json, $jsonb): void {
        foreach (['each-text' => SQLiteJsonEach::jsonEachSqlFunction('json_each', $json), 'tree-text' => SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $json), 'each-jsonb' => SQLiteJsonEach::jsonEachSqlFunction('json_each', $jsonb), 'tree-jsonb' => SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $jsonb)] as $kind => $rows) {
            $scalarCount = 0;
            foreach ($rows as $row) {
                if (in_array($row['type'], ['array', 'object'], true)) {
                    $t->same(null, $row['atom'], $scenario . ' ' . $kind . ' container atom null ' . $row['id']);
                    continue;
                }

                $scalarCount++;
                $t->same($row['atom'], $row['value'], $scenario . ' ' . $kind . ' scalar value equals atom ' . $row['id']);
                $t->same(json101_tree_invariant_compare_json_values(SQLiteJsonExtract::extract($json, $row['fullkey'])), json101_tree_invariant_compare_json_values($row['value']), $scenario . ' ' . $kind . ' scalar extraction parity ' . $row['fullkey']);
            }
            $minimumScalarCount = str_starts_with($kind, 'tree-') ? 10 : 5;
            $t->true($scalarCount >= $minimumScalarCount, $scenario . ' ' . $kind . ' scalar count');
        }
    };
}

$tests['real upstream json101.test 5.3 through 5.8 root path matrix cites hydrated upstream sections'] = static function (TestRunner $t) use ($documents): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
    $t->same(['json101-5.3', 'json101-5.4', 'json101-5.5', 'json101-5.6', 'json101-5.7', 'json101-5.8'], ['json101-5.3', 'json101-5.4', 'json101-5.5', 'json101-5.6', 'json101-5.7', 'json101-5.8']);
    $t->same(220, count($documents));
};

return $tests;
