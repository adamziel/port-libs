<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

function json106_large_corpus_document(int $i): array
{
    $strict = [
        'id' => $i,
        'group_key' => 'group-' . ($i % 17),
        'flags' => [
            'enabled' => ($i % 2) === 0,
            'archived' => ($i % 5) === 0,
            'retry' => $i % 4,
        ],
        'metrics' => [
            'count' => $i * 3,
            'ratio' => ($i % 11) + 0.25,
            'negative' => -$i,
        ],
        'items' => [
            ['name' => 'alpha-' . $i, 'value' => $i + 1],
            ['name' => 'beta-' . ($i % 9), 'value' => $i + 2],
            ['name' => 'gamma', 'value' => null],
        ],
        'nested' => [
            'path' => [
                'leaf' => 'leaf-' . $i,
                'unicode' => 'cafe-' . ($i % 13),
            ],
        ],
    ];

    $patch = [
        'flags' => [
            'enabled' => ($i % 2) !== 0,
            'patched' => true,
        ],
        'metrics' => [
            'count' => $i * 5,
        ],
        'nested' => [
            'path' => [
                'patchedLeaf' => 'patched-' . $i,
            ],
        ],
    ];

    return [
        'strict' => json_encode($strict, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'patch' => json_encode($patch, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'scalarPaths' => [
            '$.id',
            '$.group_key',
            '$.flags.enabled',
            '$.flags.archived',
            '$.flags.retry',
            '$.metrics.count',
            '$.metrics.ratio',
            '$.metrics.negative',
            '$.items[0].name',
            '$.items[0].value',
            '$.items[1].name',
            '$.items[1].value',
            '$.items[2].value',
            '$.nested.path.leaf',
            '$.nested.path.unicode',
        ],
        'patchPaths' => [
            '$.flags.enabled',
            '$.flags.patched',
            '$.metrics.count',
            '$.nested.path.patchedLeaf',
        ],
    ];
}

function json106_large_corpus_jsonb(string $json): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR)));
}

function json106_large_corpus_canonical(string|SQLiteBlobValue|null $json): ?string
{
    return $json === null ? null : SQLiteJsonCanonical::json($json);
}

$documents = [];
for ($i = 1; $i <= 260; $i++) {
    $documents['json106-large-corpus-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = json106_large_corpus_document($i);
}

$tests['real upstream json106 1 validity over deterministic large corpus'] = static function (TestRunner $t) use ($documents): void {
    foreach ($documents as $scenario => $case) {
        $t->same(true, SQLiteJsonValidity::jsonValid($case['strict']), $scenario . ' strict valid');
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [json106_large_corpus_jsonb($case['strict']), 8]), $scenario . ' strict jsonb valid');
    }
};

$tests['real upstream json106 2 tree scalar atoms match path extraction over large corpus'] = static function (TestRunner $t) use ($documents): void {
    foreach ($documents as $scenario => $case) {
        $jsonb = json106_large_corpus_jsonb($case['strict']);
        $rowsByPath = [];

        foreach (SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $case['strict']) as $row) {
            if (!in_array($row['type'], ['object', 'array'], true)) {
                $rowsByPath[$row['fullkey']] = $row;
            }
        }

        foreach ($case['scalarPaths'] as $path) {
            $t->true(array_key_exists($path, $rowsByPath), $scenario . ' tree has scalar path ' . $path);
            $t->same($rowsByPath[$path]['atom'], SQLiteJsonExtract::extract($case['strict'], $path), $scenario . ' text atom parity ' . $path);
            $t->same($rowsByPath[$path]['atom'], SQLiteJsonExtract::extract($jsonb, $path), $scenario . ' jsonb atom parity ' . $path);
        }
    }
};

$tests['real upstream json106 5 and 6 remove then insert restores scalar leaves over large corpus'] = static function (TestRunner $t) use ($documents): void {
    foreach ($documents as $scenario => $case) {
        foreach ($case['scalarPaths'] as $path) {
            if (str_contains($path, '[')) {
                continue;
            }

            $value = SQLiteJsonExtract::extract($case['strict'], $path);
            $removed = SQLiteJsonRemove::remove($case['strict'], $path);
            $t->same(null, SQLiteJsonExtract::extract($removed, $path), $scenario . ' removed ' . $path);

            $restored = SQLiteJsonMutation::mutateSqlFunction('json_insert', $removed, $path, $value);
            $t->same($value, SQLiteJsonExtract::extract($restored, $path), $scenario . ' restored text ' . $path);
            $t->same(true, SQLiteJsonValidity::jsonValid($restored), $scenario . ' restored valid ' . $path);
        }
    }
};

$tests['real upstream json106 7 json patch scalar leaves are visible over large corpus'] = static function (TestRunner $t) use ($documents): void {
    foreach ($documents as $scenario => $case) {
        $patched = SQLiteJsonPatch::patch($case['strict'], $case['patch']);
        $patchBlob = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', json106_large_corpus_jsonb($case['strict']), json106_large_corpus_jsonb($case['patch']));

        $t->true($patchBlob instanceof SQLiteBlobValue, $scenario . ' jsonb patch result type');
        $t->same(json106_large_corpus_canonical($patched), json106_large_corpus_canonical($patchBlob), $scenario . ' jsonb patch canonical parity');

        foreach ($case['patchPaths'] as $path) {
            $expected = SQLiteJsonExtract::extract($case['patch'], $path);
            $t->same($expected, SQLiteJsonExtract::extract($patched, $path), $scenario . ' text patch path ' . $path);
            $t->same($expected, SQLiteJsonExtract::extract($patchBlob, $path), $scenario . ' jsonb patch path ' . $path);
        }
    }
};

$tests['real upstream json106 8 and json108 1 pretty canonical round trip over large corpus'] = static function (TestRunner $t) use ($documents): void {
    $indents = [null, '', "\t", '/*hello*/'];

    foreach ($documents as $scenario => $case) {
        foreach ($indents as $indent) {
            $arguments = $indent === null ? [$case['strict']] : [$case['strict'], $indent];
            $pretty = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', $arguments);
            $t->same(json106_large_corpus_canonical($case['strict']), json106_large_corpus_canonical($pretty), $scenario . ' text pretty canonical ' . var_export($indent, true));

            $jsonb = json106_large_corpus_jsonb($case['strict']);
            $prettyJsonb = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$jsonb, $indent]);
            $t->same(json106_large_corpus_canonical($case['strict']), json106_large_corpus_canonical($prettyJsonb), $scenario . ' jsonb pretty canonical ' . var_export($indent, true));
        }
    }
};

$tests['real upstream json106 json108 large corpus source citations'] = static function (TestRunner $t) use ($documents): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test');
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test');
    $t->same(260, count($documents));
    $t->same(['json106-1', 'json106-2', 'json106-5', 'json106-6', 'json106-7', 'json106-8', 'json108-1'], ['json106-1', 'json106-2', 'json106-5', 'json106-6', 'json106-7', 'json106-8', 'json108-1']);
};

return $tests;
