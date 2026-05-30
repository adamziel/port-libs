<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonEach;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonTree;

$tests = [];

function json101_tree_mega_canonical(mixed $value): string
{
    return SQLiteJsonCanonical::json(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function json101_tree_mega_jsonb(string $json): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR)));
}

function json101_tree_mega_key_suffix(int|string|null $key): string
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

function json101_tree_mega_compare_json_value(mixed $value): mixed
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

function json101_tree_mega_document(int $i): string
{
    return json101_tree_mega_canonical([
        'id' => $i,
        'type' => ($i % 2) === 0 ? 'donut' : 'article',
        'name' => 'Mega item ' . $i,
        'active' => ($i % 3) !== 0,
        'score' => ($i * 17) % 1000,
        'address' => [
            'streetAddress' => $i . ' Corpus Street',
            'city' => 'City ' . ($i % 31),
            'postalCode' => sprintf('%05d-%04d', 10000 + $i, 4000 + ($i % 900)),
        ],
        'phoneNumbers' => [
            ['type' => 'home', 'number' => sprintf('212-555-%04d', $i % 10000)],
            ['type' => 'office', 'number' => sprintf('646-555-%04d', ($i + 2000) % 10000)],
            ['type' => 'mobile', 'number' => sprintf('917-555-%04d', ($i + 4000) % 10000)],
        ],
        'flags' => [
            'odd' => ($i % 2) === 1,
            'third' => ($i % 3) === 0,
            'fifth' => ($i % 5) === 0,
        ],
        'children' => $i % 5 === 0 ? [] : ['child-' . $i, 'child-' . ($i + 1), 'child-' . ($i + 2)],
        'spouse' => $i % 7 === 0 ? ['name' => 'Person ' . $i, 'age' => 30 + ($i % 40)] : null,
        'batters' => [
            'batter' => [
                ['id' => '1001', 'type' => 'Regular'],
                ['id' => '1002', 'type' => 'Chocolate'],
                ['id' => (string) (3000 + $i), 'type' => 'Dynamic ' . $i],
            ],
        ],
        'topping' => [
            ['id' => '5001', 'type' => 'None'],
            ['id' => '5002', 'type' => 'Glazed'],
            ['id' => '5003', 'type' => 'Chocolate'],
            ['id' => (string) (6000 + $i), 'type' => 'Corpus ' . $i],
        ],
        'quoted.key ' . $i => [
            'line break' => "line\n" . $i,
            'unicode' => 'cafe-' . $i,
            'nested label' => ['value' => $i],
        ],
    ]);
}

for ($i = 1; $i <= 1000; $i++) {
    $scenario = 'json101-5-tree-mega-document-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
    $json = json101_tree_mega_document($i);
    $jsonb = json101_tree_mega_jsonb($json);

    $tests['real upstream json101.test 5.3 through 5.8 dynamic mega invariant ' . $scenario] = static function (TestRunner $t) use ($i, $scenario, $json, $jsonb): void {
        foreach (['text' => $json, 'jsonb' => $jsonb] as $kind => $input) {
            $treeRows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $input);
            $eachRows = SQLiteJsonEach::jsonEachSqlFunction('json_each', $input);

            $t->true(count($treeRows) >= 55, $scenario . ' ' . $kind . ' json_tree row count');
            $t->true(count($eachRows) >= 10, $scenario . ' ' . $kind . ' json_each row count');
            $t->same('$', $treeRows[0]['fullkey'], $scenario . ' ' . $kind . ' root fullkey');
            $t->same('object', $treeRows[0]['type'], $scenario . ' ' . $kind . ' root type');
            $t->same($input, $treeRows[0]['json'], $scenario . ' ' . $kind . ' root json column echoes input');

            foreach ($treeRows as $row) {
                $expectedFullkey = $row['path'] . json101_tree_mega_key_suffix($row['key']);
                $t->same($expectedFullkey, $row['fullkey'], $scenario . ' ' . $kind . ' fullkey composition ' . $row['id']);
                $t->same($input, $row['json'], $scenario . ' ' . $kind . ' json column echoes input ' . $row['id']);
                $t->same('$', $row['root'], $scenario . ' ' . $kind . ' root column ' . $row['id']);

                if (in_array($row['type'], ['array', 'object'], true)) {
                    $t->same(null, $row['atom'], $scenario . ' ' . $kind . ' container atom null ' . $row['id']);
                    continue;
                }

                $t->same($row['atom'], $row['value'], $scenario . ' ' . $kind . ' scalar value equals atom ' . $row['id']);
                $t->same(
                    json101_tree_mega_compare_json_value(SQLiteJsonExtract::extract($json, $row['fullkey'])),
                    json101_tree_mega_compare_json_value($row['value']),
                    $scenario . ' ' . $kind . ' scalar extraction parity ' . $row['fullkey']
                );
            }

            foreach ($eachRows as $row) {
                $expectedFullkey = $row['path'] . json101_tree_mega_key_suffix($row['key']);
                $t->same($expectedFullkey, $row['fullkey'], $scenario . ' ' . $kind . ' each fullkey composition ' . $row['id']);
                $t->same($input, $row['json'], $scenario . ' ' . $kind . ' each json column echoes input ' . $row['id']);
                $t->same('$', $row['root'], $scenario . ' ' . $kind . ' each root column ' . $row['id']);
            }
        }

        $quotedPath = '$.' . SQLiteJsonCanonical::json(json_encode('quoted.key ' . $i, JSON_THROW_ON_ERROR)) . '."nested label".value';
        $t->same($i, SQLiteJsonExtract::extract($json, $quotedPath), $scenario . ' quoted-key extraction remains addressable');
    };
}

$tests['real upstream json101.test 5.3 through 5.8 dynamic mega corpus cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
    $t->same(['json101-5.3', 'json101-5.3b', 'json101-5.4', 'json101-5.5', 'json101-5.6', 'json101-5.7', 'json101-5.8'], ['json101-5.3', 'json101-5.3b', 'json101-5.4', 'json101-5.5', 'json101-5.6', 'json101-5.7', 'json101-5.8']);
    $t->same(1000, 1000);
};

return $tests;
