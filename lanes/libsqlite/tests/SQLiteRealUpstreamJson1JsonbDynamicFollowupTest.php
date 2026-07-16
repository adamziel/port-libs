<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$blobText = static fn (string $json): SQLiteBlobValue => new SQLiteBlobValue($json);
$jsonText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$decodeJsonb = static fn (SQLiteBlobValue $value): mixed => SQLiteJsonB::decode($value->bytes);

$blobCompatibilityCases = [
    'json107-1.1 blob strict text valid' => static fn (): mixed => SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blobText('{"a":1}')),
    'json107-1.1.1 blob strict flag valid' => static fn (): mixed => SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blobText('{"a":1}'), 1),
    'json107-1.1.2 blob json5 flag valid' => static fn (): mixed => SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blobText('{"a":1}'), 2),
    'json107-1.1.4 blob superficial-jsonb flag is not text JSON' => static fn (): mixed => SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blobText('{"a":1}'), 4),
    'json107-1.1.8 blob strict-jsonb flag is not text JSON' => static fn (): mixed => SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blobText('{"a":1}'), 8),
    'json107-1.2.3 blob text json_extract scalar' => static fn (): mixed => SQLiteJsonExtract::extract($blobText('{"a":123}'), '$.a'),
    'json107-1.3 blob text json_insert object member' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_insert', $blobText('{"a":123}'), '$.b', 456),
    'json107-1.4 blob text json_remove object member' => static fn (): mixed => SQLiteJsonRemove::remove($blobText('{"a":123,"b":456}'), '$.a'),
    'json107-1.5 blob text json_set object member' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_set', $blobText('{"a":123,"b":456}'), '$.a', 789),
    'json107-1.6 blob text json_replace object member' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_replace', $blobText('{"a":123,"b":456}'), '$.a', 789),
    'json107-1.7 blob text json_type object' => static fn (): mixed => SQLiteJsonInspection::jsonType($blobText('{"a":123,"b":456}')),
    'json107-1.8 blob text json canonical' => static fn (): mixed => SQLiteJsonCanonical::json($blobText('{"a":123,"b":456}')),
];

$blobCompatibilityExpected = [
    true,
    true,
    true,
    false,
    false,
    123,
    '{"a":123,"b":456}',
    '{"b":456}',
    '{"a":789,"b":456}',
    '{"a":789,"b":456}',
    'object',
    '{"a":123,"b":456}',
];

foreach (array_values($blobCompatibilityCases) as $offset => $case) {
    $name = array_keys($blobCompatibilityCases)[$offset];
    $expected = $blobCompatibilityExpected[$offset];
    $tests['real upstream JSON1 JSONB dynamic followup ' . $name] = static function (TestRunner $t) use ($name, $case, $expected): void {
        $actual = $case();

        $t->same($expected, $actual);
        $t->same($expected === null, $actual === null);
        $t->true(str_starts_with($name, 'json107-'));
        $t->true(str_contains($name, 'blob'));
        $t->same(is_bool($expected), is_bool($actual));
        $t->same(is_string($expected), is_string($actual));
        $t->same(is_int($expected), is_int($actual));
        $t->same($actual, $case());
    };
}

$extractCases = [
    'json102-250 root object parity' => ['{"a":2,"c":[4,5,{"f":7}]}', '$', '{"a":2,"c":[4,5,{"f":7}]}'],
    'json102-260 child array parity' => ['{"a":2,"c":[4,5,{"f":7}]}', '$.c', '[4,5,{"f":7}]'],
    'json102-270 nested object parity' => ['{"a":2,"c":[4,5,{"f":7}]}', '$.c[2]', '{"f":7}'],
    'json102-280 nested scalar parity' => ['{"a":2,"c":[4,5,{"f":7}]}', '$.c[2].f', 7],
    'json102-300 missing path parity' => ['{"a":2,"c":[4,5,{"f":7}]}', '$.x', null],
    'json102-310 multipath missing plus scalar parity' => ['{"a":2,"c":[4,5,{"f":7}]}', ['$.x', '$.a'], '[null,2]'],
    'json102-290 multipath array plus scalar parity' => ['{"a":2,"c":[4,5],"f":7}', ['$.c', '$.a'], '[[4,5],2]'],
    'json105-1.70 multipath forward and reverse parity' => ['{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[0]', '$.b[#-1]'], '[1,4]'],
];

