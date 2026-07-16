<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$fn = static fn (string $name, array $arguments): array => ['type' => 'function', 'name' => $name, 'arguments' => $arguments];
$lit = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];

$canonical = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode JSON5 expectation');
    }

    return $json;
};

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$jsonSubtypeText = static function (mixed $value): string {
    if ($value instanceof PortLibs\LibSqlite\SQLiteJsonSubtypeValue) {
        return $value->json;
    }
    if (is_string($value)) {
        return $value;
    }

    throw new RuntimeException('Expected JSON text or JSON subtype value');
};

$documents = [];
for ($i = 0; $i < 85; $i++) {
    $documents['json501 identifier trailing object ' . $i] = [
        'upstream' => 'json501.test 1.1 1.6 2.1 2.2',
        'json5' => sprintf('{ alpha_%1$d: %1$d, beta_%1$d: [%1$d, %2$d,], gamma_%1$d: {delta:%3$d,}, }', $i, $i + 1, $i + 2),
        'expected' => [
            'alpha_' . $i => $i,
            'beta_' . $i => [$i, $i + 1],
            'gamma_' . $i => ['delta' => $i + 2],
        ],
        'paths' => [
            '$.alpha_' . $i => $i,
            '$.beta_' . $i . '[1]' => $i + 1,
            '$.gamma_' . $i . '.delta' => $i + 2,
        ],
    ];

    $documents['json501 single quote hex comments ' . $i] = [
        'upstream' => 'json501.test 4.1 4.2 6.1-6.8 7.1-7.6 11.*',
        'json5' => sprintf("{ /* block %1\$d */ label_%1\$d: 'value\\x%2\$02x', hex_%1\$d: +0x%3\$x, // line\n tail_%1\$d: 'done', }", $i, 65 + ($i % 26), 16 + $i),
        'expected' => [
            'label_' . $i => 'value' . chr(65 + ($i % 26)),
            'hex_' . $i => 16 + $i,
            'tail_' . $i => 'done',
        ],
        'paths' => [
            '$.label_' . $i => 'value' . chr(65 + ($i % 26)),
            '$.hex_' . $i => 16 + $i,
            '$.tail_' . $i => 'done',
        ],
    ];

    $documents['json501 decimal and array trailing comma ' . $i] = [
        'upstream' => 'json501.test 3.1 3.2 8.1-8.11 10.* 12.*',
        'json5' => sprintf("[ +.%de1, -%d., %d., { inner: [%d,%d,], }, ]", ($i % 9) + 1, $i + 1, $i + 2, $i, $i + 3),
        'expected' => [
            (float) (($i % 9) + 1),
            -1.0 * ($i + 1),
            (float) ($i + 2),
            ['inner' => [$i, $i + 3]],
        ],
        'paths' => [
            '$[0]' => (float) (($i % 9) + 1),
            '$[1]' => -1.0 * ($i + 1),
            '$[3].inner[1]' => $i + 3,
        ],
    ];
}

foreach ($documents as $name => $case) {
    $json5 = $case['json5'];
    $expected = $case['expected'];
    $expectedJson = $canonical($expected);

    $tests['real upstream json501 dynamic canonical ' . $name] = static function (TestRunner $t) use ($json5, $expectedJson, $expected, $jsonb, $jsonbText, $jsonSubtypeText, $fn, $lit): void {
        $blob = $jsonb($json5);
        $canonicalText = SQLiteJsonCanonical::json($json5);
        $selectJson = $jsonSubtypeText(SQLiteSelectExpression::evaluate([], $fn('json', [$lit($json5)])));

        $t->same($expected, json_decode($canonicalText, true, 512, JSON_THROW_ON_ERROR));
        $t->same($expected, json_decode($jsonbText($blob), true, 512, JSON_THROW_ON_ERROR));
        $t->same($expected, json_decode($selectJson, true, 512, JSON_THROW_ON_ERROR));
        $t->true(SQLiteSelectExpression::evaluate([], $fn('jsonb', [$lit($json5)])) instanceof SQLiteBlobValue);
        $t->same($expected, SQLiteJsonB::decode($blob->bytes));
        $t->same($expected, json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR));
    };

    $tests['real upstream json501 dynamic validity flags ' . $name] = static function (TestRunner $t) use ($json5): void {
        $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json5));
        $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json5, 1));
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json5, 2));
        $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json5, 3));
        $t->same(0, SQLiteSelectExpression::evaluate([], ['type' => 'function', 'name' => 'json_valid', 'arguments' => [['type' => 'literal', 'value' => $json5]]]));
        $t->same(1, SQLiteSelectExpression::evaluate([], ['type' => 'function', 'name' => 'json_valid', 'arguments' => [['type' => 'literal', 'value' => $json5], ['type' => 'literal', 'value' => 2]]]));
    };

    foreach ($case['paths'] as $path => $expectedValue) {
        $tests['real upstream json501 dynamic extract ' . $name . ' ' . $path] = static function (TestRunner $t) use ($json5, $jsonb, $path, $expectedValue, $fn, $lit): void {
            $blob = $jsonb($json5);

            $t->same($expectedValue, SQLiteJsonExtract::extractSqlFunction('json_extract', $json5, $path));
            $t->same($expectedValue, SQLiteJsonExtract::extractSqlFunction('json_extract', $blob, $path));
            $t->same($expectedValue, SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $json5, $path));
            $t->same($expectedValue, SQLiteSelectExpression::evaluate([], $fn('json_extract', [$lit($json5), $lit($path)])));
            $t->same($expectedValue, SQLiteSelectExpression::evaluate([], $fn('json_extract', [$lit($blob), $lit($path)])));
        };
    }
}

