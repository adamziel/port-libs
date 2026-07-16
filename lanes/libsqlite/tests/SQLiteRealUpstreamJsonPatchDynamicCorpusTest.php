<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonText = static fn (string|SQLiteBlobValue|null $value): ?string => $value instanceof SQLiteBlobValue
    ? SQLiteJsonCanonical::json($value)
    : $value;
$decode = static function (string|SQLiteBlobValue|null $value): mixed {
    if ($value === null) {
        return null;
    }
    if ($value instanceof SQLiteBlobValue) {
        return json_decode((string) SQLiteJsonCanonical::json($value), true, 512, JSON_THROW_ON_ERROR);
    }

    return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
};

$patchCases = [
    'json104-100 rfc object member delete' => [
        '{"a":"b","c":{"d":"e","f":"g"}}',
        '{"a":"z","c":{"f":null}}',
        '{"a":"z","c":{"d":"e"}}',
    ],
    'json104-101 json5 patch object member delete' => [
        '{"a":"b","c":{"d":"e","f":"g"}}',
        '{a:"z",c:{f:null}}',
        '{"a":"z","c":{"d":"e"}}',
    ],
    'json104-102 json5 target object member delete' => [
        '{a:"b",c:{d:"e",f:"g"}}',
        '{"a":"z","c":{"f":null}}',
        '{"a":"z","c":{"d":"e"}}',
    ],
    'json104-103 json5 target and patch object member delete' => [
        '{a:"b",c:{d:"e",f:"g"}}',
        '{a:"z",c:{f:null}}',
        '{"a":"z","c":{"d":"e"}}',
    ],
    'json104-110 rfc nested merge patch' => [
        '{"title":"Goodbye!","author":{"givenName":"John","familyName":"Doe"},"tags":["example","sample"],"content":"This will be unchanged"}',
        '{"title":"Hello!","phoneNumber":"+01-123-456-7890","author":{"familyName":null},"tags":["example"]}',
        '{"title":"Hello!","author":{"givenName":"John"},"tags":["example"],"content":"This will be unchanged","phoneNumber":"+01-123-456-7890"}',
    ],
    'json104-200 object patch replaces array root' => ['[1,2,3]', '{"x":null}', '{}'],
    'json104-210 object patch keeps non-null only' => ['[1,2,3]', '{"x":null,"y":1,"z":null}', '{"y":1}'],
    'json104-220 nested null creates empty object' => ['{}', '{"a":{"bb":{"ccc":null}}}', '{"a":{"bb":{}}}'],
    'json104-221 nested array null is preserved' => ['{}', '{"a":{"bb":{"ccc":[1,null,3]}}}', '{"a":{"bb":{"ccc":[1,null,3]}}}'],
    'json104-222 nested object null inside array is preserved' => ['{}', '{"a":{"bb":{"ccc":[1,{"dddd":null},3]}}}', '{"a":{"bb":{"ccc":[1,{"dddd":null},3]}}}'],
    'json104-300 replace scalar member' => ['{"a":"b"}', '{"a":"c"}', '{"a":"c"}'],
    'json104-301 append new member' => ['{"a":"b"}', '{"b":"c"}', '{"a":"b","b":"c"}'],
    'json104-302 delete only member' => ['{"a":"b"}', '{"a":null}', '{}'],
    'json104-303 delete one of two members' => ['{"a":"b","b":"c"}', '{"a":null}', '{"b":"c"}'],
    'json104-304 scalar replaces array member' => ['{"a":["b"]}', '{"a":"c"}', '{"a":"c"}'],
    'json104-305 array replaces scalar member' => ['{"a":"c"}', '{"a":["b"]}', '{"a":["b"]}'],
    'json104-306 nested delete and replace' => ['{"a":{"b":"c"}}', '{"a":{"b":"d","c":null}}', '{"a":{"b":"d"}}'],
    'json104-307 array replaces nested object array' => ['{"a":[{"b":"c"}]}', '{"a":[1]}', '{"a":[1]}'],
    'json104-308 array patch replaces array target' => ['["a","b"]', '["c","d"]', '["c","d"]'],
    'json104-309 array patch replaces object target' => ['{"a":"b"}', '["c"]', '["c"]'],
    'json104-310 null patch replaces object with json null' => ['{"a":"foo"}', 'null', 'null'],
    'json104-311 string patch replaces object with scalar' => ['{"a":"foo"}', '"bar"', '"bar"'],
    'json104-312 patch preserves target null member' => ['{"e":null}', '{"a":1}', '{"e":null,"a":1}'],
    'json104-313 object patch replaces array and drops null member' => ['[1,2]', '{"a":"b","c":null}', '{"a":"b"}'],
    'json104-314 nested null repeats empty object' => ['{}', '{"a":{"bb":{"ccc":null}}}', '{"a":{"bb":{}}}'],
    'json104-320 duplicate patch key uses final value' => ['{"x":{"one":1}}', '{"x":{"two":2},"x":"three"}', '{"x":"three"}'],
];

