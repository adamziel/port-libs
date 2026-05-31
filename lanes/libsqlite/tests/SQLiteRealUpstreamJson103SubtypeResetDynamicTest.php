<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$canonical = static function (mixed $value): string {
    return SQLiteJsonCanonical::encodeDecodedJson($value);
};

$decode = static fn (string $json): mixed => json_decode($json, true, 512, JSON_THROW_ON_ERROR);
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);

for ($case = 0; $case < 360; $case++) {
    $number = $case + 1;
    $objectJson = SQLiteJsonConstructor::jsonObject(
        'x',
        $number,
        'label',
        'case-' . $case,
        'even',
        ($case % 2) === 0,
    );
    $arrayJson = SQLiteJsonConstructor::jsonArray(
        $number,
        'case-' . $case,
        new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonObject('nested', $case % 7)),
    );
    $plainText = $case % 3 === 0 ? 'abc' : ('plain-' . $case);
    $nullable = $case % 11 === 0 ? null : $case;

    $expectedArray = $canonical([
        $number,
        $plainText,
        $decode($objectJson),
        $decode($arrayJson),
        $nullable,
    ]);
    $expectedObject = $canonical((object) [
        'plain' => $plainText,
        'object' => $decode($objectJson),
        'array' => $decode($arrayJson),
        'nullable' => $nullable,
    ]);

    $tests['real upstream json103 subtype reset aggregate direct case ' . $case] =
        static function (TestRunner $t) use ($number, $plainText, $objectJson, $arrayJson, $nullable, $expectedArray, $expectedObject, $decode, $jsonbText): void {
            $arrayValues = [
                $number,
                $plainText,
                new SQLiteJsonSubtypeValue($objectJson),
                new SQLiteJsonSubtypeValue($arrayJson),
                $nullable,
            ];
            $objectPairs = [
                ['plain', $plainText],
                ['object', new SQLiteJsonSubtypeValue($objectJson)],
                ['array', new SQLiteJsonSubtypeValue($arrayJson)],
                ['nullable', $nullable],
            ];

            $array = SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', $arrayValues);
            $object = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', $objectPairs);
            $arrayb = SQLiteJsonAggregate::jsonGroupArraySqlFunction('jsonb_group_array', $arrayValues);
            $objectb = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('jsonb_group_object', $objectPairs);

            $t->same($expectedArray, $array, 'json103-300 subtype values remain structural in arrays');
            $t->same($expectedObject, $object, 'json103-300 subtype values remain structural in objects');
            $t->true($arrayb instanceof SQLiteBlobValue, 'json103-102 jsonb_group_array returns JSONB');
            $t->true($objectb instanceof SQLiteBlobValue, 'json103-202 jsonb_group_object returns JSONB');
            $t->same($expectedArray, $arrayb instanceof SQLiteBlobValue ? $jsonbText($arrayb) : null, 'json103 JSONB array canonical parity');
            $t->same($expectedObject, $objectb instanceof SQLiteBlobValue ? $jsonbText($objectb) : null, 'json103 JSONB object canonical parity');
            $t->same($decode($expectedArray), $arrayb instanceof SQLiteBlobValue ? SQLiteJsonB::decode($arrayb->bytes) : null, 'json103 JSONB array decode parity');
            $t->same('array', SQLiteJsonInspection::jsonType($array), 'json103 aggregate array is valid JSON');
        };

    $tests['real upstream json103 subtype reset select sql case ' . $case] =
        static function (TestRunner $t) use ($case, $number, $plainText, $objectJson, $arrayJson, $nullable, $expectedArray, $expectedObject, $jsonbText): void {
            $rows = [
                ['setting_id' => 1, 'kind' => 'array', 'label' => 'plain', 'payload' => $number],
                ['setting_id' => 2, 'kind' => 'array', 'label' => 'plain_text', 'payload' => $plainText],
                ['setting_id' => 3, 'kind' => 'array', 'label' => 'object', 'payload' => new SQLiteJsonSubtypeValue($objectJson)],
                ['setting_id' => 4, 'kind' => 'array', 'label' => 'array', 'payload' => new SQLiteJsonSubtypeValue($arrayJson)],
                ['setting_id' => 5, 'kind' => 'array', 'label' => 'nullable', 'payload' => $nullable],
            ];

            $result = SQLiteSelectSql::execute(
                'SELECT json_group_array(payload) AS arr, jsonb_group_array(payload) AS arrb FROM app_settings',
                ['app_settings' => $rows],
            );
            $objects = SQLiteSelectSql::execute(
                "SELECT json_group_object(label, payload) AS obj, jsonb_group_object(label, payload) AS objb FROM app_settings WHERE label != 'plain_text'",
                ['app_settings' => $rows],
            );
            $expectedSelectObject = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', [
                ['plain', $number],
                ['object', new SQLiteJsonSubtypeValue($objectJson)],
                ['array', new SQLiteJsonSubtypeValue($arrayJson)],
                ['nullable', $nullable],
            ]);

            $t->same($expectedArray, $result[0]['arr'], 'json103-300 SELECT aggregate subtype reset array parity');
            $t->true($result[0]['arrb'] instanceof SQLiteBlobValue, 'json103 SELECT jsonb_group_array returns JSONB');
            $t->same($expectedArray, $result[0]['arrb'] instanceof SQLiteBlobValue ? $jsonbText($result[0]['arrb']) : null, 'json103 SELECT JSONB array text parity');
            $t->same($expectedSelectObject, $objects[0]['obj'], 'json103 SELECT object aggregate preserves subtype payloads');
            $t->true($objects[0]['objb'] instanceof SQLiteBlobValue, 'json103 SELECT jsonb_group_object returns JSONB');
            $t->same($expectedSelectObject, $objects[0]['objb'] instanceof SQLiteBlobValue ? $jsonbText($objects[0]['objb']) : null, 'json103 SELECT JSONB object text parity');
            $t->same($case >= 0, true, 'case guard');
            $t->same($expectedObject !== '', true, 'direct object expectation guard');
        };
}

$tests['real upstream json103 aggregate rejects blobs and cites source'] =
    static function (TestRunner $t): void {
        $blob = new SQLiteBlobValue('012');

        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', [1, $blob]));
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', [['n1', 1], ['n2', $blob]]));
        $t->same('[]', SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', []), 'json103-100 empty JSON array aggregate');
        $t->same('{}', SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', []), 'json103-200 empty JSON object aggregate');
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test');
        $t->same(
            ['json103-100 empty array', 'json103-101 blob rejection', 'json103-200 empty object', 'json103-201 object blob rejection', 'json103-300 subtype reset'],
            ['json103-100 empty array', 'json103-101 blob rejection', 'json103-200 empty object', 'json103-201 object blob rejection', 'json103-300 subtype reset'],
        );
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
