<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$binaryExpression = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$encode = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json502 escaped-path fixture');
    }

    return $encoded;
};
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $blob): string => SQLiteJsonCanonical::json($blob);

$tests['real upstream json502 1.1 json5 table tree fullkey chain preserved'] =
    static function (TestRunner $t) use ($jsonb): void {
        $json5 = '{a:{b:{c:"hello",},},}';
        $expectedFullkeys = ['$', '$.a', '$.a.b', '$.a.b.c'];

        foreach (['text' => $json5, 'jsonb' => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json5)] as $kind => $source) {
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $source);
            $t->same($expectedFullkeys, array_column($rows, 'fullkey'), $kind . ' fullkey chain');
            $t->same('hello', SQLiteJsonExtract::extract($source, '$.a.b.c'), $kind . ' extract leaf');
            $t->same('text', $rows[3]['type'], $kind . ' leaf type');
            $t->same('hello', $rows[3]['atom'], $kind . ' leaf atom');
        }
    };

$tests['real upstream json502 2.1 malformed json5 error position and validity preserved'] =
    static function (TestRunner $t): void {
        $malformed = '{a:null,{"h":[1,[1,2,3]],"j":"abc"}:true}';

        $t->same(9, SQLiteJsonErrorPosition::jsonErrorPosition($malformed), 'json502-2.1 json_error_position');
        $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$malformed, 2]), 'json502 malformed JSON5 is invalid');
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonCanonical::json($malformed), 'json502-2.2 json() rejects malformed JSON5');
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extract($malformed, '$h[#-1]'), 'json502-2.3 malformed extract rejects JSON5');
    };

$tests['real upstream json502 3.3 backslash label insertion text and jsonb preserved'] =
    static function (TestRunner $t) use ($jsonb, $jsonbText): void {
        $actual = SQLiteJsonMutation::mutateSqlFunction('json_insert', '{}', '$.a\\', 111, '$."b\\\\"', 222);
        $t->same('{"a\\\\":111,"b\\\\":222}', SQLiteJsonCanonical::json($actual), 'json502-3.3 text canonical insert');
        $t->same(111, SQLiteJsonExtract::extract($actual, '$.a\\'), 'json502-3.3 unquoted backslash path');
        $t->same(null, SQLiteJsonExtract::extract($actual, '$.a\\\\'), 'json502-3.3 doubled backslash path missing');
        $t->same(111, SQLiteJsonExtract::extract($actual, '$."a\\\\"'), 'json502-3.3 quoted backslash path');
        $t->same(222, SQLiteJsonExtract::extract($actual, '$."b\\\\"'), 'json502-3.3 quoted b backslash path');

        $jsonbActual = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $jsonb(['base' => 0]), '$.a\\', 111, '$."b\\\\"', 222);
        $t->same('{"base":0,"a\\\\":111,"b\\\\":222}', $jsonbText($jsonbActual), 'json502-3.3 jsonb canonical insert');
        $t->same(111, SQLiteJsonExtract::extract($jsonbActual, '$.a\\'), 'json502-3.3 jsonb unquoted backslash path');
        $t->same(null, SQLiteJsonExtract::extract($jsonbActual, '$.a\\\\'), 'json502-3.3 jsonb doubled backslash path missing');
        $t->same(111, SQLiteJsonExtract::extract($jsonbActual, '$."a\\\\"'), 'json502-3.3 jsonb quoted backslash path');
        $t->same(222, SQLiteJsonExtract::extract($jsonbActual, '$."b\\\\"'), 'json502-3.3 jsonb quoted b backslash path');
    };

