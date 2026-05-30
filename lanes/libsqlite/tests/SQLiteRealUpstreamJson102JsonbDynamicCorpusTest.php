<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$jsonb = static fn (string $json): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR)));
$decode = static function (string|SQLiteBlobValue|null $value): mixed {
    if ($value === null) {
        return null;
    }
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonB::decode($value->bytes);
    }

    return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
};
$canonical = static fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$extractDocument = '{"a":2,"c":[4,5,{"f":7}]}';
foreach ([
    'json102-250 root object' => ['$' => '{"a":2,"c":[4,5,{"f":7}]}'],
    'json102-260 array member' => ['$.c' => '[4,5,{"f":7}]'],
    'json102-270 nested object element' => ['$.c[2]' => '{"f":7}'],
] as $name => $case) {
    $path = array_key_first($case);
    $expected = $case[$path];
    $tests['real upstream json102 extract text and jsonb ' . $name] = static function (TestRunner $t) use ($extractDocument, $jsonb, $decode, $canonical, $path, $expected): void {
        $t->same($expected, SQLiteJsonExtract::extractSqlFunction('json_extract', $extractDocument, $path));
        $t->same($expected, SQLiteJsonExtract::extractSqlFunction('json_extract', $jsonb($extractDocument), $path));
        $t->same($expected, $canonical($decode(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $extractDocument, $path))));
        $t->same($expected, $canonical($decode(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb($extractDocument), $path))));
    };
}

$tests['real upstream json102 extract scalar leaf json102-280'] = static function (TestRunner $t) use ($extractDocument): void {
    $t->same(7, SQLiteJsonExtract::extractSqlFunction('json_extract', $extractDocument, '$.c[2].f'));
    $t->same(7, SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $extractDocument, '$.c[2].f'));
};

$tests['real upstream json102 extract multipath json102-290'] = static function (TestRunner $t) use ($jsonb, $decode, $canonical): void {
    $document = '{"a":2,"c":[4,5],"f":7}';
    $expected = '[[4,5],2]';

    $t->same($expected, SQLiteJsonExtract::extractSqlFunction('json_extract', $document, '$.c', '$.a'));
    $t->same($expected, SQLiteJsonExtract::extractSqlFunction('json_extract', $jsonb($document), '$.c', '$.a'));
    $t->same($expected, $canonical($decode(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $document, '$.c', '$.a'))));
    $t->same($expected, $canonical($decode(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb($document), '$.c', '$.a'))));
};

$tests['real upstream json102 extract missing path json102-300 and multipath json102-310'] = static function (TestRunner $t) use ($extractDocument, $jsonb, $decode, $canonical): void {
    $t->same(null, SQLiteJsonExtract::extractSqlFunction('json_extract', $extractDocument, '$.x'));
    $t->same(null, SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $extractDocument, '$.x'));
    $t->same('[null,2]', SQLiteJsonExtract::extractSqlFunction('json_extract', $extractDocument, '$.x', '$.a'));
    $t->same('[null,2]', SQLiteJsonExtract::extractSqlFunction('json_extract', $jsonb($extractDocument), '$.x', '$.a'));
    $t->same('[null,2]', $canonical($decode(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $extractDocument, '$.x', '$.a'))));
    $t->same('[null,2]', $canonical($decode(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb($extractDocument), '$.x', '$.a'))));
};

foreach ([
    'json102-360 replace existing member' => ['$.a', 99, '{"a":99,"c":4}'],
    'json102-370 create new member' => ['$.e', 99, '{"a":2,"c":4,"e":99}'],
    'json102-380 SQL text value remains quoted JSON text' => ['$.c', '[97,96]', '{"a":2,"c":"[97,96]"}'],
] as $name => [$path, $value, $expected]) {
    $tests['real upstream json102 set scalar text and jsonb ' . $name] = static function (TestRunner $t) use ($jsonb, $decode, $canonical, $path, $value, $expected): void {
        $document = '{"a":2,"c":4}';

        $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_set', $document, $path, $value));
        $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_set', $jsonb($document), $path, $value));
        $t->same($expected, $canonical($decode(SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $document, $path, $value))));
        $t->same($expected, $canonical($decode(SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $jsonb($document), $path, $value))));
    };
}

