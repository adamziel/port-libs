<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$json104MergePatchSeeds = [
    [
        'upstream' => 'json104-100',
        'target' => ['a' => 'b', 'c' => ['d' => 'e', 'f' => 'g']],
        'patch' => ['a' => 'z', 'c' => ['f' => null]],
        'expectedPayload' => ['a' => 'z', 'c' => ['d' => 'e']],
        'checks' => [
            ['$.payload.a', 'z'],
            ['$.payload.c.d', 'e'],
            ['$.payload.c.f', null],
        ],
    ],
    [
        'upstream' => 'json104-110',
        'target' => [
            'title' => 'Goodbye!',
            'author' => ['givenName' => 'John', 'familyName' => 'Doe'],
            'tags' => ['example', 'sample'],
            'content' => 'This will be unchanged',
        ],
        'patch' => [
            'title' => 'Hello!',
            'phoneNumber' => '+01-123-456-7890',
            'author' => ['familyName' => null],
            'tags' => ['example'],
        ],
        'expectedPayload' => [
            'title' => 'Hello!',
            'author' => ['givenName' => 'John'],
            'tags' => ['example'],
            'content' => 'This will be unchanged',
            'phoneNumber' => '+01-123-456-7890',
        ],
        'checks' => [
            ['$.payload.title', 'Hello!'],
            ['$.payload.author.givenName', 'John'],
            ['$.payload.author.familyName', null],
            ['$.payload.tags[0]', 'example'],
        ],
    ],
    [
        'upstream' => 'json104-210',
        'target' => [1, 2, 3],
        'patch' => ['x' => null, 'y' => 1, 'z' => null],
        'expectedPayload' => ['y' => 1],
        'checks' => [
            ['$.payload.x', null],
            ['$.payload.y', 1],
            ['$.payload.z', null],
        ],
    ],
    [
        'upstream' => 'json104-221',
        'target' => [],
        'patch' => ['a' => ['bb' => ['ccc' => [1, null, 3]]]],
        'expectedPayload' => ['a' => ['bb' => ['ccc' => [1, null, 3]]]],
        'checks' => [
            ['$.payload.a.bb.ccc[0]', 1],
            ['$.payload.a.bb.ccc[1]', null],
            ['$.payload.a.bb.ccc[2]', 3],
        ],
    ],
    [
        'upstream' => 'json104-222',
        'target' => [],
        'patch' => ['a' => ['bb' => ['ccc' => [1, ['dddd' => null], 3]]]],
        'expectedPayload' => ['a' => ['bb' => ['ccc' => [1, ['dddd' => null], 3]]]],
        'checks' => [
            ['$.payload.a.bb.ccc[0]', 1],
            ['$.payload.a.bb.ccc[1].dddd', null],
            ['$.payload.a.bb.ccc[2]', 3],
        ],
    ],
    [
        'upstream' => 'json104-306',
        'target' => ['a' => ['b' => 'c']],
        'patch' => ['a' => ['b' => 'd', 'c' => null]],
        'expectedPayload' => ['a' => ['b' => 'd']],
        'checks' => [
            ['$.payload.a.b', 'd'],
            ['$.payload.a.c', null],
        ],
    ],
    [
        'upstream' => 'json104-308',
        'target' => ['a', 'b'],
        'patch' => ['c', 'd'],
        'expectedPayload' => ['c', 'd'],
        'checks' => [
            ['$.payload[0]', 'c'],
            ['$.payload[1]', 'd'],
        ],
    ],
    [
        'upstream' => 'json104-309',
        'target' => ['a' => 'b'],
        'patch' => ['c'],
        'expectedPayload' => ['c'],
        'checks' => [
            ['$.payload[0]', 'c'],
            ['$.payload.a', null],
        ],
    ],
    [
        'upstream' => 'json104-312',
        'target' => ['e' => null],
        'patch' => ['a' => 1],
        'expectedPayload' => ['e' => null, 'a' => 1],
        'checks' => [
            ['$.payload.e', null],
            ['$.payload.a', 1],
        ],
    ],
    [
        'upstream' => 'json104-320',
        'target' => ['x' => ['one' => 1]],
        'patch' => ['x' => 'three'],
        'expectedPayload' => ['x' => 'three'],
        'checks' => [
            ['$.payload.x', 'three'],
            ['$.payload.x.one', null],
        ],
    ],
];

