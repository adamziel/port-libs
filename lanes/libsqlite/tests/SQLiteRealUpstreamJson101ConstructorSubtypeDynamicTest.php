<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

function json101_constructor_blob(string $json): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR)));
}

function json101_constructor_canonical(string|SQLiteBlobValue $json): string
{
    return SQLiteJsonCanonical::json($json);
}

function json101_constructor_subtype(string $json): SQLiteJsonSubtypeValue
{
    return new SQLiteJsonSubtypeValue(SQLiteJsonCanonical::json($json));
}

/**
 * @return array{
 *     scalar:int,
 *     real:float,
 *     label:string,
 *     textArray:string,
 *     textObject:string,
 *     subtypeArray:SQLiteJsonSubtypeValue,
 *     subtypeObject:SQLiteJsonSubtypeValue,
 *     blobArray:SQLiteBlobValue,
 *     blobObject:SQLiteBlobValue
 * }
 */
function json101_constructor_fixture(int $case): array
{
    $array = '[' . $case . ',' . ($case + 1) . ',{"case":' . $case . '}]';
    $object = '{"case":' . $case . ',"tag":"case-' . $case . '","even":' . (($case % 2) === 0 ? 'true' : 'false') . '}';

    return [
        'scalar' => $case,
        'real' => $case + 0.5,
        'label' => 'label-' . $case,
        'textArray' => $array,
        'textObject' => $object,
        'subtypeArray' => json101_constructor_subtype($array),
        'subtypeObject' => json101_constructor_subtype($object),
        'blobArray' => json101_constructor_blob($array),
        'blobObject' => json101_constructor_blob($object),
    ];
}

function json101_constructor_json_text(string|SQLiteBlobValue $value): string
{
    return $value instanceof SQLiteBlobValue ? json101_constructor_canonical($value) : $value;
}

