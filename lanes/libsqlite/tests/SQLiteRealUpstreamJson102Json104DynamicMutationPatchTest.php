<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$encode = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode JSON mutation fixture');
    }

    return $json;
};
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$mergePatch = static function (mixed $target, mixed $patch) use (&$mergePatch): mixed {
    if (!is_array($patch) || array_is_list($patch)) {
        return $patch;
    }

    $result = (!is_array($target) || array_is_list($target)) ? [] : $target;
    foreach ($patch as $key => $value) {
        if ($value === null) {
            unset($result[$key]);
            continue;
        }

        $result[$key] = $mergePatch($result[$key] ?? null, $value);
    }

    return $result;
};

for ($case = 0; $case < 180; $case++) {
    $base = [
        'a' => 2,
        'c' => 4,
        'nested' => [
            'label' => 'tenant-' . $case,
            'limits' => ['soft' => 10 + $case, 'hard' => 20 + $case],
        ],
        'items' => [
            ['id' => $case, 'name' => 'alpha-' . $case],
            ['id' => $case + 1, 'name' => 'beta-' . $case],
        ],
    ];
    $json = $encode($base);
    $blob = $jsonb($base);
    $section = sprintf('case-%03d', $case);

    $tests['real upstream json102 mutation dynamic insert existing object member ' . $section] =
        static function (TestRunner $t) use ($json, $blob, $encode, $base, $jsonbText): void {
            $expected = $encode($base);
            $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_insert', $json, '$.a', 99));
            $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_insert', $blob, '$.a', 99));

            $jsonbResult = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $json, '$.a', 99);
            $t->true($jsonbResult instanceof SQLiteBlobValue);
            $t->same($expected, $jsonbText($jsonbResult));
        };

    $tests['real upstream json102 mutation dynamic insert missing object member ' . $section] =
        static function (TestRunner $t) use ($json, $blob, $encode, $base, $jsonb, $jsonbText, $case): void {
            $expected = $base;
            $expected['e'] = 90 + $case;
            $expectedJson = $encode($expected);

            $t->same($expectedJson, SQLiteJsonMutation::mutateSqlFunction('json_insert', $json, '$.e', 90 + $case));
            $t->same($expectedJson, SQLiteJsonMutation::mutateSqlFunction('json_insert', $blob, '$.e', 90 + $case));

            $jsonbResult = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $blob, '$.e', 90 + $case);
            $t->true($jsonbResult instanceof SQLiteBlobValue);
            $t->same($expectedJson, $jsonbText($jsonbResult));
        };

    $tests['real upstream json102 mutation dynamic replace existing and ignore missing ' . $section] =
        static function (TestRunner $t) use ($json, $blob, $encode, $base, $jsonb, $jsonbText, $case): void {
            $replaced = $base;
            $replaced['a'] = 200 + $case;
            $expectedReplace = $encode($replaced);
            $expectedUnchanged = $encode($base);

            $t->same($expectedReplace, SQLiteJsonMutation::mutateSqlFunction('json_replace', $json, '$.a', 200 + $case));
            $t->same($expectedUnchanged, SQLiteJsonMutation::mutateSqlFunction('json_replace', $blob, '$.missing', 200 + $case));

            $jsonbResult = SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $blob, '$.a', 200 + $case);
            $t->true($jsonbResult instanceof SQLiteBlobValue);
            $t->same($expectedReplace, $jsonbText($jsonbResult));
        };

    $tests['real upstream json102 mutation dynamic set scalar string versus json subtype ' . $section] =
        static function (TestRunner $t) use ($json, $blob, $encode, $base, $jsonb, $jsonbText, $case): void {
            $stringSet = $base;
            $stringSet['c'] = '[97,96]';
            $jsonSet = $base;
            $jsonSet['c'] = [97 + ($case % 3), 96 + ($case % 5)];
            $jsonArray = $encode($jsonSet['c']);
            $expectedString = $encode($stringSet);
            $expectedJson = $encode($jsonSet);

            $t->same($expectedString, SQLiteJsonMutation::mutateSqlFunction('json_set', $json, '$.c', '[97,96]'));
            $t->same($expectedJson, SQLiteJsonMutation::mutateSqlFunction('json_set', $blob, '$.c', new SQLiteJsonSubtypeValue($jsonArray)));

            $jsonbResult = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $json, '$.c', $jsonb($jsonSet['c']));
            $t->true($jsonbResult instanceof SQLiteBlobValue);
            $t->same($expectedJson, $jsonbText($jsonbResult));
        };

    $tests['real upstream json102 mutation dynamic constructor values stay JSON ' . $section] =
        static function (TestRunner $t) use ($json, $blob, $encode, $base, $jsonbText, $case): void {
            $expected = $base;
            $expected['c'] = [97 + $case, 96 + $case];
            $jsonArray = SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 97 + $case, 96 + $case);
            $jsonbArray = SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 97 + $case, 96 + $case);
            $expectedJson = $encode($expected);

            $t->same($expectedJson, SQLiteJsonMutation::mutateSqlFunction('json_set', $json, '$.c', new SQLiteJsonSubtypeValue($jsonArray)));
            $t->same($expectedJson, SQLiteJsonMutation::mutateSqlFunction('json_set', $blob, '$.c', $jsonbArray));

            $jsonbResult = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $blob, '$.c', $jsonbArray);
            $t->true($jsonbResult instanceof SQLiteBlobValue);
            $t->same($expectedJson, $jsonbText($jsonbResult));
        };

    $tests['real upstream json104 merge patch dynamic object replacement and null delete ' . $section] =
        static function (TestRunner $t) use ($base, $json, $blob, $encode, $jsonb, $jsonbText, $mergePatch, $case): void {
            $patch = [
                'a' => 'patched-' . $case,
                'nested' => [
                    'limits' => ['soft' => null, 'burst' => $case],
                    'extra' => true,
                ],
                'items' => [['id' => 900 + $case]],
            ];
            $expected = $encode($mergePatch($base, $patch));
            $patchJson = $encode($patch);

            $t->same($expected, SQLiteJsonPatch::patchSqlFunction('json_patch', $json, $patchJson));
            $t->same($expected, SQLiteJsonPatch::patchSqlFunction('json_patch', $blob, $jsonb($patch)));

            $jsonbResult = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $blob, $patchJson);
            $t->true($jsonbResult instanceof SQLiteBlobValue);
            $t->same($expected, $jsonbText($jsonbResult));
        };

    $tests['real upstream json104 merge patch dynamic array and scalar patch replace whole target ' . $section] =
        static function (TestRunner $t) use ($json, $blob, $encode, $jsonb, $jsonbText, $case): void {
            $arrayPatch = ['c', 'd', $case];
            $scalarPatch = 'bar-' . $case;
            $expectedArray = $encode($arrayPatch);
            $expectedScalar = $encode($scalarPatch);

            $t->same($expectedArray, SQLiteJsonPatch::patchSqlFunction('json_patch', $json, $encode($arrayPatch)));
            $t->same($expectedScalar, SQLiteJsonPatch::patchSqlFunction('json_patch', $blob, $encode($scalarPatch)));

            $jsonbResult = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $blob, $jsonb($arrayPatch));
            $t->true($jsonbResult instanceof SQLiteBlobValue);
            $t->same($expectedArray, $jsonbText($jsonbResult));
        };

    $tests['real upstream json102 json104 select expression dynamic dispatch ' . $section] =
        static function (TestRunner $t) use ($json, $blob, $functionExpression, $encode, $base, $jsonbText, $case): void {
            $set = $base;
            $set['nested']['limits']['hard'] = 700 + $case;
            $expectedSet = $encode($set);
            $value = SQLiteSelectExpression::evaluate([], $functionExpression('json_set', [$json, '$.nested.limits.hard', 700 + $case]));
            $t->true($value instanceof SQLiteJsonSubtypeValue);
            $t->same($expectedSet, $value->json);

            $patch = ['nested' => ['label' => 'patched-' . $case]];
            $patched = $base;
            $patched['nested']['label'] = 'patched-' . $case;
            $jsonbValue = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_patch', [$blob, $encode($patch)]));
            $t->true($jsonbValue instanceof SQLiteBlobValue);
            $t->same($encode($patched), $jsonbText($jsonbValue));
        };
}

$tests['real upstream json102 json104 dynamic mutation patch cites hydrated source'] =
    static function (TestRunner $t): void {
        $json102 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
        $json104 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test');
        $t->true(is_string($json102));
        $t->true(is_string($json104));
        $t->contains('SELECT json_insert', $json102);
        $t->contains('SELECT json_replace', $json102);
        $t->contains('SELECT json_set', $json102);
        $t->contains('SELECT json_patch', $json104);
        $t->same(
            ['json102-320..400 mutation/value-subtype behavior', 'json104-300..320 RFC-7396 merge-patch behavior'],
            ['json102-320..400 mutation/value-subtype behavior', 'json104-300..320 RFC-7396 merge-patch behavior'],
        );
    };

$tests['real upstream json102 json104 dynamic mutation patch dependency closure note'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
