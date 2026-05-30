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

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$canonical = static fn (string|SQLiteBlobValue $json): string => SQLiteJsonCanonical::json($json);

$documents = [];
for ($i = 1; $i <= 160; $i++) {
    $group = $i % 7;
    $strict = [
        'tenant' => [
            'id' => $i,
            'name' => 'tenant-' . $i,
            'flags' => [
                'enabled' => ($i % 2) === 0,
                'archived' => ($i % 5) === 0,
                'load' => $group,
            ],
        ],
        'queue' => [
            ['name' => 'scan', 'ok' => true, 'weight' => $i],
            ['name' => 'rewrite', 'ok' => ($i % 3) === 0, 'weight' => $i + 1],
            ['name' => 'publish', 'ok' => false, 'weight' => $i + 2],
        ],
        'limits' => [
            'posts' => 40 + $i,
            'uploads' => ($i % 4) === 0 ? null : 10 + $group,
            'ratio' => $i + 0.5,
        ],
        'tags' => ['alpha', 'beta-' . $group, 'item-' . $i],
        'note' => 'line-' . $i,
    ];
    $patch = [
        'tenant' => [
            'flags' => [
                'enabled' => true,
                'load' => $group + 10,
            ],
        ],
        'queue' => [
            ['name' => 'scan', 'ok' => true, 'weight' => $i],
            ['name' => 'rewrite', 'ok' => true, 'weight' => $i + 100],
        ],
        'limits' => [
            'posts' => 100 + $i,
            'uploads' => null,
        ],
        'extra' => [
            'batch' => $i,
            'active' => true,
        ],
    ];

    $strictJson = json_encode($strict, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    $patchJson = json_encode($patch, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    $json5 = sprintf(
        '{tenant:{id:%d,name:"tenant-%d",flags:{enabled:%s,archived:%s,load:%d,},},queue:[{name:"scan",ok:true,weight:%d},{name:"rewrite",ok:%s,weight:%d},{name:"publish",ok:false,weight:%d},],limits:{posts:%d,uploads:%s,ratio:%s,},tags:["alpha","beta-%d","item-%d",],note:"line-%d",}',
        $i,
        $i,
        ($i % 2) === 0 ? 'true' : 'false',
        ($i % 5) === 0 ? 'true' : 'false',
        $group,
        $i,
        ($i % 3) === 0 ? 'true' : 'false',
        $i + 1,
        $i + 2,
        40 + $i,
        ($i % 4) === 0 ? 'null' : (string) (10 + $group),
        (string) ($i + 0.5),
        $group,
        $i,
        $i
    );

    $documents['json106-bulk-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = [
        'strict' => $strictJson,
        'json5' => $json5,
        'patch' => $patchJson,
    ];
}

$tests['real upstream json106 bulk invariant validity and canonical jsonb parity'] = static function (TestRunner $t) use ($documents, $jsonb, $canonical): void {
    foreach ($documents as $scenario => $case) {
        $t->same(true, SQLiteJsonValidity::jsonValid($case['strict']), $scenario . ' strict json_valid');
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$case['json5'], 2]), $scenario . ' json5 json_valid flag');
        $t->same($canonical($case['strict']), $canonical($case['json5']), $scenario . ' json5 canonicalizes to strict');

        $blob = $jsonb($case['strict']);
        $t->same(true, $blob instanceof SQLiteBlobValue, $scenario . ' jsonb constructor returns blob');
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 4]), $scenario . ' jsonb superficial valid');
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 8]), $scenario . ' jsonb strict valid');
        $t->same($canonical($case['strict']), $canonical($blob), $scenario . ' jsonb canonical parity');
    }
};

$tests['real upstream json106 bulk json_tree atoms match path extraction'] = static function (TestRunner $t) use ($documents, $jsonb): void {
    foreach ($documents as $scenario => $case) {
        foreach (['strict' => $case['strict'], 'json5' => $case['json5']] as $kind => $json) {
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $json);
            $scalarRows = array_values(array_filter($rows, static fn (array $row): bool => !in_array($row['type'], ['object', 'array'], true)));
            $t->true(count($scalarRows) >= 20, $scenario . ' ' . $kind . ' scalar row count');

            foreach ($scalarRows as $row) {
                $t->same($row['atom'], SQLiteJsonExtract::extract($json, $row['fullkey']), $scenario . ' ' . $kind . ' text atom ' . $row['fullkey']);
                $t->same($row['atom'], SQLiteJsonExtract::extract($jsonb($json), $row['fullkey']), $scenario . ' ' . $kind . ' jsonb atom ' . $row['fullkey']);
            }
        }
    }
};

$tests['real upstream json106 bulk remove then insert restores object scalar leaves'] = static function (TestRunner $t) use ($documents): void {
    foreach ($documents as $scenario => $case) {
        $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $case['json5']);
        foreach ($rows as $row) {
            if (in_array($row['type'], ['object', 'array'], true) || str_contains($row['fullkey'], '[')) {
                continue;
            }

            $removed = SQLiteJsonRemove::remove($case['json5'], $row['fullkey']);
            $t->same(null, SQLiteJsonExtract::extract($removed, $row['fullkey']), $scenario . ' removed ' . $row['fullkey']);
            $restored = SQLiteJsonMutation::mutateSqlFunction('json_insert', $removed, $row['fullkey'], $row['atom']);
            $t->same($row['atom'], SQLiteJsonExtract::extract($restored, $row['fullkey']), $scenario . ' restored ' . $row['fullkey']);
            $t->same(true, SQLiteJsonValidity::jsonValid($restored), $scenario . ' restored valid ' . $row['fullkey']);
        }
    }
};

$tests['real upstream json106 bulk merge patch and pretty round trip parity'] = static function (TestRunner $t) use ($documents, $jsonb, $canonical): void {
    foreach ($documents as $scenario => $case) {
        $patchedText = SQLiteJsonPatch::patch($case['strict'], $case['patch']);
        $patchedBlob = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $jsonb($case['strict']), $jsonb($case['patch']));
        $t->same(true, $patchedBlob instanceof SQLiteBlobValue, $scenario . ' jsonb patch result');
        $t->same($canonical($patchedText), $canonical($patchedBlob), $scenario . ' jsonb patch canonical parity');
        $t->same(true, SQLiteJsonValidity::jsonValid($patchedText), $scenario . ' patched text valid');

        foreach (SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $case['patch']) as $row) {
            if (in_array($row['type'], ['object', 'array'], true) || str_contains($row['fullkey'], '[') || $row['atom'] === null) {
                continue;
            }
            $t->same($row['atom'], SQLiteJsonExtract::extract($patchedText, $row['fullkey']), $scenario . ' patch atom ' . $row['fullkey']);
        }

        foreach ([null, '', '  ', "\t"] as $indent) {
            $prettyText = $indent === null
                ? SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$case['strict'], null])
                : SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$case['strict'], $indent]);
            $prettyBlob = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$jsonb($case['strict']), $indent]);
            $t->same($canonical($case['strict']), $canonical($prettyText), $scenario . ' pretty text indent ' . var_export($indent, true));
            $t->same($canonical($case['strict']), $canonical($prettyBlob), $scenario . ' pretty jsonb indent ' . var_export($indent, true));
        }
    }
};

return $tests;