foreach ([
    'json102-390 json subtype array value' => new SQLiteJsonSubtypeValue('[97,96]'),
    'json102-390 jsonb value' => new SQLiteBlobValue(SQLiteJsonB::encode([97, 96])),
] as $name => $value) {
    $tests['real upstream json102 set structural value ' . $name] = static function (TestRunner $t) use ($jsonb, $decode, $canonical, $value): void {
        $document = '{"a":2,"c":4}';
        $expected = '{"a":2,"c":[97,96]}';

        $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_set', $document, '$.c', $value));
        $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_set', $jsonb($document), '$.c', $value));
        $t->same($expected, $canonical($decode(SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $document, '$.c', $value))));
        $t->same($expected, $canonical($decode(SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $jsonb($document), '$.c', $value))));
    };
}

foreach ([
    'json102-440 remove middle array element' => [['$[2]'], '[0,1,3,4]'],
    'json102-450 remove middle then first' => [['$[2]', '$[0]'], '[1,3,4]'],
    'json102-460 remove first then shifted middle' => [['$[0]', '$[2]'], '[1,2,4]'],
] as $name => [$paths, $expected]) {
    $tests['real upstream json102 remove array text and jsonb ' . $name] = static function (TestRunner $t) use ($jsonb, $decode, $canonical, $paths, $expected): void {
        $document = '[0,1,2,3,4]';

        $t->same($expected, SQLiteJsonRemove::removeSqlFunction('json_remove', $document, ...$paths));
        $t->same($expected, SQLiteJsonRemove::removeSqlFunction('json_remove', $jsonb($document), ...$paths));
        $t->same($expected, $canonical($decode(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $document, ...$paths))));
        $t->same($expected, $canonical($decode(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonb($document), ...$paths))));
    };
}

foreach ([
    'json102-445.1 remove index equal length is no op' => '$[5]',
    'json102-445.2 remove index beyond length is no op' => '$[6]',
    'json102-445.3 remove huge 32-bit index is no op' => '$[4294967295]',
    'json102-445.4 remove huge 32-bit plus one index is no op' => '$[4294967296]',
    'json102-445.5 remove huge 32-bit plus two index is no op' => '$[4294967297]',
    'json102-445.6 remove very huge index is no op' => '$[42949672950]',
    'json102-445.7 remove very huge tens index is no op' => '$[42949672960]',
] as $name => $path) {
    $tests['real upstream json102 remove out of range ' . $name] = static function (TestRunner $t) use ($path): void {
        $t->same('[0,1,2,3,4]', SQLiteJsonRemove::removeSqlFunction('json_remove', '[0,1,2,3,4]', $path));
    };
}

foreach ([
    'json102-470 no path object remove is no op' => [[], '{"x":25,"y":42}'],
    'json102-480 missing member remove is no op' => [['$.z'], '{"x":25,"y":42}'],
    'json102-490 remove present member' => [['$.y'], '{"x":25}'],
    'json102-500 remove root returns null' => [['$'], null],
] as $name => [$paths, $expected]) {
    $tests['real upstream json102 remove object text and jsonb ' . $name] = static function (TestRunner $t) use ($jsonb, $decode, $canonical, $paths, $expected): void {
        $document = '{"x":25,"y":42}';

        $t->same($expected, SQLiteJsonRemove::removeSqlFunction('json_remove', $document, ...$paths));
        $t->same($expected, SQLiteJsonRemove::removeSqlFunction('json_remove', $jsonb($document), ...$paths));
        $jsonbText = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $document, ...$paths);
        $jsonbBlob = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonb($document), ...$paths);
        $t->same($expected, $jsonbText === null ? null : $canonical($decode($jsonbText)));
        $t->same($expected, $jsonbBlob === null ? null : $canonical($decode($jsonbBlob)));
    };
}

foreach ([
    'json102-510 root object type' => ['$' => 'object'],
    'json102-530 array member type' => ['$.a' => 'array'],
    'json102-540 integer element type' => ['$.a[0]' => 'integer'],
    'json102-550 real element type' => ['$.a[1]' => 'real'],
    'json102-560 true element type' => ['$.a[2]' => 'true'],
    'json102-570 false element type' => ['$.a[3]' => 'false'],
    'json102-580 null element type' => ['$.a[4]' => 'null'],
] as $name => $case) {
    $path = array_key_first($case);
    $expected = $case[$path];
    $tests['real upstream json102 json type text and jsonb ' . $name] = static function (TestRunner $t) use ($jsonb, $path, $expected): void {
        $document = '{"a":[2,3.5,true,false,null,"x"]}';

        $t->same($expected, SQLiteJsonInspection::jsonType($document, $path));
        $t->same($expected, SQLiteJsonInspection::jsonType($jsonb($document), $path));
    };
}

return $tests;
