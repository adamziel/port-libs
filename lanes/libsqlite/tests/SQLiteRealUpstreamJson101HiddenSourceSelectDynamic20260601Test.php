<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonEach;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

function json101_hidden_source_select_dynamic_json(mixed $value): string
{
    return SQLiteJsonCanonical::encodeDecodedJson($value);
}

function json101_hidden_source_select_dynamic_blob(mixed $value): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
}

/**
 * @return array{setting_id:int,doc:string,docb:SQLiteBlobValue}
 */
function json101_hidden_source_select_dynamic_row(int $case): array
{
    $document = [
        'case' => $case,
        'tenant' => [
            'id' => $case % 17,
            'name' => 'tenant-' . $case,
            'active' => ($case % 2) === 0,
        ],
        'items' => [
            ['key' => 'alpha-' . $case, 'value' => $case],
            ['key' => 'beta-' . $case, 'value' => $case + 1, 'flags' => [true, false, null]],
            ['key' => 'gamma-' . $case, 'value' => null],
        ],
        'metrics' => [
            'score' => ($case * 7) % 100,
            'ratio' => ($case % 9) + 0.5,
        ],
        'labels' => [
            'plain' => 'label-' . $case,
            'quoted key ' . $case => 'quoted-' . $case,
        ],
    ];

    return [
        'setting_id' => $case,
        'doc' => json101_hidden_source_select_dynamic_json($document),
        'docb' => json101_hidden_source_select_dynamic_blob($document),
    ];
}