foreach ($patchCases as $upstream => [$target, $patch, $expected]) {
    $tests["real upstream {$upstream} json_patch dynamic text"] = static function (TestRunner $t) use ($target, $patch, $expected, $decode): void {
        $actual = SQLiteJsonPatch::patch($target, $patch);
        $t->same($expected, $actual);
        $t->same($decode($expected), $decode($actual));
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$actual]));
        $t->same(SQLiteJsonInspection::jsonType($expected), SQLiteJsonInspection::jsonType($actual));
    };
    $tests["real upstream {$upstream} jsonb_patch dynamic parity"] = static function (TestRunner $t) use ($jsonb, $jsonText, $target, $patch, $expected, $decode): void {
        $actual = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $jsonb($target), $jsonb($patch));
        $t->true($actual instanceof SQLiteBlobValue);
        $t->same($expected, $jsonText($actual));
        $t->same($decode($expected), $decode($actual));
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$actual, 4]));
    };
}

$rows = [];
$i = 1;
foreach ($patchCases as $upstream => [$target, $patch, $expected]) {
    $rows[] = [
        'case_id' => $i++,
        'upstream_id' => $upstream,
        'target_doc' => $target,
        'patch_doc' => $patch,
        'expected_doc' => $expected,
    ];
}

foreach ($rows as $row) {
    $tests['real upstream ' . $row['upstream_id'] . ' select sql json_patch dynamic row ' . $row['case_id']] = static function (TestRunner $t) use ($rows, $row, $decode): void {
        $result = SQLiteSelectSql::execute(
            'SELECT json_patch(target_doc, patch_doc) AS patched FROM app_settings WHERE case_id = ' . $row['case_id'],
            ['app_settings' => $rows],
        );
        $actual = $result[0]['patched'] ?? null;
        $t->same($row['expected_doc'], $actual);
        $t->same($decode($row['expected_doc']), $decode($actual));
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$actual]));
        $t->same(SQLiteJsonInspection::jsonType($row['expected_doc']), SQLiteJsonInspection::jsonType($actual));
    };
}

$quotedPathRows = [
    ['case_id' => 401, 'doc' => '{"a":1,"b":2}', 'path' => '$.b', 'write_path' => '$.c', 'write_value' => 3, 'expected' => '{"a":1,"b":2,"c":3}', 'extract' => 2],
    ['case_id' => 403, 'doc' => '{"a":1,"b":2,"c":3}', 'path' => '$."b"', 'write_path' => '$."b"', 'write_value' => 555, 'expected' => '{"a":1,"b":555,"c":3}', 'extract' => 2],
    ['case_id' => 405, 'doc' => '{"a":1,"b":555,"c":3}', 'path' => '$."d"', 'write_path' => '$."d"', 'write_value' => 4, 'expected' => '{"a":1,"b":555,"c":3,"d":4}', 'extract' => null],
];

foreach ($quotedPathRows as $row) {
    $tests['real upstream json104-' . $row['case_id'] . ' quoted object path dynamic extraction'] = static function (TestRunner $t) use ($row): void {
        $t->same($row['extract'], SQLiteJsonExtract::extractSqlFunction('json_extract', $row['doc'], $row['path']));
        $t->same(SQLiteJsonExtract::extractSqlFunction('json_extract', $row['doc'], $row['path']), SQLiteJsonExtract::extractSqlFunction('json_extract', $row['doc'], str_replace('$."', '$.', rtrim($row['path'], '"'))));
        $t->same($row['expected'], \PortLibs\LibSqlite\SQLiteJsonMutation::mutateSqlFunction('json_set', $row['doc'], $row['write_path'], $row['write_value']));
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$row['expected']]));
    };
}

$tests['real upstream json104-300a dynamic null target returns sql null'] = static function (TestRunner $t): void {
    $t->same(null, SQLiteJsonPatch::patch(null, '{"a":"c"}'));
    $t->same(null, SQLiteJsonPatch::patchSqlFunction('json_patch', null, '{"a":"c"}'));
    $t->same(null, SQLiteJsonPatch::patchSqlFunction('jsonb_patch', null, '{"a":"c"}'));
    $t->same('real-null', SQLiteJsonPatch::patch(null, '{"a":"c"}') ?? 'real-null');
};

$tests['real upstream json104-310a dynamic null patch returns sql null'] = static function (TestRunner $t): void {
    $t->same(null, SQLiteJsonPatch::patch('{"a":"foo"}', null));
    $t->same(null, SQLiteJsonPatch::patchSqlFunction('json_patch', '{"a":"foo"}', null));
    $t->same(null, SQLiteJsonPatch::patchSqlFunction('jsonb_patch', '{"a":"foo"}', null));
    $t->same('real-null', SQLiteJsonPatch::patch('{"a":"foo"}', null) ?? 'real-null');
};

$tests['real upstream json104 dynamic patch output can be removed and inspected'] = static function (TestRunner $t) use ($patchCases): void {
    foreach ($patchCases as $upstream => [$target, $patch, $expected]) {
        $actual = SQLiteJsonPatch::patch($target, $patch);
        $type = SQLiteJsonInspection::jsonType($actual);
        $withoutMissing = SQLiteJsonRemove::removeSqlFunction('json_remove', $actual, '$.definitely_missing');
        $t->same($expected, $withoutMissing, $upstream . ' missing remove stable');
        $t->same($type, SQLiteJsonInspection::jsonType($withoutMissing), $upstream . ' type stable');
    }
};

return $tests;
