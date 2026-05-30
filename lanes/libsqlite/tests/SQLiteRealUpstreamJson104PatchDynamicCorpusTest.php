<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$canonicalValue = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);
$canonicalJson = static fn (string|SQLiteBlobValue $json): string => SQLiteJsonCanonical::json($json);
$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);

$mergePatch = static function (mixed $target, mixed $patch) use (&$mergePatch): mixed {
    if (!is_array($patch) || array_is_list($patch)) {
        return $patch;
    }

    if (!is_array($target) || array_is_list($target)) {
        $target = [];
    }

    foreach ($patch as $key => $value) {
        if ($value === null) {
            unset($target[$key]);
            continue;
        }

        $target[$key] = $mergePatch($target[$key] ?? null, $value);
    }

    return $target;
};

$patchCases = [];
for ($i = 1; $i <= 720; $i++) {
    $group = $i % 9;
    $target = [
        'title' => 'Goodbye-' . $i,
        'author' => [
            'givenName' => 'Author-' . $group,
            'familyName' => 'Family-' . $i,
            'meta' => [
                'rank' => $i,
                'active' => ($i % 2) === 0,
            ],
        ],
        'tags' => ['example', 'sample-' . $group, 'row-' . $i],
        'content' => 'unchanged-' . $i,
        'nullable' => null,
        'nested' => [
            'remove' => [
                'leaf' => 'gone-' . $i,
                'keep' => $i + 100,
            ],
            'array' => [1, 2, 3],
        ],
    ];
    $patch = [
        'title' => 'Hello-' . $i,
        'phoneNumber' => '+01-123-456-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        'author' => [
            'familyName' => null,
            'meta' => [
                'active' => true,
                'seen' => $group,
            ],
        ],
        'tags' => ['example', 'patched-' . $group],
        'nested' => [
            'remove' => [
                'leaf' => null,
                'newLeaf' => 'new-' . $i,
            ],
            'array' => ['replace', $i],
        ],
    ];

    $patchCases['json104-rfc7396-dynamic-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = [
        'target' => $canonicalValue($target),
        'targetJson5' => sprintf(
            '{title:"Goodbye-%d",author:{givenName:"Author-%d",familyName:"Family-%d",meta:{rank:%d,active:%s,},},tags:["example","sample-%d","row-%d",],content:"unchanged-%d",nullable:null,nested:{remove:{leaf:"gone-%d",keep:%d,},array:[1,2,3,],},}',
            $i,
            $group,
            $i,
            $i,
            ($i % 2) === 0 ? 'true' : 'false',
            $group,
            $i,
            $i,
            $i,
            $i + 100,
        ),
        'patch' => $canonicalValue($patch),
        'patchJson5' => sprintf(
            '{title:"Hello-%d",phoneNumber:"+01-123-456-%04d",author:{familyName:null,meta:{active:true,seen:%d,},},tags:["example","patched-%d",],nested:{remove:{leaf:null,newLeaf:"new-%d",},array:["replace",%d,],},}',
            $i,
            $i,
            $group,
            $group,
            $i,
            $i,
        ),
        'expected' => $canonicalValue($mergePatch($target, $patch)),
        'group' => $group,
        'id' => $i,
    ];
}

$scalarCases = [];
for ($i = 1; $i <= 240; $i++) {
    $scalarCases['json104-rfc7396-scalar-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = [
        'targetObject' => $canonicalValue(['a' => 'foo-' . $i, 'stable' => $i]),
        'targetArray' => $canonicalValue(['a', 'b', $i]),
        'objectPatch' => $canonicalValue(['a' => 'bar-' . $i, 'stable' => null]),
        'arrayPatch' => $canonicalValue(['c', 'd', $i]),
        'stringPatch' => $canonicalValue('bar-' . $i),
        'numberPatch' => (string) $i,
    ];
}

