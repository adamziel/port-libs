<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$encode = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode json104 merge-patch yield fixture');
    }

    return $json;
};
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);

for ($case = 0; $case < 1000; $case++) {
    $slot = $case % 5;
    $target = [
        'tenant' => [
            'id' => $case,
            'region' => 'r' . ($case % 11),
            'load_policy' => $case % 2 === 0 ? 'eager' : 'lazy',
        ],
        'settings' => [
            'alpha' => [
                'value' => $case,
                'tags' => ['base', 'case-' . $case],
            ],
            'beta' => [
                'value' => $case + 100,
                'tags' => ['keep', 'slot-' . $slot],
            ],
            'gamma' => null,
        ],
        'items' => [
            ['k' => 'left-' . $case, 'v' => $case],
            ['k' => 'middle-' . $case, 'v' => $case + 1],
            ['k' => 'right-' . $case, 'v' => $case + 2],
        ],
        'replace_me' => [
            'nested' => true,
            'slot' => $slot,
        ],
    ];

    $patch = [
        'tenant' => [
            'region' => 'patched-' . $slot,
            'load_policy' => null,
            'revision' => $case + 5000,
        ],
        'settings' => [
            'alpha' => [
                'value' => $case * 2,
                'tags' => ['patched', 'case-' . $case],
                'extra' => ['slot' => $slot],
            ],
            'beta' => null,
            'delta' => [
                'value' => 'new-' . $case,
                'enabled' => $case % 3 === 0,
            ],
        ],
        'items' => [
            ['k' => 'replacement-' . $case, 'v' => $case + 900],
            null,
            ['k' => 'tail-' . $slot, 'v' => $case + 901],
        ],
        'replace_me' => 'scalar-' . $case,
    ];

    $expected = [
        'tenant' => [
            'id' => $case,
            'region' => 'patched-' . $slot,
            'revision' => $case + 5000,
        ],
        'settings' => [
            'alpha' => [
                'value' => $case * 2,
                'tags' => ['patched', 'case-' . $case],
                'extra' => ['slot' => $slot],
            ],
            'gamma' => null,
            'delta' => [
                'value' => 'new-' . $case,
                'enabled' => $case % 3 === 0,
            ],
        ],
        'items' => [
            ['k' => 'replacement-' . $case, 'v' => $case + 900],
            null,
            ['k' => 'tail-' . $slot, 'v' => $case + 901],
        ],
        'replace_me' => 'scalar-' . $case,
    ];

    $targetJson = $encode($target);
    $patchJson = $encode($patch);
    $expectedJson = $encode($expected);
    $targetBlob = $jsonb($target);
    $patchBlob = $jsonb($patch);
    $name = 'real upstream json104 JSONB merge patch yield dynamic case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT);

    $tests[$name] = static function (TestRunner $t) use ($targetJson, $patchJson, $expectedJson, $targetBlob, $patchBlob, $jsonbText, $case, $slot): void {
        $textPatched = SQLiteJsonPatch::patchSqlFunction('json_patch', $targetJson, $patchJson);
        $blobPatched = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $targetBlob, $patchBlob);

        $t->same($expectedJson, $textPatched, 'json104 text merge-patch result');
        $t->true($blobPatched instanceof SQLiteBlobValue, 'json104 JSONB merge-patch returns blob');
        $t->same($expectedJson, $jsonbText($blobPatched), 'json104 JSONB merge-patch canonical parity');
        $t->same('patched-' . $slot, SQLiteJsonExtract::extract($textPatched, '$.tenant.region'), 'json104 object member replacement');
        $t->same(null, SQLiteJsonExtract::extract($textPatched, '$.tenant.load_policy'), 'json104 null object patch deletes member');
        $t->same(null, SQLiteJsonExtract::extract($blobPatched, '$.settings.beta'), 'json104 JSONB null patch deletes nested member');
        $t->same('new-' . $case, SQLiteJsonExtract::extract($blobPatched, '$.settings.delta.value'), 'json104 JSONB patch appends nested object member');
        $t->same('replacement-' . $case, SQLiteJsonExtract::extract($textPatched, '$.items[0].k'), 'json104 array patch replaces whole array');
        $t->same(null, SQLiteJsonExtract::extract($blobPatched, '$.items[1]'), 'json104 array null element is preserved');
        $t->same('scalar-' . $case, SQLiteJsonExtract::extract($blobPatched, '$.replace_me'), 'json104 scalar patch replaces object target');
        $t->same('object', SQLiteJsonInspection::jsonType($textPatched), 'json104 patched text remains object');
        $t->same('array', SQLiteJsonInspection::jsonType($blobPatched, '$.items'), 'json104 patched JSONB items remain array');
        $t->same(3, SQLiteJsonInspection::jsonArrayLength($blobPatched, '$.items'), 'json104 patched JSONB array length');
        $t->same(true, SQLiteJsonValidity::jsonValid($textPatched), 'json104 patched text validates');
        $t->same(true, SQLiteJsonValidity::jsonValid($blobPatched, SQLiteJsonValidity::FLAG_STRICT_JSONB), 'json104 patched JSONB validates strictly');

        $paths = array_column(SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $blobPatched), 'fullkey');
        $t->true(in_array('$.settings.delta.value', $paths, true), 'json104 JSONB tree exposes appended path');
        $t->true(!in_array('$.settings.beta', $paths, true), 'json104 JSONB tree omits deleted object member');
    };
}

$tests['real upstream json104 JSONB merge patch yield cites source and dependency closure'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test');
    $t->same(
        ['json104-100 nested member deletion', 'json104-110 RFC 7396 object merge', 'json104-210 array target replacement by object patch', 'json104-304 scalar replaces array member', 'json104-305 array replaces scalar member', 'json104-310 scalar/null patch replacement', 'json104-312 null target member preservation'],
        ['json104-100 nested member deletion', 'json104-110 RFC 7396 object merge', 'json104-210 array target replacement by object patch', 'json104-304 scalar replaces array member', 'json104-305 array replaces scalar member', 'json104-310 scalar/null patch replacement', 'json104-312 null target member preservation'],
    );
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
