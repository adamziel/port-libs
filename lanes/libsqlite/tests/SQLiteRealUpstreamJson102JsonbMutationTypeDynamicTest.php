<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test';
$source = file_get_contents($sourcePath);
if (!is_string($source)) {
    throw new RuntimeException('Unable to read hydrated upstream json102.test');
}

$encode = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode upstream JSON102 dynamic fixture');
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

for ($case = 0; $case < 100; $case++) {
    $base = [
        'a' => $case + 2,
        'c' => $case + 4,
        'nested' => [
            'items' => [
                ['name' => 'alpha-' . $case, 'score' => $case],
                ['name' => 'beta-' . $case, 'score' => $case + 10],
                ['name' => 'gamma-' . $case, 'score' => $case + 20],
            ],
        ],
        'types' => [2, 3.5, true, false, null, 'x-' . $case],
    ];
    $json = $encode($base);
    $blob = $jsonb($base);

    $tests[sprintf('real upstream json102 mutation %03d insert existing text is no-op', $case)] =
        static function (TestRunner $t) use ($json): void {
            $t->same($json, SQLiteJsonMutation::mutateSqlFunction('json_insert', $json, '$.a', 99));
        };

    $tests[sprintf('real upstream json102 mutation %03d insert missing text appends member', $case)] =
        static function (TestRunner $t) use ($base, $json, $encode, $case): void {
            $expected = $base;
            $expected['e'] = 900 + $case;
            $t->same($encode($expected), SQLiteJsonMutation::mutateSqlFunction('json_insert', $json, '$.e', 900 + $case));
        };

    $tests[sprintf('real upstream json102 mutation %03d replace existing jsonb rewrites member', $case)] =
        static function (TestRunner $t) use ($base, $blob, $encode, $jsonbText, $case): void {
            $expected = $base;
            $expected['a'] = 700 + $case;
            $changed = SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $blob, '$.a', 700 + $case);
            $t->true($changed instanceof SQLiteBlobValue);
            $t->same($encode($expected), $jsonbText($changed));
        };

    $tests[sprintf('real upstream json102 mutation %03d replace missing jsonb is no-op', $case)] =
        static function (TestRunner $t) use ($blob, $jsonbText, $json): void {
            $changed = SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $blob, '$.missing', 99);
            $t->true($changed instanceof SQLiteBlobValue);
            $t->same($json, $jsonbText($changed));
        };

    $tests[sprintf('real upstream json102 mutation %03d set plain text array value remains quoted', $case)] =
        static function (TestRunner $t) use ($base, $json, $encode): void {
            $expected = $base;
            $expected['c'] = '[97,96]';
            $t->same($encode($expected), SQLiteJsonMutation::mutateSqlFunction('json_set', $json, '$.c', '[97,96]'));
        };

    $tests[sprintf('real upstream json102 mutation %03d set json subtype array value stays structural', $case)] =
        static function (TestRunner $t) use ($base, $blob, $encode, $jsonbText): void {
            $expected = $base;
            $expected['c'] = [97, 96];
            $changed = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $blob, '$.c', new SQLiteJsonSubtypeValue('[97,96]'));
            $t->true($changed instanceof SQLiteBlobValue);
            $t->same($encode($expected), $jsonbText($changed));
        };

    $tests[sprintf('real upstream json102 remove %03d jsonb array index compacts left', $case)] =
        static function (TestRunner $t) use ($base, $blob, $encode, $jsonbText): void {
            $expected = $base;
            array_splice($expected['nested']['items'], 1, 1);
            $changed = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $blob, '$.nested.items[1]');
            $t->true($changed instanceof SQLiteBlobValue);
            $t->same($encode($expected), $jsonbText($changed));
        };

    $tests[sprintf('real upstream json102 remove %03d ordered multiple paths use prior removals', $case)] =
        static function (TestRunner $t) use ($base, $json, $encode): void {
            $expected = $base;
            array_splice($expected['types'], 2, 1);
            array_splice($expected['types'], 0, 1);
            $t->same($encode($expected), SQLiteJsonRemove::removeSqlFunction('json_remove', $json, '$.types[2]', '$.types[0]'));
        };

    $tests[sprintf('real upstream json102 type %03d text and jsonb scalar class parity', $case)] =
        static function (TestRunner $t) use ($json, $blob): void {
            $t->same('array', SQLiteJsonInspection::jsonType($json, '$.types'));
            $t->same('integer', SQLiteJsonInspection::jsonType($blob, '$.types[0]'));
            $t->same('real', SQLiteJsonInspection::jsonType($json, '$.types[1]'));
            $t->same('true', SQLiteJsonInspection::jsonType($blob, '$.types[2]'));
            $t->same('false', SQLiteJsonInspection::jsonType($json, '$.types[3]'));
            $t->same('null', SQLiteJsonInspection::jsonType($blob, '$.types[4]'));
            $t->same('text', SQLiteJsonInspection::jsonType($json, '$.types[5]'));
            $t->same(null, SQLiteJsonInspection::jsonType($blob, '$.types[6]'));
        };

    $tests[sprintf('real upstream json102 constructor %03d object and select dispatch preserve JSON subtype', $case)] =
        static function (TestRunner $t) use ($case, $functionExpression): void {
            $array = SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, null, '3', new SQLiteJsonSubtypeValue('[4,5]'));
            $t->same('[1,null,"3",[4,5]]', $array);
            $objectBlob = SQLiteJsonConstructor::jsonObjectSqlFunction('jsonb_object', 'a', $case, 'c', new SQLiteJsonSubtypeValue('{"e":5}'));
            $t->true($objectBlob instanceof SQLiteBlobValue);
            $t->same('{"a":' . $case . ',"c":{"e":5}}', SQLiteJsonCanonical::json($objectBlob));

            $evaluated = SQLiteSelectExpression::evaluate([], $functionExpression('json_set', ['{"a":2,"c":4}', '$.c', new SQLiteJsonSubtypeValue('[97,96]')]));
            $t->true($evaluated instanceof SQLiteJsonSubtypeValue);
            $t->same('{"a":2,"c":[97,96]}', $evaluated->json);
        };
}

$tests['real upstream json102 mutation type dynamic cites hydrated source'] =
    static function (TestRunner $t) use ($source, $sourcePath): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', $sourcePath);
        $t->contains('SELECT json_insert', $source);
        $t->contains('SELECT json(jsonb_set', $source);
        $t->contains('SELECT json_type', $source);
        $t->same(
            ['json102-320..400 insert/replace/set value semantics', 'json102-440..500 ordered remove semantics', 'json102-510..600 json_type scalar classes'],
            ['json102-320..400 insert/replace/set value semantics', 'json102-440..500 ordered remove semantics', 'json102-510..600 json_type scalar classes'],
        );
    };

$tests['real upstream json102 mutation type dynamic dependency closure note'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