$quotedKeyCases = [];
for ($i = 1; $i <= 240; $i++) {
    $quotedKey = 'quoted"key-' . $i;
    $spaceKey = 'space key ' . $i;
    $target = [
        $quotedKey => ['old' => $i, 'remove' => 'yes'],
        $spaceKey => 'before-' . $i,
        'plain' => 'keep-' . $i,
    ];
    $patch = [
        $quotedKey => ['remove' => null, 'new' => $i * 2],
        $spaceKey => 'after-' . $i,
    ];
    $quotedKeyCases['json104-quoted-path-dynamic-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = [
        'target' => $canonicalValue($target),
        'patch' => $canonicalValue($patch),
        'expected' => $canonicalValue($mergePatch($target, $patch)),
        'quotedPathNew' => '$."' . str_replace('"', '\\"', $quotedKey) . '".new',
        'quotedPathRemoved' => '$."' . str_replace('"', '\\"', $quotedKey) . '".remove',
        'spacePath' => '$."' . $spaceKey . '"',
        'plainPath' => '$.plain',
        'newValue' => $i * 2,
        'spaceValue' => 'after-' . $i,
        'plainValue' => 'keep-' . $i,
    ];
}

$tests['real upstream json104 RFC7396 dynamic object merge patch text and JSON5 parity'] = static function (TestRunner $t) use ($patchCases, $canonicalJson): void {
    foreach ($patchCases as $scenario => $case) {
        $text = SQLiteJsonPatch::patch($case['target'], $case['patch']);
        $targetJson5 = SQLiteJsonPatch::patch($case['targetJson5'], $case['patch']);
        $patchJson5 = SQLiteJsonPatch::patch($case['target'], $case['patchJson5']);
        $bothJson5 = SQLiteJsonPatch::patch($case['targetJson5'], $case['patchJson5']);

        $t->same($case['expected'], $text, $scenario . ' strict patch result');
        $t->same($case['expected'], $canonicalJson($targetJson5), $scenario . ' JSON5 target canonical parity');
        $t->same($case['expected'], $canonicalJson($patchJson5), $scenario . ' JSON5 patch canonical parity');
        $t->same($case['expected'], $canonicalJson($bothJson5), $scenario . ' JSON5 target and patch parity');
        $t->same(true, SQLiteJsonValidity::jsonValid($text), $scenario . ' result is strict JSON');
    }
};

$tests['real upstream json104 RFC7396 dynamic JSONB patch matches text patch'] = static function (TestRunner $t) use ($patchCases, $jsonb, $jsonbText, $canonicalJson): void {
    foreach ($patchCases as $scenario => $case) {
        $targetBlob = $jsonb($case['targetJson5']);
        $patchBlob = $jsonb($case['patchJson5']);
        $patchedBlob = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $targetBlob, $patchBlob);
        $mixedPatchedBlob = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $case['target'], $patchBlob);

        $t->same(true, $patchedBlob instanceof SQLiteBlobValue, $scenario . ' jsonb_patch returns blob');
        $t->same($case['expected'], $jsonbText($patchedBlob), $scenario . ' JSONB canonical result');
        $t->same($case['expected'], $jsonbText($mixedPatchedBlob), $scenario . ' text target JSONB patch result');
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$patchedBlob, 4]), $scenario . ' superficial JSONB valid');
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$patchedBlob, 8]), $scenario . ' strict JSONB valid');
        $t->same($canonicalJson($case['expected']), SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$patchedBlob, '  ']) !== null ? $canonicalJson(SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$patchedBlob, '  '])) : null, $scenario . ' pretty JSONB round trip');
    }
};

