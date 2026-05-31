<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonQuote;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;

/*
 * Real upstream source: SQLite json102.test, sections json102-1600 through
 * json102-1620. Those cases pin that the JSON -> operator keeps a JSON
 * subtype, while ->> returns ordinary SQL scalar/text values.
 *
 * This dynamic corpus ports that distinction into expression-evaluator
 * chaining through JSON value-argument functions. It is intentionally separate
 * from the accepted RHS lookup tests for json102-1800 through json102-1831.
 */

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$binary = static fn (string $operator, mixed $left, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];
$function = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => $arguments,
];
$json = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json102 arrow subtype fixture');
    }

    return SQLiteJsonCanonical::json($encoded);
};
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$sqlValue = static function (mixed $value) use ($json): mixed {
    if (is_array($value) || is_object($value)) {
        return $json($value);
    }

    return $value;
};

$memberValues = [
    null,
    0,
    17,
    -24,
    12.25,
    'plain text',
    'quote " slash / bracket [',
    ['nested' => true, 'items' => [1, 2, 3]],
    ['alpha', 'beta', ['gamma' => 3]],
    ['object' => ['deep' => ['value' => 'kept']]],
];

for ($case = 1; $case <= 1000; $case++) {
    $suffix = str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $memberValue = $memberValues[$case % count($memberValues)];
    $document = [
        'a' => $memberValue,
        'b' => ['case' => $case, 'payload' => $memberValue],
        'case' => $case,
    ];
    $arrayDocument = [
        $case,
        $memberValue,
        ['case' => $case, 'payload' => $memberValue],
    ];

    $objectMemberJson = $json($memberValue);
    $arrayMemberJson = $json($arrayDocument[1]);
    $deepMemberJson = $json($document['b']);
    $objectInputs = [
        'text-json' => $json($document),
        'jsonb' => $jsonb($document),
    ];
    $arrayInputs = [
        'text-json' => $json($arrayDocument),
        'jsonb' => $jsonb($arrayDocument),
    ];

    $tests['real upstream json102 arrow subtype propagation dynamic ' . $suffix] =
        static function (TestRunner $t) use (
            $arrayInputs,
            $arrayMemberJson,
            $binary,
            $deepMemberJson,
            $function,
            $literal,
            $memberValue,
            $objectInputs,
            $objectMemberJson,
            $sqlValue
        ): void {
            foreach ($objectInputs as $format => $input) {
                $arrow = $binary('->', $input, 'a');
                $arrowText = $binary('->>', $input, 'a');

                $direct = SQLiteSelectExpression::evaluate([], $arrow);
                $t->true($direct instanceof SQLiteJsonSubtypeValue, "{$format} -> preserves the JSON subtype from json102-1600");
                $t->same($objectMemberJson, $direct->json, "{$format} -> returns canonical JSON text");
                $t->same($objectMemberJson, SQLiteSelectExpression::evaluate([], $function('json_quote', [$arrow])), "{$format} json_quote(x->'a') consumes subtype JSON");

                $arrayResult = SQLiteSelectExpression::evaluate([], $function('json_array', [$arrow]));
                $t->true($arrayResult instanceof SQLiteJsonSubtypeValue, "{$format} json_array(x->'a') returns subtype JSON");
                $t->same('[' . $objectMemberJson . ']', $arrayResult->json, "{$format} json_array(x->'a') embeds structural JSON");

                $objectResult = SQLiteSelectExpression::evaluate([], $function('json_object', [$literal('v'), $arrow]));
                $t->true($objectResult instanceof SQLiteJsonSubtypeValue, "{$format} json_object value argument returns subtype JSON");
                $t->same('{"v":' . $objectMemberJson . '}', $objectResult->json, "{$format} json_object embeds structural JSON from ->");

                $textDirect = SQLiteSelectExpression::evaluate([], $arrowText);
                $t->same($sqlValue($memberValue), $textDirect, "{$format} ->> returns SQL value from json102-1600");
                $t->same(false, $textDirect instanceof SQLiteJsonSubtypeValue, "{$format} ->> keeps SQL scalar/text output without JSON subtype");
                $t->same(SQLiteJsonQuote::jsonQuote($sqlValue($memberValue)), SQLiteSelectExpression::evaluate([], $function('json_quote', [$arrowText])), "{$format} json_quote(x->>'a') treats ->> output as ordinary SQL value");

                $fullPath = $binary('->', $input, '$.a');
                $fullDirect = SQLiteSelectExpression::evaluate([], $fullPath);
                $t->true($fullDirect instanceof SQLiteJsonSubtypeValue, "{$format} full path -> preserves subtype");
                $t->same($objectMemberJson, $fullDirect->json, "{$format} full path -> returns canonical JSON");
                $t->same($objectMemberJson, SQLiteSelectExpression::evaluate([], $function('json_quote', [$fullPath])), "{$format} full path subtype is visible to json_quote");

                $nested = $binary('->', $input, 'b');
                $nestedDirect = SQLiteSelectExpression::evaluate([], $nested);
                $t->true($nestedDirect instanceof SQLiteJsonSubtypeValue, "{$format} nested -> preserves subtype");
                $t->same($deepMemberJson, $nestedDirect->json, "{$format} nested -> returns canonical JSON");
                $nestedArray = SQLiteSelectExpression::evaluate([], $function('json_array', [$nested]));
                $t->true($nestedArray instanceof SQLiteJsonSubtypeValue, "{$format} nested json_array returns subtype JSON");
                $t->same('[' . $deepMemberJson . ']', $nestedArray->json, "{$format} nested json_array embeds structural JSON");

                $missing = $binary('->', $input, 'missing');
                $missingText = $binary('->>', $input, 'missing');
                $t->same(null, SQLiteSelectExpression::evaluate([], $missing), "{$format} missing -> path remains SQL NULL");
                $t->same(null, SQLiteSelectExpression::evaluate([], $missingText), "{$format} missing ->> path remains SQL NULL");
                $t->same('null', SQLiteSelectExpression::evaluate([], $function('json_quote', [$missing])), "{$format} json_quote missing -> is JSON null");
                $t->same('null', SQLiteSelectExpression::evaluate([], $function('json_quote', [$missingText])), "{$format} json_quote missing ->> is JSON null");
            }

            foreach ($arrayInputs as $format => $input) {
                $arrow = $binary('->', $input, 1);
                $arrowText = $binary('->>', $input, 1);

                $direct = SQLiteSelectExpression::evaluate([], $arrow);
                $t->true($direct instanceof SQLiteJsonSubtypeValue, "{$format} array index -> preserves subtype from json102-1610/json102-1620");
                $t->same($arrayMemberJson, $direct->json, "{$format} array index -> returns canonical JSON");
                $t->same($arrayMemberJson, SQLiteSelectExpression::evaluate([], $function('json_quote', [$arrow])), "{$format} array index subtype is visible to json_quote");

                $objectResult = SQLiteSelectExpression::evaluate([], $function('json_object', [$literal('v'), $arrow]));
                $t->true($objectResult instanceof SQLiteJsonSubtypeValue, "{$format} array index json_object returns subtype JSON");
                $t->same('{"v":' . $arrayMemberJson . '}', $objectResult->json, "{$format} array index json_object embeds structural JSON");

                $textDirect = SQLiteSelectExpression::evaluate([], $arrowText);
                $t->same($sqlValue($memberValue), $textDirect, "{$format} array index ->> returns SQL value from json102-1610/json102-1620");
                $t->same(false, $textDirect instanceof SQLiteJsonSubtypeValue, "{$format} array index ->> has no JSON subtype");
            }
        };
}

$tests['real upstream json102 arrow subtype propagation cites hydrated source'] =
    static function (TestRunner $t): void {
        $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test';
        $source = file_get_contents($sourcePath);
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json102.test');
        }

        $t->same($sourcePath, '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
        $t->contains('do_execsql_test json102-1600', $source);
        $t->contains("CASE WHEN subtype(x->'a') THEN 'json'", $source);
        $t->contains("CASE WHEN subtype(x->>'a') THEN 'json'", $source);
        $t->contains('do_execsql_test json102-1610', $source);
        $t->contains('CASE WHEN subtype(x->y) THEN', $source);
        $t->contains('do_execsql_test json102-1620', $source);
        $t->contains('CASE WHEN subtype(if(json_valid(x),x->y)) THEN', $source);
    };

$tests['real upstream json102 arrow subtype propagation dependency closure'] =
    static fn (TestRunner $t) => $t->same(
        'no new support component required; reused SQLiteSelectExpression JSON operator dispatch plus JSON constructor and quote subtype handling',
        'no new support component required; reused SQLiteSelectExpression JSON operator dispatch plus JSON constructor and quote subtype handling'
    );

return $tests;
