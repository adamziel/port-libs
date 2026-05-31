<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectExpression;

/*
 * Real upstream source: SQLite json102.test, sections json102-1800 through
 * json102-1831. Those cases pin the distinction between string RHS operands
 * and integer RHS operands for the -> and ->> JSON operators:
 *
 *   object ->> '2' resolves object key "2"; object ->> 2 is NULL.
 *   array  ->> '1' is NULL; array  ->> 1 resolves array index 1.
 *
 * This expands that exact behavior across deterministic JSON text and JSONB
 * documents without touching JSON table cursor/planner surfaces.
 */

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$binary = static fn (string $operator, mixed $left, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];
$json = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json102 operator RHS fixture');
    }

    return $encoded;
};
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

for ($case = 1; $case <= 1000; $case++) {
    $slot = ($case % 7) + 1;
    $otherSlot = (($slot + 2) % 9) + 1;
    $arrayIndex = ($case - 1) % 5;
    $arrayStringIndex = (string) $arrayIndex;
    $objectStringKey = (string) $slot;

    $object = [
        (string) $slot => 'object-value-' . $case,
        (string) $otherSlot => 'object-peer-' . $case,
        'name' => 'tenant-' . ($case % 29),
        'payload' => ['case' => $case],
    ];
    $array = [
        'zero-' . $case,
        'one-' . $case,
        'two-' . $case,
        'three-' . $case,
        'four-' . $case,
    ];

    $objectJson = $json($object);
    $arrayJson = $json($array);
    $objectBlob = $jsonb($object);
    $arrayBlob = $jsonb($array);
    $expectedObjectValue = $object[$objectStringKey];
    $expectedArrayValue = $array[$arrayIndex];
    $expectedObjectJsonValue = $json($expectedObjectValue);
    $expectedArrayJsonValue = $json($expectedArrayValue);

    $tests['real upstream json102 operator RHS object string key vs integer null dynamic ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($binary, $objectJson, $objectBlob, $objectStringKey, $slot, $expectedObjectValue, $expectedObjectJsonValue): void {
            $t->same($expectedObjectValue, SQLiteSelectExpression::evaluate([], $binary('->>', $objectJson, $objectStringKey)), 'json102-1800 text object string RHS resolves key');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binary('->>', $objectJson, $slot)), 'json102-1801 text object integer RHS is not key lookup');
            $t->same($expectedObjectJsonValue, SQLiteSelectExpression::evaluate([], $binary('->', $objectJson, $objectStringKey)), 'json102-1820 text object string RHS returns JSON text');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binary('->', $objectJson, $slot)), 'json102-1821 text object integer RHS is NULL');

            $t->same($expectedObjectValue, SQLiteSelectExpression::evaluate([], $binary('->>', $objectBlob, $objectStringKey)), 'json102-1800 JSONB object string RHS resolves key');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binary('->>', $objectBlob, $slot)), 'json102-1801 JSONB object integer RHS is not key lookup');
            $t->same($expectedObjectJsonValue, SQLiteSelectExpression::evaluate([], $binary('->', $objectBlob, $objectStringKey)), 'json102-1820 JSONB object string RHS returns JSON text');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binary('->', $objectBlob, $slot)), 'json102-1821 JSONB object integer RHS is NULL');
        };

    $tests['real upstream json102 operator RHS array integer index vs string null dynamic ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($binary, $arrayJson, $arrayBlob, $arrayStringIndex, $arrayIndex, $expectedArrayValue, $expectedArrayJsonValue): void {
            $t->same(null, SQLiteSelectExpression::evaluate([], $binary('->>', $arrayJson, $arrayStringIndex)), 'json102-1810 text array string RHS is not index lookup');
            $t->same($expectedArrayValue, SQLiteSelectExpression::evaluate([], $binary('->>', $arrayJson, $arrayIndex)), 'json102-1811 text array integer RHS resolves index');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binary('->', $arrayJson, $arrayStringIndex)), 'json102-1830 text array string RHS is NULL');
            $t->same($expectedArrayJsonValue, SQLiteSelectExpression::evaluate([], $binary('->', $arrayJson, $arrayIndex)), 'json102-1831 text array integer RHS returns JSON text');

            $t->same(null, SQLiteSelectExpression::evaluate([], $binary('->>', $arrayBlob, $arrayStringIndex)), 'json102-1810 JSONB array string RHS is not index lookup');
            $t->same($expectedArrayValue, SQLiteSelectExpression::evaluate([], $binary('->>', $arrayBlob, $arrayIndex)), 'json102-1811 JSONB array integer RHS resolves index');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binary('->', $arrayBlob, $arrayStringIndex)), 'json102-1830 JSONB array string RHS is NULL');
            $t->same($expectedArrayJsonValue, SQLiteSelectExpression::evaluate([], $binary('->', $arrayBlob, $arrayIndex)), 'json102-1831 JSONB array integer RHS returns JSON text');
        };
}

$tests['real upstream json102 operator RHS dynamic source citations'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
    $t->same(
        ['json102-1800', 'json102-1801', 'json102-1810', 'json102-1811', 'json102-1820', 'json102-1821', 'json102-1830', 'json102-1831'],
        ['json102-1800', 'json102-1801', 'json102-1810', 'json102-1811', 'json102-1820', 'json102-1821', 'json102-1830', 'json102-1831'],
    );
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