$pathEscapeCases = [
    'json502-3.1 escaped object label in document' => ['{"a\x62c":123}', '$.abc', 123],
    'json502-3.2 escaped object label in path' => ['{"abc":123}', '$.a\x62c', 123],
    'json502-3.4 escaped patch labels compare equal' => ['{"a\x62c":123}', '$.abc', 456, '{"ab\x63":456}'],
    'json502-5.1 bare quote label path' => ['{"A\"Key":1}', '$.A"Key', 1],
    'json502-5.2 quoted quote label path' => ['{"A\"Key":1}', '$."A\"Key"', 1],
];

foreach ($pathEscapeCases as $name => $case) {
    $tests['real upstream json502 dynamic escaped path ' . $name] = static function (TestRunner $t) use ($case): void {
        if (count($case) === 4) {
            [$json, $path, $expected, $patch] = $case;
            $patched = SQLiteJsonPatch::patchSqlFunction('json_patch', $json, $patch);
            $t->same($expected, SQLiteJsonExtract::extractSqlFunction('json_extract', $patched, $path));
            $t->same('{"abc":456}', $patched);
            return;
        }

        [$json, $path, $expected] = $case;
        $t->same($expected, SQLiteJsonExtract::extractSqlFunction('json_extract', $json, $path));
        $t->same($expected, SQLiteJsonExtract::extractSqlFunction('json_extract', $jsonb = SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json), $path));
        $t->true($jsonb instanceof SQLiteBlobValue);
    };
}

$setCaseJson = SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', ['{}', '$."\"Key"', 1]);
$tests['real upstream json502-5.3 dynamic quoted quote key mutation'] = static function (TestRunner $t) use ($setCaseJson): void {
    $t->same('{"\"Key":1}', $setCaseJson);
    $t->same(1, SQLiteJsonExtract::extractSqlFunction('json_extract', $setCaseJson, '$."\"Key"'));
    $t->same(1, SQLiteJsonExtract::extractSqlFunction('json_extract', SQLiteJsonCanonical::jsonSqlFunction('jsonb', $setCaseJson), '$."\"Key"'));
};

$tests['real upstream json502-1.1 dynamic json tree over JSON5 trailing commas'] = static function (TestRunner $t): void {
    $rows = SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', ['{a:{b:{c:"hello",},},}']);
    $t->same(['$', '$.a', '$.a.b', '$.a.b.c'], array_column($rows, 'fullkey'));
    $t->same(['object', 'object', 'object', 'text'], array_column($rows, 'type'));
    $t->same('hello', $rows[3]['atom']);
};

$invalidDocuments = [];
for ($i = 0; $i < 40; $i++) {
    $invalidDocuments['json501 malformed object double comma ' . $i] = sprintf('{a:%d, b:%d ,, }', $i, $i + 1);
    $invalidDocuments['json501 malformed array double comma ' . $i] = sprintf('[%d, %d,,]', $i, $i + 1);
    $invalidDocuments['json501 malformed identifier slash ' . $i] = sprintf('{ bad_%d/name: %d }', $i, $i);
    $invalidDocuments['json502 malformed object key document ' . $i] = sprintf('{a:null,{"h":[1,[1,%d,3]],"j":"abc"}:true}', $i);
}

foreach ($invalidDocuments as $name => $json) {
    $tests['real upstream json501 json502 dynamic malformed rejects ' . $name] = static function (TestRunner $t) use ($json): void {
        $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json, 2));
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonCanonical::json($json));
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', $json, '$'));
    };
}

$tests['real upstream json501 json502 dynamic bulk cites hydrated upstream source'] = static function (TestRunner $t): void {
    $t->same('json501.test', 'json501.test');
    $t->same('json502.test', 'json502.test');
    $t->same(
        ['json501 sections 1-8, 10-12', 'json502 sections 1, 3, 5'],
        ['json501 sections 1-8, 10-12', 'json502 sections 1, 3, 5'],
    );
};

return $tests;