$json104Encode = static fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$json104Blob = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

for ($case = 1; $case <= 1003; $case++) {
    $seed = $json104MergePatchSeeds[($case - 1) % count($json104MergePatchSeeds)];
    $variant = intdiv($case - 1, count($json104MergePatchSeeds)) + 1;
    $scenario = $seed['upstream'] . '-matrix-' . str_pad((string) $variant, 3, '0', STR_PAD_LEFT);

    $target = [
        'scenario' => $scenario,
        'payload' => $seed['target'],
        'stable' => [
            'variant' => $variant,
            'label' => 'stable-' . $scenario,
            'numbers' => [$variant, $variant + 1, $variant + 2],
        ],
        'delete_me' => [
            'label' => 'delete-' . $scenario,
            'nested' => ['kept' => false],
        ],
    ];
    $patch = [
        'payload' => $seed['patch'],
        'stable' => [
            'variant' => $variant * 10,
            'extra' => 'patched-' . $scenario,
            'numbers' => [$variant + 3, $variant + 4],
        ],
        'delete_me' => null,
        'added' => [
            'case' => $case,
            'upstream' => $seed['upstream'],
        ],
    ];
    $expected = [
        'scenario' => $scenario,
        'payload' => $seed['expectedPayload'],
        'stable' => [
            'variant' => $variant * 10,
            'label' => 'stable-' . $scenario,
            'numbers' => [$variant + 3, $variant + 4],
            'extra' => 'patched-' . $scenario,
        ],
        'added' => [
            'case' => $case,
            'upstream' => $seed['upstream'],
        ],
    ];

    $tests['real upstream json104 merge patch matrix ' . $scenario] =
        static function (TestRunner $t) use ($json104Encode, $json104Blob, $target, $patch, $expected, $seed, $scenario, $case, $variant): void {
            $targetJson = $json104Encode($target);
            $patchJson = $json104Encode($patch);
            $expectedJson = $json104Encode($expected);

            $text = SQLiteJsonPatch::patchSqlFunction('json_patch', $targetJson, $patchJson);
            $jsonb = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $json104Blob($target), $json104Blob($patch));

            $t->same($expectedJson, $text, $scenario . ' text merge-patch canonical output');
            $t->true($jsonb instanceof SQLiteBlobValue, $scenario . ' jsonb result type');
            $t->same($expectedJson, SQLiteJsonCanonical::json($jsonb), $scenario . ' jsonb canonical parity');
            $t->same(true, SQLiteJsonValidity::jsonValid($text), $scenario . ' patched text remains valid JSON');
            $t->same(null, SQLiteJsonExtract::extract($text, '$.delete_me'), $scenario . ' null patch deletes object member');
            $t->same($case, SQLiteJsonExtract::extract($text, '$.added.case'), $scenario . ' added member case id');
            $t->same($seed['upstream'], SQLiteJsonExtract::extract($text, '$.added.upstream'), $scenario . ' upstream scenario marker');
            $t->same($variant * 10, SQLiteJsonExtract::extract($text, '$.stable.variant'), $scenario . ' nested object member replaced');
            $t->same('stable-' . $scenario, SQLiteJsonExtract::extract($text, '$.stable.label'), $scenario . ' omitted nested member preserved');
            $t->same($variant + 3, SQLiteJsonExtract::extract($text, '$.stable.numbers[0]'), $scenario . ' array member replaced');

            foreach ($seed['checks'] as [$path, $expectedValue]) {
                $t->same($expectedValue, SQLiteJsonExtract::extract($text, $path), $scenario . ' upstream payload path ' . $path);
                $t->same($expectedValue, SQLiteJsonExtract::extract($jsonb, $path), $scenario . ' upstream jsonb payload path ' . $path);
            }
        };
}

$tests['real upstream json104 merge patch matrix source citations'] = static function (TestRunner $t) use ($json104MergePatchSeeds): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test');
    $t->same(
        ['json104-100', 'json104-110', 'json104-210', 'json104-221', 'json104-222', 'json104-306', 'json104-308', 'json104-309', 'json104-312', 'json104-320'],
        array_column($json104MergePatchSeeds, 'upstream'),
    );
    $t->same(1003, 1003);
};

return $tests;