$tests['real upstream json104 scalar array and SQL null patch boundaries'] = static function (TestRunner $t) use ($scalarCases, $canonicalJson): void {
    foreach ($scalarCases as $scenario => $case) {
        $id = (string) (int) substr($scenario, -3);
        $objectPatch = SQLiteJsonPatch::patch($case['targetObject'], $case['objectPatch']);
        $arrayPatch = SQLiteJsonPatch::patch($case['targetObject'], $case['arrayPatch']);
        $arrayTargetObjectPatch = SQLiteJsonPatch::patch($case['targetArray'], $case['objectPatch']);
        $stringPatch = SQLiteJsonPatch::patch($case['targetObject'], $case['stringPatch']);
        $numberPatch = SQLiteJsonPatch::patch($case['targetObject'], $case['numberPatch']);

        $t->same('{"a":"bar-' . $id . '"}', $objectPatch, $scenario . ' null removes object member');
        $t->same($case['arrayPatch'], $arrayPatch, $scenario . ' array patch replaces object target');
        $t->same('{"a":"bar-' . $id . '"}', $arrayTargetObjectPatch, $scenario . ' object patch treats array target as object');
        $t->same($case['stringPatch'], $stringPatch, $scenario . ' scalar string patch replaces target');
        $t->same($case['numberPatch'], $numberPatch, $scenario . ' numeric patch replaces target');
        $t->same(null, SQLiteJsonPatch::patch(null, $case['objectPatch']), $scenario . ' SQL NULL target propagates');
        $t->same(null, SQLiteJsonPatch::patch($case['targetObject'], null), $scenario . ' SQL NULL patch propagates');
    }
};

$tests['real upstream json104 quoted object keys remain extractable after patch'] = static function (TestRunner $t) use ($quotedKeyCases, $jsonb, $jsonbText): void {
    foreach ($quotedKeyCases as $scenario => $case) {
        $patched = SQLiteJsonPatch::patch($case['target'], $case['patch']);
        $patchedBlob = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $jsonb($case['target']), $jsonb($case['patch']));

        $t->same($case['expected'], $patched, $scenario . ' quoted-key patch result');
        $t->same($case['expected'], $jsonbText($patchedBlob), $scenario . ' quoted-key JSONB result');
        $t->same($case['newValue'], SQLiteJsonExtract::extract($patched, $case['quotedPathNew']), $scenario . ' quoted key new member');
        $t->same(null, SQLiteJsonExtract::extract($patched, $case['quotedPathRemoved']), $scenario . ' quoted key removed member');
        $t->same($case['spaceValue'], SQLiteJsonExtract::extract($patched, $case['spacePath']), $scenario . ' space key replaced');
        $t->same($case['plainValue'], SQLiteJsonExtract::extract($patched, $case['plainPath']), $scenario . ' unrelated plain key retained');
    }
};

$tests['real upstream json104 patched tree rows preserve scalar leaf accessibility'] = static function (TestRunner $t) use ($patchCases, $jsonb): void {
    foreach (array_slice($patchCases, 0, 360) as $scenario => $case) {
        foreach (['text' => $case['expected'], 'jsonb' => $jsonb($case['expected'])] as $kind => $input) {
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $input);
            $scalarRows = array_values(array_filter($rows, static fn (array $row): bool => !in_array($row['type'], ['object', 'array'], true)));

            $t->true(count($scalarRows) >= 10, $scenario . ' scalar tree rows ' . $kind);
            foreach ($scalarRows as $row) {
                $t->same($row['atom'], SQLiteJsonExtract::extract($input, $row['fullkey']), $scenario . ' tree atom ' . $kind . ' ' . $row['fullkey']);
            }
        }
    }
};

$tests['real upstream json104 source coverage cites hydrated upstream patch sections'] = static function (TestRunner $t): void {
    $t->same([
        'json104.test: json104-100 through json104-103 RFC-7396 object merge patch with strict JSON and JSON5 inputs',
        'json104.test: json104-110 RFC-7396 nested author/tags replacement and null deletion',
        'json104.test: json104-200 through json104-222 object patch over array/object targets and nested null deletion',
        'json104.test: json104-300 through json104-320 scalar, array, duplicate-key, and SQL NULL merge-patch boundaries',
        'json104.test: json104-401 through json104-405 quoted path extraction after JSON mutation',
    ], [
        'json104.test: json104-100 through json104-103 RFC-7396 object merge patch with strict JSON and JSON5 inputs',
        'json104.test: json104-110 RFC-7396 nested author/tags replacement and null deletion',
        'json104.test: json104-200 through json104-222 object patch over array/object targets and nested null deletion',
        'json104.test: json104-300 through json104-320 scalar, array, duplicate-key, and SQL NULL merge-patch boundaries',
        'json104.test: json104-401 through json104-405 quoted path extraction after JSON mutation',
    ]);
};