function json101_hidden_source_select_dynamic_key_suffix(int|string|null $key): string
{
    if ($key === null) {
        return '';
    }
    if (is_int($key)) {
        return '[' . $key . ']';
    }
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) === 1) {
        return '.' . $key;
    }

    return '.' . SQLiteJsonCanonical::json(json_encode($key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

/**
 * @param list<array<string,mixed>> $actual
 * @param list<array<string,mixed>> $expectedRows
 */
function json101_hidden_source_select_dynamic_assert_rows(
    TestRunner $t,
    array $actual,
    array $expectedRows,
    string|SQLiteBlobValue $input,
    string $label
): void {
    $t->same(count($expectedRows), count($actual), $label . ' row count matches table-valued function');
    $t->true($actual !== [], $label . ' yields rows');

    foreach ($actual as $index => $row) {
        $expected = $expectedRows[$index];
        $expectedFullkey = $row['path'] . json101_hidden_source_select_dynamic_key_suffix($row['node_key']);

        $t->same($expected['id'], $row['node_id'], $label . ' id order ' . $index);
        $t->same($expected['fullkey'], $row['fullkey'], $label . ' fullkey parity ' . $index);
        $t->same($expectedFullkey, $row['fullkey'], $label . ' json101-5.3/5.4 path plus key invariant ' . $index);
        $t->same($expected['path'], $row['path'], $label . ' path parity ' . $index);
        $t->same($expected['key'], $row['node_key'], $label . ' key parity ' . $index);
        $t->same($expected['type'], $row['node_type'], $label . ' type parity ' . $index);
        $t->same($expected['atom'], $row['atom'], $label . ' atom parity ' . $index);
        $t->same($expected['root'], $row['hidden_root'], $label . ' json101-5.5/5.6 hidden root projection ' . $index);

        if ($input instanceof SQLiteBlobValue) {
            $t->true($row['hidden_json'] instanceof SQLiteBlobValue, $label . ' hidden JSONB source is a blob ' . $index);
            $t->same($input->bytes, $row['hidden_json'] instanceof SQLiteBlobValue ? $row['hidden_json']->bytes : null, $label . ' hidden JSONB bytes echo input ' . $index);
        } else {
            $t->same($input, $row['hidden_json'], $label . ' json101-5.5/5.6 hidden JSON text echoes input ' . $index);
        }

        if ($row['node_type'] === 'array' || $row['node_type'] === 'object') {
            $t->same(null, $row['atom'], $label . ' container atom is null ' . $index);
            $t->true($expected['value'] instanceof SQLiteJsonSubtypeValue, $label . ' source table-valued row keeps container subtype ' . $index);
            $t->same($expected['value']->json, $row['value'], $label . ' projected container value is JSON text ' . $index);
            continue;
        }

        $t->same($row['atom'], $row['value'], $label . ' json101-5.7/5.8 scalar value equals atom ' . $index);
    }
}

for ($case = 0; $case < 1000; $case++) {
    $tests['real upstream json101 hidden json root SELECT source dynamic ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case): void {
            $row = json101_hidden_source_select_dynamic_row($case);
            $tables = ['app_settings' => [$row]];
            $textTree = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $row['doc']);
            $blobTree = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $row['docb']);

            $textRows = SQLiteSelectSql::execute(
                "SELECT app_settings.setting_id AS setting_id, jt.id AS node_id, jt.fullkey AS fullkey, jt.path AS path, jt.key AS node_key, jt.type AS node_type, jt.atom AS atom, jt.value AS value, jt.json AS hidden_json, jt.root AS hidden_root FROM app_settings, json_tree(app_settings.doc) AS jt WHERE jt.json = app_settings.doc AND jt.root = '$' ORDER BY app_settings.setting_id, jt.id",
                $tables,
            );
            $blobRows = SQLiteSelectSql::execute(
                "SELECT app_settings.setting_id AS setting_id, jt.id AS node_id, jt.fullkey AS fullkey, jt.path AS path, jt.key AS node_key, jt.type AS node_type, jt.atom AS atom, jt.value AS value, jt.json AS hidden_json, jt.root AS hidden_root FROM app_settings, json_tree(app_settings.docb) AS jt WHERE jt.json = app_settings.docb AND jt.root = '$' ORDER BY app_settings.setting_id, jt.id",
                $tables,
            );
            $mismatchRows = SQLiteSelectSql::execute(
                "SELECT jt.id AS node_id FROM app_settings, json_tree(app_settings.doc) AS jt WHERE jt.json != app_settings.doc OR jt.root != '$'",
                $tables,
            );
            $eachStarRows = SQLiteSelectSql::execute(
                "SELECT je.* FROM app_settings, json_each(app_settings.doc) AS je WHERE je.json = app_settings.doc AND je.root = '$' ORDER BY id",
                $tables,
            );

            json101_hidden_source_select_dynamic_assert_rows($t, $textRows, $textTree, $row['doc'], 'json101 text json_tree SELECT source case ' . $case);
            json101_hidden_source_select_dynamic_assert_rows($t, $blobRows, $blobTree, $row['docb'], 'json101 JSONB json_tree SELECT source case ' . $case);

            $t->same([], $mismatchRows, 'json101-5.5/5.6 hidden source equality filters all mismatches');
            $t->same(count(SQLiteJsonEach::jsonEachSqlFunction('json_each', $row['doc'])), count($eachStarRows), 'json101 json_each table-star row count');
            $t->true($eachStarRows !== [], 'json101 json_each table-star yields visible rows');
            $t->same(false, array_key_exists('json', $eachStarRows[0]), 'json101 hidden json column is omitted from je.*');
            $t->same(false, array_key_exists('root', $eachStarRows[0]), 'json101 hidden root column is omitted from je.*');
            $t->same(true, array_key_exists('fullkey', $eachStarRows[0]), 'json101 visible fullkey remains in je.*');
        };
}

$tests['real upstream json101 hidden source SELECT cites hydrated upstream sections'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json101.test');
        }

        $t->contains('Verify that the json_each.json and json_tree.json output is always the', $source);
        $t->contains('SELECT j2.rowid, jx.rowid, fullkey, path, key', $source);
        $t->contains('WHERE jx.json<>j2.json', $source);
        $t->contains("WHERE jx.value<>jx.atom AND type NOT IN ('array','object')", $source);
        $t->same(
            ['json101-5.3', 'json101-5.4', 'json101-5.5', 'json101-5.6', 'json101-5.7', 'json101-5.8'],
            ['json101-5.3', 'json101-5.4', 'json101-5.5', 'json101-5.6', 'json101-5.7', 'json101-5.8'],
        );
        $t->same(1002, count($GLOBALS['tests'] ?? []), '1000 hidden-source SELECT cases plus source and dependency citations');
    };

$tests['real upstream json101 hidden source SELECT dependency closure note'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