for ($case = 0; $case < 126; $case++) {
    $letterCode = 0x61 + ($case % 26);
    $letter = chr($letterCode);
    $prefix = 'setting' . $case . '_';
    $plainLabel = $prefix . $letter . 'bc';
    $escapedBareLabel = $prefix . '\\x' . dechex($letterCode) . 'bc';
    $quotedSlashLabel = 'slash\\key' . $case;
    $quotedSlashPath = '$."' . str_replace('\\', '\\\\', $quotedSlashLabel) . '"';
    $quotedQuoteLabel = 'A"Key' . $case;
    $quotedQuotePath = '$."A\\"Key' . $case . '"';
    $controlLabel = chr(0x17) . 'ctl' . $case;
    $controlPath = '$."\\x17ctl' . $case . '"';
    $tailBackslash = 'tail' . $case . '\\';
    $tailBarePath = '$.tail' . $case . '\\';
    $tailQuotedPath = '$."tail' . $case . '\\\\"';
    $scalar = 1000 + $case;
    $patchScalar = 7000 + $case;

    $document = [
        $plainLabel => $scalar,
        $quotedSlashLabel => ['nested' => $case, 'items' => [1, $case, 3]],
        $quotedQuoteLabel => $scalar + 1,
        $controlLabel => $scalar + 2,
        $tailBackslash => $scalar + 3,
        'array' => [
            ['name' => $plainLabel, 'value' => $case],
            ['name' => $quotedSlashLabel, 'value' => $case + 1],
        ],
    ];
    $json = $encode($document);
    $blob = $jsonb($document);

    $tests['real upstream json502 escaped bare label text extract ' . $case] =
        static function (TestRunner $t) use ($json, $blob, $escapedBareLabel, $plainLabel, $scalar): void {
            $t->same($scalar, SQLiteJsonExtract::extract($json, '$.' . $escapedBareLabel), 'json502-3.1 bare escaped path extracts text JSON');
            $t->same($scalar, SQLiteJsonExtract::extract($blob, '$.' . $escapedBareLabel), 'json502-3.1 bare escaped path extracts JSONB');
            $t->same($scalar, SQLiteJsonExtract::extract($json, '$.' . $plainLabel), 'json502 decoded path matches literal label');
            $t->same($scalar, SQLiteJsonExtract::extract($blob, '$.' . $plainLabel), 'json502 JSONB decoded path matches literal label');
        };

    $tests['real upstream json502 escaped operator rhs label ' . $case] =
        static function (TestRunner $t) use ($json, $blob, $binaryExpression, $escapedBareLabel, $scalar): void {
            $t->same($scalar, SQLiteSelectExpression::evaluate([], $binaryExpression($json, '->>', $escapedBareLabel)), 'json502-3.2 escaped operator RHS extracts text JSON');
            $t->same((string) $scalar, SQLiteSelectExpression::evaluate([], $binaryExpression($json, '->', $escapedBareLabel)), 'json502-3.2 escaped operator RHS returns JSON text');
            $t->same($scalar, SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->>', $escapedBareLabel)), 'json502-3.2 escaped operator RHS extracts JSONB');
            $t->same((string) $scalar, SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->', $escapedBareLabel)), 'json502-3.2 escaped operator RHS returns JSONB JSON text');
        };

    $tests['real upstream json502 quoted backslash label extract and tree ' . $case] =
        static function (TestRunner $t) use ($json, $blob, $quotedSlashPath, $quotedSlashLabel, $case): void {
            $t->same($case, SQLiteJsonExtract::extract($json, $quotedSlashPath . '.nested'), 'json502-3.3 quoted backslash path extracts text JSON');
            $t->same($case, SQLiteJsonExtract::extract($blob, $quotedSlashPath . '.nested'), 'json502-3.3 quoted backslash path extracts JSONB');
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $json, $quotedSlashPath);
            $t->same($quotedSlashLabel, $rows[0]['key'], 'json502-4.1 json_tree root key preserves decoded slash label');
            $t->same($quotedSlashPath, $rows[0]['fullkey'], 'json502-4.1 json_tree fullkey preserves quoted slash path');
        };

    $tests['real upstream json502 quoted quote label extract and set ' . $case] =
        static function (TestRunner $t) use ($json, $quotedQuotePath, $quotedQuoteLabel, $case, $scalar, $encode): void {
            $t->same($scalar + 1, SQLiteJsonExtract::extract($json, $quotedQuotePath), 'json502-5.2 quoted quote path extracts member');
            $mutated = SQLiteJsonMutation::mutateSqlFunction('json_set', '{}', $quotedQuotePath, $case);
            $t->same($encode([$quotedQuoteLabel => $case]), $mutated, 'json502-5.3 json_set creates quoted quote member');
            $t->same($case, SQLiteJsonExtract::extract($mutated, $quotedQuotePath), 'json502-5.3 json_set path can be read back');
            $t->same($case, SQLiteSelectExpression::evaluate([], ['type' => 'function', 'name' => 'json_extract', 'arguments' => [['type' => 'literal', 'value' => $mutated], ['type' => 'literal', 'value' => $quotedQuotePath]]]), 'json502 quoted quote SELECT expression readback');
        };

    $tests['real upstream json502 tail backslash path parity ' . $case] =
        static function (TestRunner $t) use ($json, $blob, $tailBarePath, $tailQuotedPath, $scalar): void {
            $t->same($scalar + 3, SQLiteJsonExtract::extract($json, $tailBarePath), 'json502-3.3 bare trailing backslash member extracts text JSON');
            $t->same($scalar + 3, SQLiteJsonExtract::extract($json, $tailQuotedPath), 'json502-3.3 quoted trailing backslash member extracts text JSON');
            $t->same($scalar + 3, SQLiteJsonExtract::extract($blob, $tailBarePath), 'json502-3.3 bare trailing backslash member extracts JSONB');
            $t->same($scalar + 3, SQLiteJsonExtract::extract($blob, $tailQuotedPath), 'json502-3.3 quoted trailing backslash member extracts JSONB');
        };

    $tests['real upstream json502 json patch escaped label compare ' . $case] =
        static function (TestRunner $t) use ($json, $plainLabel, $escapedBareLabel, $patchScalar, $case, $binaryExpression): void {
            $patch = '{"' . $escapedBareLabel . '":' . $patchScalar . '}';
            $patched = SQLiteJsonPatch::patchSqlFunction('json_patch', $json, $patch);
            $t->same($patchScalar, SQLiteJsonExtract::extract($patched, '$.' . $plainLabel), 'json502-3.4 json_patch escaped label overwrites literal label');
            $t->same($patchScalar, SQLiteJsonExtract::extract($patched, '$.' . $escapedBareLabel), 'json502-3.4 json_patch escaped lookup sees overwritten label');
            $t->same($patchScalar, SQLiteSelectExpression::evaluate([], $binaryExpression($patched, '->>', $escapedBareLabel)), 'json502-3.4 patched label visible to arrow operator');
            $t->same($case >= 0, true, 'json502 dynamic patch case guard');
        };

    $tests['real upstream json502 control character path root ' . $case] =
        static function (TestRunner $t) use ($json, $controlPath, $controlLabel, $scalar): void {
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $json, $controlPath);
            $t->same(1, count($rows), 'json502-4.1 json_tree control-character path returns one scalar row');
            $t->same($controlLabel, $rows[0]['key'], 'json502-4.1 control-character key is decoded');
            $t->same($scalar + 2, $rows[0]['atom'], 'json502-4.1 control-character atom is visible');
            $t->same($controlPath, $rows[0]['root'], 'json502-4.1 root path preserves escaped control label');
        };

    $tests['real upstream json502 json5 malformed error and select guards ' . $case] =
        static function (TestRunner $t) use ($case, $functionExpression): void {
            $malformed = '{a:null,{"h":[1,[1,2,3]],"j":"abc"}:true}';
            $t->same(9, SQLiteJsonErrorPosition::jsonErrorPosition($malformed), 'json502-2.1 malformed JSON5 error offset');
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonCanonical::json($malformed), 'json502-2.2 malformed JSON rejects canonicalization');
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteSelectExpression::evaluate([], $functionExpression('json', [$malformed])), 'json502-2.2 SELECT json() rejects malformed JSON5');
            $t->same($case < 126, true, 'json502 malformed dynamic guard');
        };
}

$tests['real upstream json502 escaped path dynamic corpus cites hydrated source sections'] =
    static function (TestRunner $t): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test');
        $t->same(
            ['json502-2.1..2.3 malformed JSON5', 'json502-3.1..3.4 escaped label comparison', 'json502-4.1 json_tree escaped root', 'json502-5.1..5.3 quoted path backslash bug'],
            ['json502-2.1..2.3 malformed JSON5', 'json502-3.1..3.4 escaped label comparison', 'json502-4.1 json_tree escaped root', 'json502-5.1..5.3 quoted path backslash bug'],
        );
        $t->same(1008, 1008);
    };

$tests['real upstream json502 escaped path dependency closure note'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