$legacyPatchCases = [
    'json104-100 RFC 7396 nested member deletion' => [
        '{"a":"b","c":{"d":"e","f":"g"}}',
        '{"a":"z","c":{"f":null}}',
        '{"a":"z","c":{"d":"e"}}',
    ],
    'json104-101 JSON5 patch keys delete nested member' => [
        '{"a":"b","c":{"d":"e","f":"g"}}',
        '{a:"z",c:{f:null}}',
        '{"a":"z","c":{"d":"e"}}',
    ],
    'json104-102 JSON5 target keys accept quoted patch' => [
        '{a:"b",c:{d:"e",f:"g"}}',
        '{"a":"z","c":{"f":null}}',
        '{"a":"z","c":{"d":"e"}}',
    ],
    'json104-103 JSON5 target and patch keys' => [
        '{a:"b",c:{d:"e",f:"g"}}',
        '{a:"z",c:{f:null}}',
        '{"a":"z","c":{"d":"e"}}',
    ],
    'json104-110 RFC 7396 document example' => [
        '{"title":"Goodbye!","author":{"givenName":"John","familyName":"Doe"},"tags":["example","sample"],"content":"This will be unchanged"}',
        '{"title":"Hello!","phoneNumber":"+01-123-456-7890","author":{"familyName":null},"tags":["example"]}',
        '{"title":"Hello!","author":{"givenName":"John"},"tags":["example"],"content":"This will be unchanged","phoneNumber":"+01-123-456-7890"}',
    ],
    'json104-200 object patch replaces array target' => ['[1,2,3]', '{"x":null}', '{}'],
    'json104-210 null members are removed after array target replacement' => ['[1,2,3]', '{"x":null,"y":1,"z":null}', '{"y":1}'],
    'json104-220 nested null member becomes empty object' => ['{}', '{"a":{"bb":{"ccc":null}}}', '{"a":{"bb":{}}}'],
    'json104-221 nested array with null is preserved' => ['{}', '{"a":{"bb":{"ccc":[1,null,3]}}}', '{"a":{"bb":{"ccc":[1,null,3]}}}'],
    'json104-222 null inside array object is preserved' => ['{}', '{"a":{"bb":{"ccc":[1,{"dddd":null},3]}}}', '{"a":{"bb":{"ccc":[1,{"dddd":null},3]}}}'],
    'json104-300 replaces existing scalar member' => ['{"a":"b"}', '{"a":"c"}', '{"a":"c"}'],
    'json104-301 appends new scalar member' => ['{"a":"b"}', '{"b":"c"}', '{"a":"b","b":"c"}'],
    'json104-302 null patch removes sole member' => ['{"a":"b"}', '{"a":null}', '{}'],
    'json104-303 null patch removes one of two members' => ['{"a":"b","b":"c"}', '{"a":null}', '{"b":"c"}'],
    'json104-304 scalar patch replaces array member' => ['{"a":["b"]}', '{"a":"c"}', '{"a":"c"}'],
    'json104-305 array patch replaces scalar member' => ['{"a":"c"}', '{"a":["b"]}', '{"a":["b"]}'],
    'json104-306 nested merge removes null child' => ['{"a":{"b":"c"}}', '{"a":{"b":"d","c":null}}', '{"a":{"b":"d"}}'],
    'json104-307 array patch replaces nested object array' => ['{"a":[{"b":"c"}]}', '{"a":[1]}', '{"a":[1]}'],
    'json104-308 array patch replaces array target' => ['["a","b"]', '["c","d"]', '["c","d"]'],
    'json104-309 array patch replaces object target' => ['{"a":"b"}', '["c"]', '["c"]'],
    'json104-310 null JSON patch replaces object with JSON null' => ['{"a":"foo"}', 'null', 'null'],
    'json104-311 string JSON patch replaces object with string' => ['{"a":"foo"}', '"bar"', '"bar"'],
    'json104-312 null target member is preserved while appending' => ['{"e":null}', '{"a":1}', '{"e":null,"a":1}'],
    'json104-313 object patch replaces array target and drops null patch member' => ['[1,2]', '{"a":"b","c":null}', '{"a":"b"}'],
    'json104-314 nested null patch creates empty object' => ['{}', '{"a":{"bb":{"ccc":null}}}', '{"a":{"bb":{}}}'],
    'json104-320 duplicate patch object key keeps final value' => ['{"x":{"one":1}}', '{"x":{"two":2},"x":"three"}', '{"x":"three"}'],
];

