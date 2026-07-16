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
$jsonText = static fn (string|SQLiteBlobValue|null $value): ?string => $value instanceof SQLiteBlobValue
    ? SQLiteJsonCanonical::json($value)
    : $value;
$canonical = static fn (string|SQLiteBlobValue|null $value): ?string => $value === null
    ? null
    : SQLiteJsonCanonical::json($value);

$documents = [
    'json106-invariant-001' => [
        'strict' => '{"tenant":{"id":1,"features":["cache","media"],"limits":{"posts":50,"uploads":null}},"enabled":true}',
        'json5' => '{tenant:{id:1,features:["cache","media",],limits:{posts:50,uploads:null}},enabled:true,}',
        'patch' => '{tenant:{limits:{posts:75},features:["cache","media","forms"]},enabled:true}',
    ],
    'json106-invariant-002' => [
        'strict' => '{"queue":[{"name":"scan","ok":true},{"name":"rewrite","ok":false}],"retry":2}',
        'json5' => '{queue:[{name:"scan",ok:true},{name:"rewrite",ok:false}],retry:2,}',
        'patch' => '{queue:[{name:"scan",ok:true},{name:"rewrite",ok:true},{name:"publish",ok:null}],retry:3}',
    ],
    'json106-invariant-003' => [
        'strict' => '{"map":{"a.b":1,"quoted\\"key":"v","unicode":"cafe"},"empty":[],"count":0}',
        'json5' => '{map:{"a.b":1,"quoted\\"key":"v",unicode:"cafe"},empty:[],count:0}',
        'patch' => '{map:{unicode:"café",extra:true},count:1}',
    ],
    'json106-invariant-004' => [
        'strict' => '{"matrix":[[1,2],[3,4],[5,6]],"label":"grid","active":false}',
        'json5' => '{matrix:[[1,2],[3,4],[5,6]],label:"grid",active:false}',
        'patch' => '{matrix:[[1,2],[3,40],[5,6]],active:true}',
    ],
    'json106-invariant-005' => [
        'strict' => '{"profile":{"name":"Ada","roles":["admin"],"meta":{"visits":12,"last":null}}}',
        'json5' => '{profile:{name:"Ada",roles:["admin"],meta:{visits:12,last:null}}}',
        'patch' => '{profile:{roles:["admin","editor"],meta:{last:"2026-05-30"}}}',
    ],
    'json106-invariant-006' => [
        'strict' => '{"numbers":[0,-1,2.5,3000000000],"truth":[true,false,null],"text":"line\\nfeed"}',
        'json5' => '{numbers:[0,-1,2.5,3000000000],truth:[true,false,null],text:"line\\nfeed"}',
        'patch' => '{numbers:[0,-1,2.5,3000000001],text:"line\\nfeed"}',
    ],
    'json106-invariant-007' => [
        'strict' => '{"settings":{"theme":{"color":"blue","contrast":1.25},"flags":{"beta":false}}}',
        'json5' => '{settings:{theme:{color:"blue",contrast:1.25},flags:{beta:false}}}',
        'patch' => '{settings:{theme:{contrast:1.5},flags:{beta:true,internal:null}}}',
    ],
    'json106-invariant-008' => [
        'strict' => '{"items":[{"id":1,"tags":["a","b"]},{"id":2,"tags":[]}],"cursor":"end"}',
        'json5' => '{items:[{id:1,tags:["a","b"]},{id:2,tags:[]}],cursor:"end"}',
        'patch' => '{items:[{id:1,tags:["a","b","c"]},{id:2,tags:["d"]}],cursor:null}',
    ],
    'json106-invariant-009' => [
        'strict' => '{"nested":{"a":{"b":{"c":{"d":4}}}},"keep":"yes"}',
        'json5' => '{nested:{a:{b:{c:{d:4}}}},keep:"yes"}',
        'patch' => '{nested:{a:{b:{c:{e:5}}}},keep:"yes"}',
    ],
    'json106-invariant-010' => [
        'strict' => '{"array":[1,{"two":2},[3,{"four":4}]],"object":{"x":[5,6]}}',
        'json5' => '{array:[1,{two:2},[3,{four:4}]],object:{x:[5,6]}}',
        'patch' => '{array:[1,{two:22},[3,{four:4,five:5}]],object:{x:[5,6,7]}}',
    ],
];

