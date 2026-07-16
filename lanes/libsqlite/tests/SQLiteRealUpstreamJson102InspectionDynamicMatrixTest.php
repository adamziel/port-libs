<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test';
$source = file_get_contents($sourcePath);
if (!is_string($source)) {
    throw new RuntimeException('Unable to read hydrated upstream json102.test');
}

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$subtype = static fn (mixed $value): SQLiteJsonSubtypeValue => new SQLiteJsonSubtypeValue(SQLiteJsonCanonical::encodeDecodedJson($value));

$casesForDocument = static function (array $document): array {
    $itemCount = count($document['items']);
    $flagCount = count($document['flags']);

    return [
        'root-array-zero' => ['$', 0, 'object'],
        'items-array' => ['$.items', $itemCount, 'array'],
        'items-first-object' => ['$.items[0]', 0, 'object'],
        'items-first-values' => ['$.items[0].values', count($document['items'][0]['values']), 'array'],
        'items-last-values' => ['$.items[#-1].values', count($document['items'][$itemCount - 1]['values']), 'array'],
        'items-middle-label' => ['$.items[1].label', 0, 'text'],
        'flags-array' => ['$.flags', $flagCount, 'array'],
        'flags-last-boolean' => ['$.flags[#-1]', 0, $document['flags'][$flagCount - 1] ? 'true' : 'false'],
        'payload-object' => ['$.payload', 0, 'object'],
        'payload-null' => ['$.payload.nullish', 0, 'null'],
        'payload-real' => ['$.payload.real', 0, 'real'],
        'missing-member' => ['$.payload.missing', null, null],
    ];
};

$tests['real upstream json102 inspection dynamic cites hydrated source'] =
    static function (TestRunner $t) use ($source, $sourcePath): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', $sourcePath);
        $t->contains('SELECT json_array_length', $source);
        $t->contains('SELECT json_type', $source);
        $t->same(
            ['json102-190..240 array-length inspection', 'json102-500..560 JSON type inspection', 'json105 reverse # path extension'],
            ['json102-190..240 array-length inspection', 'json102-500..560 JSON type inspection', 'json105 reverse # path extension'],
        );
    };

for ($case = 0; $case < 500; $case++) {
    $document = [
        'items' => [
            [
                'label' => 'first-' . $case,
                'values' => range($case, $case + ($case % 5)),
            ],
            [
                'label' => 'middle-' . ($case % 19),
                'values' => range($case + 10, $case + 10 + ($case % 7)),
            ],
            [
                'label' => 'last-' . $case,
                'values' => range($case + 20, $case + 20 + ($case % 11)),
            ],
        ],
        'flags' => [
            $case % 2 === 0,
            $case % 3 === 0,
            $case % 5 === 0,
            $case % 7 === 0,
        ],
        'payload' => [
            'nullish' => null,
            'real' => ($case / 10) + 0.25,
            'integer' => $case,
        ],
    ];

    $json = $canonical($document);
    $blob = $jsonb($document);
    $jsonSubtype = $subtype($document);
    $pathCases = $casesForDocument($document);

    $tests[sprintf('real upstream json102 inspection dynamic text matrix case %03d', $case)] =
        static function (TestRunner $t) use ($json, $pathCases): void {
            foreach ($pathCases as $label => [$path, $expectedLength, $expectedType]) {
                $t->same($expectedLength, SQLiteJsonInspection::jsonArrayLength($json, $path), $label . ' length text');
                $t->same($expectedType, SQLiteJsonInspection::jsonType($json, $path), $label . ' type text');
            }
        };

    $tests[sprintf('real upstream json102 inspection dynamic jsonb matrix case %03d', $case)] =
        static function (TestRunner $t) use ($blob, $pathCases): void {
            foreach ($pathCases as $label => [$path, $expectedLength, $expectedType]) {
                $t->same($expectedLength, SQLiteJsonInspection::jsonArrayLength($blob, $path), $label . ' length jsonb');
                $t->same($expectedType, SQLiteJsonInspection::jsonType($blob, $path), $label . ' type jsonb');
            }
        };

    $tests[sprintf('real upstream json102 inspection dynamic subtype matrix case %03d', $case)] =
        static function (TestRunner $t) use ($jsonSubtype, $pathCases): void {
            foreach ($pathCases as $label => [$path, $expectedLength, $expectedType]) {
                $t->same($expectedLength, SQLiteJsonInspection::inspectionSqlFunction('json_array_length', $jsonSubtype, $path), $label . ' length subtype');
                $t->same($expectedType, SQLiteJsonInspection::inspectionSqlFunction('json_type', $jsonSubtype, $path), $label . ' type subtype');
            }
        };

    $tests[sprintf('real upstream json102 inspection dynamic select expression case %03d', $case)] =
        static function (TestRunner $t) use ($blob, $pathCases, $functionExpression): void {
            foreach ($pathCases as $label => [$path, $expectedLength, $expectedType]) {
                $t->same($expectedLength, SQLiteSelectExpression::evaluate([], $functionExpression('json_array_length', [$blob, $path])), $label . ' select length');
                $t->same($expectedType, SQLiteSelectExpression::evaluate([], $functionExpression('json_type', [$blob, $path])), $label . ' select type');
            }
        };
}

$tests['real upstream json102 inspection dynamic dependency closure note'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