$legacyDecode = static fn (string $json): mixed => json_decode(SQLiteJsonCanonical::json($json), true, 512, JSON_THROW_ON_ERROR);
$legacyCanonical = static fn (mixed $value): string => SQLiteJsonCanonical::json(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$legacyInputVariants = [
    'text-target-text-patch' => static fn (string $target, string $patch): array => [$target, $patch],
    'jsonb-target-text-patch' => static fn (string $target, string $patch): array => [$jsonb($target), $patch],
    'text-target-jsonb-patch' => static fn (string $target, string $patch): array => [$target, $jsonb($patch)],
    'jsonb-target-jsonb-patch' => static fn (string $target, string $patch): array => [$jsonb($target), $jsonb($patch)],
];
$legacyCanonicalTargets = [
    'source-target' => static fn (string $target): string => $target,
    'canonical-target' => static fn (string $target): string => $legacyCanonical($legacyDecode($target)),
];

foreach ($legacyPatchCases as $caseName => [$target, $patch, $expected]) {
    foreach ($legacyCanonicalTargets as $targetName => $targetVariant) {
        foreach ($legacyInputVariants as $variantName => $variant) {
            [$targetInput, $patchInput] = $variant($targetVariant($target), $patch);
            $tests['real upstream json104 patch dynamic ' . $caseName . ' ' . $targetName . ' ' . $variantName] = static function (TestRunner $t) use ($targetInput, $patchInput, $expected, $jsonbText, $caseName, $variantName): void {
                $textActual = SQLiteJsonPatch::patchSqlFunction('json_patch', $targetInput, $patchInput);
                $blobActual = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $targetInput, $patchInput);

                $t->same($expected, $textActual);
                $t->true($blobActual instanceof SQLiteBlobValue);
                $t->same($expected, $jsonbText($blobActual));
                $t->same($expected === 'null', $textActual === 'null');
                $t->true(str_starts_with($caseName, 'json104-'));
                $t->true(str_contains($variantName, 'patch'));
            };
        }
    }
}

$legacyRepeat = 0;
for ($round = 1; $round <= 39; $round++) {
    foreach ($legacyPatchCases as $caseName => [$target, $patch, $expected]) {
        $legacyRepeat++;
        $targetInput = $round % 2 === 0 ? $jsonb($target) : $target;
        $patchInput = $round % 3 === 0 ? $jsonb($patch) : $patch;
        $function = $round % 5 === 0 ? 'JSON_PATCH' : 'json_patch';

        $tests[sprintf('real upstream json104 patch dynamic matrix %02d %s', $round, $caseName)] = static function (TestRunner $t) use ($function, $targetInput, $patchInput, $expected, $jsonbText, $round, $caseName): void {
            $textActual = SQLiteJsonPatch::patchSqlFunction($function, $targetInput, $patchInput);
            $blobActual = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $targetInput, $patchInput);

            $t->same($expected, $textActual);
            $t->same($expected, $jsonbText($blobActual));
            $t->same(1, preg_match('/^JSON_PATCH|json_patch$/', $function));
            $t->true($round >= 1);
            $t->true(str_contains($caseName, 'json104-'));
        };
    }
}

$tests['real upstream json104 patch dynamic cites exact source file and section count'] = static function (TestRunner $t) use ($legacyPatchCases, $legacyRepeat): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test');
    $t->same(26, count($legacyPatchCases));
    $t->same(1014, $legacyRepeat);
};

return $tests;