$tests['real upstream json106 scalar tree atoms match path extraction'] = static function (TestRunner $t) use ($documents, $jsonb): void {
    foreach ($documents as $scenario => $case) {
        foreach (['strict' => $case['strict'], 'json5' => $case['json5']] as $kind => $json) {
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $json);
            $scalarRows = array_values(array_filter($rows, static fn (array $row): bool => !in_array($row['type'], ['object', 'array'], true)));
            $t->true(count($scalarRows) > 0, $scenario . ' ' . $kind . ' has scalar rows');

            foreach ($scalarRows as $row) {
                $t->same($row['atom'], SQLiteJsonExtract::extract($json, $row['fullkey']), $scenario . ' ' . $kind . ' atom ' . $row['fullkey']);
                $t->same($row['atom'], SQLiteJsonExtract::extract($jsonb($json), $row['fullkey']), $scenario . ' ' . $kind . ' jsonb atom ' . $row['fullkey']);
                $t->same($row['fullkey'], $row['fullkey'] === '$' ? '$' : $row['fullkey']);
            }
        }
    }
};

$tests['real upstream json106 remove then insert restores scalar leaves'] = static function (TestRunner $t) use ($documents): void {
    foreach ($documents as $scenario => $case) {
        $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $case['json5']);
        foreach ($rows as $row) {
            if (in_array($row['type'], ['object', 'array'], true) || str_ends_with($row['fullkey'], ']')) {
                continue;
            }

            $removed = SQLiteJsonRemove::remove($case['json5'], $row['fullkey']);
            $t->same(null, SQLiteJsonExtract::extract($removed, $row['fullkey']), $scenario . ' removed ' . $row['fullkey']);
            $restored = SQLiteJsonMutation::mutateSqlFunction('json_insert', $removed, $row['fullkey'], $row['atom']);
            $t->same($row['atom'], SQLiteJsonExtract::extract($restored, $row['fullkey']), $scenario . ' restored ' . $row['fullkey']);
            $t->same(true, SQLiteJsonValidity::jsonValid($restored, 2), $scenario . ' restored valid');
        }
    }
};

$tests['real upstream json106 merge patch preserves patch scalar leaves'] = static function (TestRunner $t) use ($documents): void {
    foreach ($documents as $scenario => $case) {
        $patched = SQLiteJsonPatch::patch($case['strict'], $case['patch']);
        $t->same(true, SQLiteJsonValidity::jsonValid($patched), $scenario . ' patched valid');

        foreach (SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $case['patch']) as $row) {
            if (in_array($row['type'], ['object', 'array'], true) || str_ends_with($row['fullkey'], ']')) {
                continue;
            }
            if ($row['atom'] === null) {
                continue;
            }

            $t->same($row['atom'], SQLiteJsonExtract::extract($patched, $row['fullkey']), $scenario . ' patch atom ' . $row['fullkey']);
        }
    }
};

$tests['real upstream json108 pretty output canonicalizes to original json'] = static function (TestRunner $t) use ($documents, $canonical, $jsonb): void {
    $indents = [null, '', "\t", '/*hello*/'];

    foreach ($documents as $scenario => $case) {
        foreach ($indents as $indent) {
            $pretty = $indent === null
                ? SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$case['strict'], null])
                : SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$case['strict'], $indent]);
            $t->same($canonical($case['strict']), $canonical($pretty), $scenario . ' text indent ' . var_export($indent, true));

            $prettyJsonb = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$jsonb($case['strict']), $indent]);
            $t->same($canonical($case['strict']), $canonical($prettyJsonb), $scenario . ' jsonb indent ' . var_export($indent, true));
        }
    }
};

$tests['real upstream json106 jsonb patch and pretty round trip parity'] = static function (TestRunner $t) use ($documents, $jsonb, $jsonText, $canonical): void {
    foreach ($documents as $scenario => $case) {
        $textPatch = SQLiteJsonPatch::patch($case['strict'], $case['patch']);
        $blobPatch = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $jsonb($case['strict']), $jsonb($case['patch']));
        $t->true($blobPatch instanceof SQLiteBlobValue, $scenario . ' jsonb patch result');
        $t->same($textPatch, $jsonText($blobPatch), $scenario . ' jsonb patch text parity');

        $pretty = SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', $blobPatch, '  ');
        $t->same($canonical($textPatch), $canonical($pretty), $scenario . ' jsonb pretty canonical parity');
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blobPatch, 4]), $scenario . ' jsonb valid flag');
    }
};

return $tests;