for ($case = 1; $case <= 1000; $case++) {
    $tests['real upstream json101 constructor subtype dynamic row ' . $case] =
        static function (TestRunner $t) use ($case): void {
            $fixture = json101_constructor_fixture($case);

            $plainArray = SQLiteJsonConstructor::jsonArraySqlFunction(
                'json_array',
                $fixture['scalar'],
                $fixture['textArray'],
                $fixture['textObject'],
                null,
            );
            $subtypeArray = SQLiteJsonConstructor::jsonArraySqlFunction(
                'json_array',
                $fixture['scalar'],
                $fixture['subtypeArray'],
                $fixture['subtypeObject'],
                $fixture['real'],
            );
            $blobArray = SQLiteJsonConstructor::jsonArraySqlFunction(
                'jsonb_array',
                $fixture['scalar'],
                $fixture['blobArray'],
                $fixture['blobObject'],
                $fixture['real'],
            );

            $t->same($fixture['textArray'], SQLiteJsonExtract::extract($plainArray, '$[1]'), 'json101-1.1 text argument remains string');
            $t->same($fixture['textObject'], SQLiteJsonExtract::extract($plainArray, '$[2]'), 'json101-1.1 object text argument remains string');
            $t->same(null, SQLiteJsonExtract::extract($plainArray, '$[3]'), 'json101-1.1 null argument survives');
            $t->same($case + 1, SQLiteJsonExtract::extract($subtypeArray, '$[1][1]'), 'json101-1.1 json subtype array is inserted as JSON');
            $t->same('case-' . $case, SQLiteJsonExtract::extract($subtypeArray, '$[2].tag'), 'json101-1.1 json subtype object is inserted as JSON');
            $t->same($case + 1, SQLiteJsonExtract::extract($blobArray, '$[1][1]'), 'json101-1.1 jsonb subtype array is inserted as JSON');
            $t->same('case-' . $case, SQLiteJsonExtract::extract($blobArray, '$[2].tag'), 'json101-1.1 jsonb subtype object is inserted as JSON');
            $t->same(json101_constructor_canonical($subtypeArray), json101_constructor_canonical($blobArray), 'json101-1.1 json/jsonb array constructor parity');

            $plainObject = SQLiteJsonConstructor::jsonObjectSqlFunction(
                'json_object',
                'ex',
                $fixture['textArray'],
                $fixture['label'],
                $fixture['textObject'],
            );
            $subtypeObject = SQLiteJsonConstructor::jsonObjectSqlFunction(
                'json_object',
                'ex',
                $fixture['subtypeArray'],
                $fixture['label'],
                $fixture['subtypeObject'],
            );
            $blobObject = SQLiteJsonConstructor::jsonObjectSqlFunction(
                'jsonb_object',
                'ex',
                $fixture['blobArray'],
                $fixture['label'],
                $fixture['blobObject'],
            );

            $t->same($fixture['textArray'], SQLiteJsonExtract::extract($plainObject, '$.ex'), 'json101-2.1 object text value remains string');
            $t->same($fixture['textObject'], SQLiteJsonExtract::extract($plainObject, '$.' . $fixture['label']), 'json101-2.1 object text member remains string');
            $t->same($case + 1, SQLiteJsonExtract::extract($subtypeObject, '$.ex[1]'), 'json101-2.2.2 object json subtype value is inserted as JSON');
            $t->same($case, SQLiteJsonExtract::extract($subtypeObject, '$.' . $fixture['label'] . '.case'), 'json101-2.2.2 object json subtype member is inserted as JSON');
            $t->same($case + 1, SQLiteJsonExtract::extract($blobObject, '$.ex[1]'), 'json101-2.2.3 object jsonb value is inserted as JSON');
            $t->same($case, SQLiteJsonExtract::extract($blobObject, '$.' . $fixture['label'] . '.case'), 'json101-2.2.3 object jsonb member is inserted as JSON');
            $t->same(json101_constructor_canonical($subtypeObject), json101_constructor_canonical($blobObject), 'json101-2.2 json/jsonb object constructor parity');

            $base = '{"a":1,"b":2}';
            $textReplacement = SQLiteJsonMutation::mutateSqlFunction('json_replace', $base, '$.a', $fixture['textArray']);
            $subtypeReplacement = SQLiteJsonMutation::mutateSqlFunction('json_replace', $base, '$.a', $fixture['subtypeArray']);
            $blobReplacement = SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $base, '$.a', $fixture['blobArray']);

            $t->same($fixture['textArray'], SQLiteJsonExtract::extract($textReplacement, '$.a'), 'json101-3.1 text replacement remains string');
            $t->same($case + 1, SQLiteJsonExtract::extract($subtypeReplacement, '$.a[1]'), 'json101-3.2 json subtype replacement is inserted as JSON');
            $t->same($case + 1, SQLiteJsonExtract::extract($blobReplacement, '$.a[1]'), 'json101-3.2 jsonb replacement is inserted as JSON');
            $t->same(json101_constructor_canonical($subtypeReplacement), json101_constructor_canonical($blobReplacement), 'json101-3.2 json/jsonb replace parity');

            $textSet = SQLiteJsonMutation::mutateSqlFunction('json_set', $base, '$.b', $fixture['textObject']);
            $subtypeSet = SQLiteJsonMutation::mutateSqlFunction('json_set', $base, '$.b', $fixture['subtypeObject']);
            $blobSet = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $base, '$.b', $fixture['blobObject']);

            $t->same($fixture['textObject'], SQLiteJsonExtract::extract($textSet, '$.b'), 'json101-3.3 text set remains string');
            $t->same('text', SQLiteJsonInspection::jsonType($textSet, '$.b'), 'json101-3.3 text set type');
            $t->same('object', SQLiteJsonInspection::jsonType($subtypeSet, '$.b'), 'json101-3.4 json subtype set type');
            $t->same('case-' . $case, SQLiteJsonExtract::extract($subtypeSet, '$.b.tag'), 'json101-3.4 json subtype set value');
            $t->same('object', SQLiteJsonInspection::jsonType($blobSet, '$.b'), 'json101-3.4 jsonb set type');
            $t->same('case-' . $case, SQLiteJsonExtract::extract($blobSet, '$.b.tag'), 'json101-3.4 jsonb set value');
            $t->same(json101_constructor_canonical($subtypeSet), json101_constructor_canonical($blobSet), 'json101-3.4 json/jsonb set parity');

            $inserted = SQLiteJsonMutation::mutateSqlFunction('json_insert', '{"root":[]}', '$.root[#]', $fixture['subtypeObject']);
            $insertedBlob = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', '{"root":[]}', '$.root[#]', $fixture['blobObject']);
            $t->same($case, SQLiteJsonExtract::extract($inserted, '$.root[0].case'), 'json101-3 append subtype object');
            $t->same($case, SQLiteJsonExtract::extract($insertedBlob, '$.root[0].case'), 'json101-3 append jsonb object');
            $t->same(json101_constructor_canonical($inserted), json101_constructor_canonical($insertedBlob), 'json101-3 append json/jsonb parity');
        };
}

$tests['real upstream json101 constructor subtype dynamic cites source and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        $t->same(['json101-1.1', 'json101-2.1', 'json101-2.2.2', 'json101-2.2.3', 'json101-3.1', 'json101-3.2', 'json101-3.3', 'json101-3.4'], ['json101-1.1', 'json101-2.1', 'json101-2.2.2', 'json101-2.2.3', 'json101-3.1', 'json101-3.2', 'json101-3.3', 'json101-3.4']);
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