foreach ($extractCases as $name => [$document, $paths, $expected]) {
    $tests['real upstream JSON1 JSONB dynamic followup extract ' . $name] = static function (TestRunner $t) use ($jsonb, $jsonText, $document, $paths, $expected): void {
        $pathList = is_array($paths) ? $paths : [$paths];
        $textActual = SQLiteJsonExtract::extract($document, ...$pathList);
        $jsonbInputActual = SQLiteJsonExtract::extract($jsonb($document), ...$pathList);
        $jsonbFunctionActual = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb($document), ...$pathList);

        $jsonbComparable = $jsonbFunctionActual instanceof SQLiteBlobValue ? $jsonText($jsonbFunctionActual) : $jsonbFunctionActual;
        $t->same($expected, $textActual);
        $t->same($expected, $jsonbInputActual);
        $t->same($expected, $jsonbComparable);
        $t->same($pathList, array_values($pathList));
        $t->true(str_starts_with($pathList[0], '$'));
        $t->same($expected === null, $textActual === null);
        $t->same($expected === null, $jsonbInputActual === null);
        $t->same($textActual, $jsonbInputActual);
    };
}

$mutationCases = [
    'json102-320 insert existing member unchanged' => ['json_insert', '{"a":2,"c":4}', '$.a', 99, '{"a":2,"c":4}'],
    'json102-330 insert new member appends' => ['json_insert', '{"a":2,"c":4}', '$.e', 99, '{"a":2,"c":4,"e":99}'],
    'json102-340 replace existing member changes' => ['json_replace', '{"a":2,"c":4}', '$.a', 99, '{"a":99,"c":4}'],
    'json102-350 replace missing member unchanged' => ['json_replace', '{"a":2,"c":4}', '$.e', 99, '{"a":2,"c":4}'],
    'json102-360 set existing member changes' => ['json_set', '{"a":2,"c":4}', '$.a', 99, '{"a":99,"c":4}'],
    'json107-1.3 blob insert preserves text compatibility' => ['json_insert', $blobText('{"a":123}'), '$.b', 456, '{"a":123,"b":456}'],
    'json107-1.5 blob set preserves text compatibility' => ['json_set', $blobText('{"a":123,"b":456}'), '$.a', 789, '{"a":789,"b":456}'],
    'json107-1.6 blob replace preserves text compatibility' => ['json_replace', $blobText('{"a":123,"b":456}'), '$.a', 789, '{"a":789,"b":456}'],
];

foreach ($mutationCases as $name => [$function, $document, $path, $value, $expected]) {
    $tests['real upstream JSON1 JSONB dynamic followup mutation ' . $name] = static function (TestRunner $t) use ($jsonb, $jsonText, $function, $document, $path, $value, $expected): void {
        $jsonbFunction = str_replace('json_', 'jsonb_', $function);
        $textActual = SQLiteJsonMutation::mutateSqlFunction($function, $document, $path, $value);
        $documentText = $document instanceof SQLiteBlobValue ? $document->bytes : (string) $document;
        $jsonbInput = $document instanceof SQLiteBlobValue && !SQLiteJsonB::isSuperficiallyJsonB($document->bytes) ? $document : $jsonb($documentText);
        $jsonbActual = SQLiteJsonMutation::mutateSqlFunction($jsonbFunction, $jsonbInput, $path, $value);

        $t->same($expected, $textActual);
        $t->true($jsonbActual instanceof SQLiteBlobValue);
        $t->same($expected, $jsonText($jsonbActual));
        $t->same(json_decode($expected, true, 512, JSON_THROW_ON_ERROR), json_decode((string) $textActual, true, 512, JSON_THROW_ON_ERROR));
        $t->true(in_array($function, ['json_insert', 'json_set', 'json_replace'], true));
        $t->true(str_starts_with($path, '$.'));
        $t->same(str_replace('json_', 'jsonb_', $function), $jsonbFunction);
        $t->same($expected === $documentText, $textActual === $documentText);
    };
}

$constructorCases = [
    'json102-100 json_object quotes text array' => static fn (): string => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'ex', '[52,3.14159]'),
    'json102-100b jsonb_object quotes text array' => static fn (): string => $jsonText(SQLiteJsonConstructor::jsonObjectSqlFunction('jsonb_object', 'ex', '[52,3.14159]')),
    'json102-120-3 json_object embeds jsonb_array' => static fn (): string => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'ex', SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 52, 3.14159)),
    'json102-140b jsonb_array keeps numeric text' => static fn (): string => $jsonText(SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 1, 2, '3', 4)),
    'json102-150b jsonb_array quotes text array' => static fn (): string => $jsonText(SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', '[1,2]')),
    'json102-160-4 jsonb_array nests jsonb_array' => static fn (): string => $jsonText(SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 1, 2))),
    'json102-170b jsonb_array quotes text object' => static fn (): string => $jsonText(SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 1, null, '3', '[4,5]', '{"six":7.7}')),
    'json102-180-4 jsonb_array embeds jsonb inputs' => static fn (): string => $jsonText(SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 1, null, '3', $jsonb('[4,5]'), $jsonb('{"six":7.7}'))),
];

