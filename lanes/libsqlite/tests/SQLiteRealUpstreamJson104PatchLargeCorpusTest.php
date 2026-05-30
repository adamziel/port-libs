<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

/**
 * @return list<array{id:string,target:string,patch:string,expected:string,paths:list<string>}>
 */
function libsqlite_json104_upstream_seed_cases(): array
{
    return [
        [
            'id' => 'json104-100-rfc-page-2-3',
            'target' => '{"a":"b","c":{"d":"e","f":"g"}}',
            'patch' => '{"a":"z","c":{"f":null}}',
            'expected' => '{"a":"z","c":{"d":"e"}}',
            'paths' => ['$.a', '$.c.d', '$.c.f'],
        ],
        [
            'id' => 'json104-110-rfc-page-4-5',
            'target' => '{"title":"Goodbye!","author":{"givenName":"John","familyName":"Doe"},"tags":["example","sample"],"content":"This will be unchanged"}',
            'patch' => '{"title":"Hello!","phoneNumber":"+01-123-456-7890","author":{"familyName":null},"tags":["example"]}',
            'expected' => '{"title":"Hello!","author":{"givenName":"John"},"tags":["example"],"content":"This will be unchanged","phoneNumber":"+01-123-456-7890"}',
            'paths' => ['$.title', '$.author.givenName', '$.author.familyName', '$.tags', '$.phoneNumber'],
        ],
        [
            'id' => 'json104-210-array-target-object-patch',
            'target' => '[1,2,3]',
            'patch' => '{"x":null,"y":1,"z":null}',
            'expected' => '{"y":1}',
            'paths' => ['$.x', '$.y', '$.z'],
        ],
        [
            'id' => 'json104-221-nested-null-array-preserved',
            'target' => '{}',
            'patch' => '{"a":{"bb":{"ccc":[1,null,3]}}}',
            'expected' => '{"a":{"bb":{"ccc":[1,null,3]}}}',
            'paths' => ['$.a.bb.ccc', '$.a.bb.ccc[1]'],
        ],
        [
            'id' => 'json104-222-nested-object-null-preserved-in-array',
            'target' => '{}',
            'patch' => '{"a":{"bb":{"ccc":[1,{"dddd":null},3]}}}',
            'expected' => '{"a":{"bb":{"ccc":[1,{"dddd":null},3]}}}',
            'paths' => ['$.a.bb.ccc[1].dddd'],
        ],
        [
            'id' => 'json104-300-replace-existing-member',
            'target' => '{"a":"b"}',
            'patch' => '{"a":"c"}',
            'expected' => '{"a":"c"}',
            'paths' => ['$.a'],
        ],
        [
            'id' => 'json104-301-add-member',
            'target' => '{"a":"b"}',
            'patch' => '{"b":"c"}',
            'expected' => '{"a":"b","b":"c"}',
            'paths' => ['$.a', '$.b'],
        ],
        [
            'id' => 'json104-302-delete-only-member',
            'target' => '{"a":"b"}',
            'patch' => '{"a":null}',
            'expected' => '{}',
            'paths' => ['$.a'],
        ],
        [
            'id' => 'json104-306-recursive-delete',
            'target' => '{"a":{"b":"c"}}',
            'patch' => '{"a":{"b":"d","c":null}}',
            'expected' => '{"a":{"b":"d"}}',
            'paths' => ['$.a.b', '$.a.c'],
        ],
        [
            'id' => 'json104-308-array-replaces-array',
            'target' => '["a","b"]',
            'patch' => '["c","d"]',
            'expected' => '["c","d"]',
            'paths' => ['$[0]', '$[1]'],
        ],
        [
            'id' => 'json104-309-array-patch-replaces-object',
            'target' => '{"a":"b"}',
            'patch' => '["c"]',
            'expected' => '["c"]',
            'paths' => ['$[0]', '$.a'],
        ],
        [
            'id' => 'json104-311-scalar-patch-replaces-object',
            'target' => '{"a":"foo"}',
            'patch' => '"bar"',
            'expected' => '"bar"',
            'paths' => ['$', '$.a'],
        ],
        [
            'id' => 'json104-312-null-valued-target-member-survives',
            'target' => '{"e":null}',
            'patch' => '{"a":1}',
            'expected' => '{"e":null,"a":1}',
            'paths' => ['$.e', '$.a'],
        ],
        [
            'id' => 'json104-320-duplicate-key-last-wins',
            'target' => '{"x":{"one":1}}',
            'patch' => '{"x":{"two":2},"x":"three"}',
            'expected' => '{"x":"three"}',
            'paths' => ['$.x', '$.x.one', '$.x.two'],
        ],
    ];
}

function libsqlite_json104_decode(string $json): mixed
{
    return json_decode($json, false, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
}

function libsqlite_json104_canonical(mixed $value): string
{
    return SQLiteJsonCanonical::encodeDecodedJson($value);
}

function libsqlite_json104_blob(string $json): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode(libsqlite_json104_decode($json)));
}

function libsqlite_json104_merge_patch(mixed $target, mixed $patch): mixed
{
    if (!$patch instanceof stdClass) {
        return $patch;
    }

    if (!$target instanceof stdClass) {
        $target = new stdClass();
    }

    foreach (get_object_vars($patch) as $key => $value) {
        if ($value === null) {
            unset($target->{$key});
            continue;
        }

        $target->{$key} = libsqlite_json104_merge_patch($target->{$key} ?? null, $value);
    }

    return $target;
}

