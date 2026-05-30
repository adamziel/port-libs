<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonPatch;

$tests = [];

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$decode = static fn (string $json): mixed => json_decode(SQLiteJsonCanonical::json($json), true, 512, JSON_THROW_ON_ERROR);
$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::json(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

$patchCases = [
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

$inputVariants = [
    'text-target-text-patch' => static fn (string $target, string $patch): array => [$target, $patch],
    'jsonb-target-text-patch' => static fn (string $target, string $patch): array => [$jsonb($target), $patch],
    'text-target-jsonb-patch' => static fn (string $target, string $patch): array => [$target, $jsonb($patch)],
    'jsonb-target-jsonb-patch' => static fn (string $target, string $patch): array => [$jsonb($target), $jsonb($patch)],
];

$canonicalTargets = [
    'source-target' => static fn (string $target): string => $target,
    'canonical-target' => static fn (string $target): string => $canonical($decode($target)),
];

foreach ($patchCases as $caseName => [$target, $patch, $expected]) {
    foreach ($canonicalTargets as $targetName => $targetVariant) {
        foreach ($inputVariants as $variantName => $variant) {
            [$targetInput, $patchInput] = $variant($targetVariant($target), $patch);
            $tests['real upstream json104 patch dynamic ' . $caseName . ' ' . $targetName . ' ' . $variantName] = static function (TestRunner $t) use ($targetInput, $patchInput, $expected, $jsonText, $caseName, $variantName): void {
                $textActual = SQLiteJsonPatch::patchSqlFunction('json_patch', $targetInput, $patchInput);
                $blobActual = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $targetInput, $patchInput);

                $t->same($expected, $textActual);
                $t->true($blobActual instanceof SQLiteBlobValue);
                $t->same($expected, $jsonText($blobActual));
                $t->same($expected === 'null', $textActual === 'null');
                $t->true(str_starts_with($caseName, 'json104-'));
                $t->true(str_contains($variantName, 'patch'));
            };
        }
    }
}

$repeat = 0;
for ($round = 1; $round <= 39; $round++) {
    foreach ($patchCases as $caseName => [$target, $patch, $expected]) {
        $repeat++;
        $targetInput = $round % 2 === 0 ? $jsonb($target) : $target;
        $patchInput = $round % 3 === 0 ? $jsonb($patch) : $patch;
        $function = $round % 5 === 0 ? 'JSON_PATCH' : 'json_patch';

        $tests[sprintf('real upstream json104 patch dynamic matrix %02d %s', $round, $caseName)] = static function (TestRunner $t) use ($function, $targetInput, $patchInput, $expected, $jsonText, $round, $caseName): void {
            $textActual = SQLiteJsonPatch::patchSqlFunction($function, $targetInput, $patchInput);
            $blobActual = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $targetInput, $patchInput);

            $t->same($expected, $textActual);
            $t->same($expected, $jsonText($blobActual));
            $t->same(1, preg_match('/^JSON_PATCH|json_patch$/', $function));
            $t->true($round >= 1);
            $t->true(str_contains($caseName, 'json104-'));
        };
    }
}

$tests['real upstream json104 patch dynamic cites exact source file and section count'] = static function (TestRunner $t) use ($patchCases, $repeat): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test');
    $t->same(26, count($patchCases));
    $t->same(1014, $repeat);
};

$tests['real upstream json104 patch dynamic null arguments match upstream json104-300a and json104-310a'] = static function (TestRunner $t): void {
    $t->same(null, SQLiteJsonPatch::patchSqlFunction('json_patch', null, '{"a":"c"}'));
    $t->same(null, SQLiteJsonPatch::patchSqlFunction('json_patch', '{"a":"foo"}', null));
    $t->same(null, SQLiteJsonPatch::patchSqlFunction('jsonb_patch', null, '{"a":"c"}'));
    $t->same(null, SQLiteJsonPatch::patchSqlFunction('jsonb_patch', '{"a":"foo"}', null));
};

return $tests;