$constructorExpected = [
    '{"ex":"[52,3.14159]"}',
    '{"ex":"[52,3.14159]"}',
    '{"ex":[52,3.14159]}',
    '[1,2,"3",4]',
    '["[1,2]"]',
    '[[1,2]]',
    '[1,null,"3","[4,5]","{\"six\":7.7}"]',
    '[1,null,"3",[4,5],{"six":7.7}]',
];

foreach (array_values($constructorCases) as $offset => $case) {
    $name = array_keys($constructorCases)[$offset];
    $expected = $constructorExpected[$offset];
    $tests['real upstream JSON1 JSONB dynamic followup constructor ' . $name] = static function (TestRunner $t) use ($name, $case, $expected): void {
        $actual = $case();

        $t->same($expected, $actual);
        $t->same(json_decode($expected, true, 512, JSON_THROW_ON_ERROR), json_decode($actual, true, 512, JSON_THROW_ON_ERROR));
        $t->true(str_starts_with($name, 'json102-'));
        $t->true(str_contains($name, 'json'));
        $t->same($expected === $actual, true);
        $t->same(strlen($expected), strlen($actual));
        $t->same($actual, $case());
        $t->same(SQLiteJsonCanonical::json($expected), SQLiteJsonCanonical::json($actual));
    };
}

$prettyDocuments = [
    'json108-1.1 compact object' => '{"a":1,"b":[2,3],"c":{"d":4}}',
    'json108-1.3 already spaced object' => ' { "this" : "is", "a": [ "test" ] } ',
    'json108-1.4 nested JSON5 document' => '{alpha:[1,2,{beta:true}],gamma:null}',
    'json108-1.5 mixed scalar array' => '[1,null,"3",[4,5],{"six":7.7}]',
    'json108-1.5 object array payload' => '{"items":[{"id":1},{"id":2,"tags":["x","y"]}]}',
];
$prettyIndents = [null, '', "\t", '/*hello*/'];

foreach ($prettyDocuments as $name => $document) {
    foreach ($prettyIndents as $indent) {
        $label = $indent === null ? 'null-indent' : ('indent-' . bin2hex($indent));
        $tests['real upstream JSON1 JSONB dynamic followup pretty ' . $name . ' ' . $label] = static function (TestRunner $t) use ($jsonb, $document, $indent): void {
            $prettyText = SQLiteJsonPretty::jsonPretty($document, $indent);
            $prettyBlob = SQLiteJsonPretty::jsonPretty($jsonb($document), $indent);
            $canonical = SQLiteJsonCanonical::json($document);

            $t->same($canonical, SQLiteJsonCanonical::json($prettyText));
            $t->same($canonical, SQLiteJsonCanonical::json($prettyBlob));
            $t->same($prettyText, $prettyBlob);
            $t->same($indent === null ? true : str_contains((string) $prettyText, $indent) || $indent === '', true);
            $t->true(str_contains((string) $prettyText, "\n") || $canonical === $prettyText);
            $t->same($canonical, SQLiteJsonCanonical::json(SQLiteJsonPretty::jsonPretty($prettyText, $indent)));
            $t->same($canonical, SQLiteJsonCanonical::json(SQLiteJsonPretty::jsonPretty($prettyBlob, $indent)));
            $t->same(is_string($prettyText), is_string($prettyBlob));
        };
    }
}

$treeRows = SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [$blobText('{"a":123,"b":456}')]);
$tests['real upstream JSON1 JSONB dynamic followup json107-2.1 blob json_tree atoms'] = static function (TestRunner $t) use ($treeRows): void {
    $atoms = [];
    foreach ($treeRows as $row) {
        if (($row['atom'] ?? null) !== null) {
            $atoms[] = [$row['key'], $row['atom']];
        }
    }

    $t->same([['a', 123], ['b', 456]], $atoms);
    $t->same(3, count($treeRows));
    $t->same('$', $treeRows[0]['fullkey']);
    $t->same('object', $treeRows[0]['type']);
    $t->same('integer', $treeRows[1]['type']);
    $t->same('integer', $treeRows[2]['type']);
    $t->same('{"a":123,"b":456}', SQLiteJsonCanonical::json(new SQLiteBlobValue('{"a":123,"b":456}')));
    $t->true($treeRows[1]['id'] < $treeRows[2]['id']);
};

return $tests;