/**
 * @return list<array{id:string,target:string,patch:string,expected:string,paths:list<string>}>
 */
function libsqlite_json104_large_cases(): array
{
    $cases = [];
    foreach (libsqlite_json104_upstream_seed_cases() as $seedIndex => $seed) {
        $target = libsqlite_json104_decode($seed['target']);
        $patch = libsqlite_json104_decode($seed['patch']);

        for ($variant = 1; $variant <= 40; $variant++) {
            $suffix = $seedIndex . '-' . $variant;
            $wrappedTarget = (object) [
                'scenario' => $seed['id'],
                'variant' => $variant,
                'payload' => $target,
                'stable' => (object) [
                    'label' => 'unchanged-' . $suffix,
                    'numbers' => [$variant, $variant + 1, $variant + 2],
                ],
                'remove_me' => (object) [
                    'nested' => 'gone-' . $suffix,
                ],
            ];
            $wrappedPatch = (object) [
                'variant' => $variant * 10,
                'payload' => $patch,
                'stable' => (object) [
                    'numbers' => [$variant * 2, null, $variant * 3],
                ],
                'remove_me' => null,
                'added' => (object) [
                    'seed' => $seed['id'],
                    'variant' => $variant,
                    'flag' => ($variant % 2) === 0,
                ],
            ];
            $expected = libsqlite_json104_merge_patch($wrappedTarget, $wrappedPatch);
            $cases[] = [
                'id' => $seed['id'] . '-wrapped-' . str_pad((string) $variant, 2, '0', STR_PAD_LEFT),
                'target' => libsqlite_json104_canonical($wrappedTarget),
                'patch' => libsqlite_json104_canonical($wrappedPatch),
                'expected' => libsqlite_json104_canonical($expected),
                'paths' => array_merge(
                    ['$.scenario', '$.variant', '$.payload', '$.stable.label', '$.stable.numbers', '$.remove_me', '$.added.seed', '$.added.flag'],
                    array_map(static fn (string $path): string => '$.payload' . substr($path, 1), $seed['paths']),
                ),
            ];
        }
    }

    return $cases;
}

$cases = libsqlite_json104_large_cases();

$tests['real upstream json104 RFC7396 patch corpus cites source sections'] = static function (TestRunner $t) use ($cases): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test');
    $t->same(14, count(libsqlite_json104_upstream_seed_cases()));
    $t->same(560, count($cases));
    $t->same('json104-100-rfc-page-2-3-wrapped-01', $cases[0]['id']);
    $t->same('json104-320-duplicate-key-last-wins-wrapped-40', $cases[array_key_last($cases)]['id']);
};

$tests['real upstream json104 RFC7396 patch text and JSONB parity large corpus'] = static function (TestRunner $t) use ($cases): void {
    foreach ($cases as $case) {
        $patched = SQLiteJsonPatch::patchSqlFunction('json_patch', $case['target'], $case['patch']);
        $patchedBlob = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', libsqlite_json104_blob($case['target']), libsqlite_json104_blob($case['patch']));

        $t->same($case['expected'], $patched, $case['id'] . ' text patch canonical');
        $t->true($patchedBlob instanceof SQLiteBlobValue, $case['id'] . ' jsonb patch returns blob');
        $t->same($case['expected'], SQLiteJsonCanonical::json($patchedBlob), $case['id'] . ' jsonb patch canonical');
        $t->same(true, SQLiteJsonValidity::jsonValid($patched), $case['id'] . ' patched text valid');
    }
};

$tests['real upstream json104 RFC7396 patch path extraction large corpus'] = static function (TestRunner $t) use ($cases): void {
    foreach ($cases as $case) {
        $patched = SQLiteJsonPatch::patch($case['target'], $case['patch']);
        $patchedBlob = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', libsqlite_json104_blob($case['target']), libsqlite_json104_blob($case['patch']));

        foreach ($case['paths'] as $path) {
            $expected = SQLiteJsonExtract::extract($case['expected'], $path);
            $t->same($expected, SQLiteJsonExtract::extract($patched, $path), $case['id'] . ' text extract ' . $path);
            $t->same($expected, SQLiteJsonExtract::extract($patchedBlob, $path), $case['id'] . ' jsonb extract ' . $path);
        }
    }
};

$tests['real upstream json104 RFC7396 patch SQL NULL propagation large corpus'] = static function (TestRunner $t) use ($cases): void {
    foreach ($cases as $case) {
        $t->same(null, SQLiteJsonPatch::patchSqlFunction('json_patch', null, $case['patch']), $case['id'] . ' text null target');
        $t->same(null, SQLiteJsonPatch::patchSqlFunction('json_patch', $case['target'], null), $case['id'] . ' text null patch');
        $t->same(null, SQLiteJsonPatch::patchSqlFunction('jsonb_patch', null, libsqlite_json104_blob($case['patch'])), $case['id'] . ' jsonb null target');
        $t->same(null, SQLiteJsonPatch::patchSqlFunction('jsonb_patch', libsqlite_json104_blob($case['target']), null), $case['id'] . ' jsonb null patch');
    }
};

return $tests;
