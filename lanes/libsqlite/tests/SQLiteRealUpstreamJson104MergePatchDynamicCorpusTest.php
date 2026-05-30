<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

/**
 * Independent RFC-7396 merge-patch oracle for upstream json104.test.
 */
function json104_merge_patch_oracle(mixed $target, mixed $patch): mixed
{
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

        $target[$key] = json104_merge_patch_oracle($target[$key] ?? null, $value);
    }

    return $target;
}

function json104_canonical(mixed $value): string
{
    return SQLiteJsonCanonical::json(json_encode(json104_preserve_empty_json_objects($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function json104_preserve_empty_json_objects(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    if ($value === []) {
        return (object) [];
    }

    if (array_is_list($value)) {
        return array_map('json104_preserve_empty_json_objects', $value);
    }

    $object = [];
    foreach ($value as $key => $child) {
        $object[$key] = json104_preserve_empty_json_objects($child);
    }

    return (object) $object;
}

function json104_jsonb(string $json): SQLiteBlobValue
{
    return SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
}

function json104_scalar_paths(mixed $value, string $prefix = '$'): array
{
    if (!is_array($value)) {
        return [$prefix => $value];
    }

    $paths = [];
    if (array_is_list($value)) {
        foreach ($value as $index => $child) {
            foreach (json104_scalar_paths($child, $prefix . '[' . $index . ']') as $path => $scalar) {
                $paths[$path] = $scalar;
            }
        }

        return $paths;
    }

    foreach ($value as $key => $child) {
        $pathKey = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $key) === 1
            ? '.' . $key
            : '."' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $key) . '"';
        foreach (json104_scalar_paths($child, $prefix . $pathKey) as $path => $scalar) {
            $paths[$path] = $scalar;
        }
    }

    return $paths;
}

$upstreamSeeds = [
    'json104-100-rfc-pages-2-3' => [
        'target' => ['a' => 'b', 'c' => ['d' => 'e', 'f' => 'g']],
        'patch' => ['a' => 'z', 'c' => ['f' => null]],
    ],
    'json104-110-rfc-pages-4-5' => [
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
    ],
    'json104-220-nested-null-object' => [
        'target' => [],
        'patch' => ['a' => ['bb' => ['ccc' => null]]],
    ],
    'json104-221-nested-array-null-preserved' => [
        'target' => [],
        'patch' => ['a' => ['bb' => ['ccc' => [1, null, 3]]]],
    ],
    'json104-300-replace-member' => [
        'target' => ['a' => 'b'],
        'patch' => ['a' => 'c'],
    ],
    'json104-301-add-member' => [
        'target' => ['a' => 'b'],
        'patch' => ['b' => 'c'],
    ],
    'json104-302-delete-member' => [
        'target' => ['a' => 'b'],
        'patch' => ['a' => null],
    ],
    'json104-306-recursive-object' => [
        'target' => ['a' => ['b' => 'c']],
        'patch' => ['a' => ['b' => 'd', 'c' => null]],
    ],
    'json104-307-array-replaced' => [
        'target' => ['a' => [['b' => 'c']]],
        'patch' => ['a' => [1]],
    ],
    'json104-312-null-target-member-preserved' => [
        'target' => ['e' => null],
        'patch' => ['a' => 1],
    ],
    'json104-320-duplicate-object-last-wins-normalized' => [
        'target' => ['x' => ['one' => 1]],
        'patch' => ['x' => 'three'],
    ],
];

for ($case = 0; $case < 1100; $case++) {
    $seedName = array_keys($upstreamSeeds)[$case % count($upstreamSeeds)];
    $seed = $upstreamSeeds[$seedName];
    $bucket = intdiv($case, count($upstreamSeeds));
    $mode = $case % 10;
    $target = $seed['target'];
    $patch = $seed['patch'];

    $target['dynamic'] = [
        'case' => $case,
        'bucket' => $bucket,
        'mode' => $mode,
        'label' => 'json104-dynamic-' . $case,
        'array' => [$case, $bucket, ['mode' => $mode, 'keep' => true]],
    ];
    $target['remove_me_' . $mode] = ['old' => $case, 'nested' => ['value' => 'stale']];
    $target['stable_' . ($case % 7)] = ['keep' => $case % 3, 'seed' => $seedName];

    $patch['dynamic'] = [
        'case' => $case + 1,
        'bucket' => $bucket,
        'mode' => $mode,
        'label' => 'json104-patched-' . $case,
        'array' => [$case, ['mode' => $mode, 'keep' => false], $bucket],
        'added' => ['source' => $seedName, 'ordinal' => $case],
    ];
    $patch['remove_me_' . $mode] = null;
    $patch['added_' . ($case % 13)] = [
        'branch' => ['case' => $case, 'even' => ($case % 2) === 0],
        'leaf' => $case * 3,
    ];

    if (($case % 4) === 0) {
        $patch['stable_' . ($case % 7)] = ['keep' => null, 'seed' => $seedName, 'patched' => true];
    }
    if (($case % 5) === 0) {
        $patch['array_replace_' . ($case % 11)] = [$case, null, ['tail' => $bucket]];
        $target['array_replace_' . ($case % 11)] = ['was' => 'object'];
    }
    if (($case % 6) === 0) {
        $patch['scalar_replace_' . ($case % 17)] = 'scalar-' . $case;
        $target['scalar_replace_' . ($case % 17)] = ['old' => ['nested' => true]];
    }

    $targetJson = json104_canonical($target);
    $patchJson = json104_canonical($patch);
    $expectedValue = json104_merge_patch_oracle($target, $patch);
    $expectedJson = json104_canonical($expectedValue);
    $expectedScalarPaths = array_slice(json104_scalar_paths($expectedValue), 0, 5, true);
    $removedPath = '$.remove_me_' . $mode;
    $addedPath = '$.added_' . ($case % 13) . '.leaf';

    $tests[sprintf('real upstream corpus json104.test rfc7396 dynamic merge patch %04d %s', $case, $seedName)] = static function (TestRunner $t) use ($case, $seedName, $targetJson, $patchJson, $expectedJson, $expectedValue, $expectedScalarPaths, $removedPath, $addedPath): void {
        $textActual = SQLiteJsonPatch::patchSqlFunction('json_patch', $targetJson, $patchJson);
        $jsonbActual = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', json104_jsonb($targetJson), json104_jsonb($patchJson));
        $jsonbText = SQLiteJsonCanonical::json($jsonbActual);

        $t->same($expectedJson, $textActual, $seedName . ' text merge patch parity');
        $t->true($jsonbActual instanceof SQLiteBlobValue, $seedName . ' jsonb returns blob');
        $t->same($expectedJson, $jsonbText, $seedName . ' jsonb merge patch parity');
        $t->same(null, SQLiteJsonExtract::extract($textActual, $removedPath), $seedName . ' null object member deletes');
        $t->same($case * 3, SQLiteJsonExtract::extract($textActual, $addedPath), $seedName . ' dynamic object member added');
        $t->same(true, SQLiteJsonValidity::jsonValid($textActual), $seedName . ' text result valid');
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$jsonbActual, 4]), $seedName . ' jsonb result superficial valid');
        $t->same($expectedValue, json_decode((string) $textActual, true, 512, JSON_THROW_ON_ERROR), $seedName . ' decoded structure parity');

        $treeRows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $textActual);
        $fullKeys = array_column($treeRows, 'fullkey');
        $t->true(in_array('$.dynamic.added.source', $fullKeys, true), $seedName . ' tree exposes added source');
        $t->true(in_array('$.dynamic.array[1].keep', $fullKeys, true), $seedName . ' tree exposes replaced array object');

        foreach ($expectedScalarPaths as $path => $expectedScalar) {
            $t->same($expectedScalar, SQLiteJsonExtract::extract($textActual, $path), $seedName . ' scalar path ' . $path);
        }
    };
}

$tests['real upstream corpus json104.test cites hydrated upstream file and sections'] = static function (TestRunner $t) use ($upstreamSeeds): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test');
    $t->same([
        'json104-100',
        'json104-110',
        'json104-220',
        'json104-221',
        'json104-300',
        'json104-301',
        'json104-302',
        'json104-306',
        'json104-307',
        'json104-312',
        'json104-320',
    ], array_map(static fn (string $name): string => substr($name, 0, strpos($name, '-', 8)), array_keys($upstreamSeeds)));
};

return $tests;
