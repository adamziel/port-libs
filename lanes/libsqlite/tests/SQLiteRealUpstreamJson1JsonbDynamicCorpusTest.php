<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonEach;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonQuote;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$decodeJson = static function (string|SQLiteBlobValue|null $value): mixed {
    if ($value === null) {
        return null;
    }
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonB::decode($value->bytes);
    }

    return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
};

$canonical = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode JSON corpus expectation');
    }

    return $encoded;
};

$fn = static fn (string $name, array $arguments): array => ['type' => 'function', 'name' => $name, 'arguments' => $arguments];
$lit = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];

$tests = [];

$constructorCases = [
    'json101-1.1.00' => [
        'kind' => 'array',
        'args' => [1, 2.5, null, 'hello'],
        'expected' => [1, 2.5, null, 'hello'],
    ],
    'json101-1.1.01' => [
        'kind' => 'array',
        'args' => [1, '{"abc":2.5,"def":null,"ghi":hello}', 99],
        'expected' => [1, '{"abc":2.5,"def":null,"ghi":hello}', 99],
    ],
    'json101-1.1.02' => [
        'kind' => 'array',
        'args' => [1, new SQLiteJsonSubtypeValue('{"abc":2.5,"def":null,"ghi":"hello"}'), 99],
        'expected' => [1, ['abc' => 2.5, 'def' => null, 'ghi' => 'hello'], 99],
    ],
    'json101-1.1.03' => [
        'kind' => 'array',
        'args' => [1, new SQLiteJsonSubtypeValue('{"abc":2.5,"def":null,"ghi":"hello"}'), 99],
        'expected' => [1, ['abc' => 2.5, 'def' => null, 'ghi' => 'hello'], 99],
    ],
    'json101-1.2' => [
        'kind' => 'array',
        'args' => ['String "\\ Test'],
        'expected' => ['String "\\ Test'],
    ],
    'json101-1.4' => [
        'kind' => 'array',
        'args' => [-9223372036854775807 - 1, 9223372036854775807, 0, 1, -1, 0.0, 1.0, -1.0, -1.0e99, 2.0e100, 'one', 'two', 'three', 4, 5, 6, 7, 8, 9, 10, null, 'abcdefghijklmnopqrstuvwyxzABCDEFGHIJKLMNOPQRSTUVWXYZ', 99],
        'expected' => [-9223372036854775807 - 1, 9223372036854775807, 0, 1, -1, 0.0, 1.0, -1.0, -1.0e99, 2.0e100, 'one', 'two', 'three', 4, 5, 6, 7, 8, 9, 10, null, 'abcdefghijklmnopqrstuvwyxzABCDEFGHIJKLMNOPQRSTUVWXYZ', 99],
    ],
    'json101-2.1' => [
        'kind' => 'object',
        'args' => ['a', 1, 'b', 2.5, 'c', null, 'd', 'String Test'],
        'expected' => ['a' => 1, 'b' => 2.5, 'c' => null, 'd' => 'String Test'],
    ],
    'json101-2.2.2' => [
        'kind' => 'object',
        'args' => ['a', new SQLiteJsonSubtypeValue('["xyx",77,4.5]'), 'x', 2.5],
        'expected' => ['a' => ['xyx', 77, 4.5], 'x' => 2.5],
    ],
    'json101-2.2.3' => [
        'kind' => 'object',
        'args' => ['a', new SQLiteBlobValue(SQLiteJsonB::encode(['xyx', 77, 4.5])), 'x', 2.5],
        'expected' => ['a' => ['xyx', 77, 4.5], 'x' => 2.5],
    ],
    'json101-2.5' => [
        'kind' => 'object',
        'args' => ['a', 'xxxxxxxxxx', 'b', new SQLiteBlobValue(SQLiteJsonB::encode([1, 2, 3]))],
        'expected' => ['a' => 'xxxxxxxxxx', 'b' => [1, 2, 3]],
    ],
    'json102-100' => [
        'kind' => 'object',
        'args' => ['ex', '[52,3.14159]'],
        'expected' => ['ex' => '[52,3.14159]'],
    ],
    'json102-110' => [
        'kind' => 'object',
        'args' => ['ex', new SQLiteJsonSubtypeValue('[52,3.14159]')],
        'expected' => ['ex' => [52, 3.14159]],
    ],
    'json102-120' => [
        'kind' => 'object',
        'args' => ['ex', new SQLiteJsonSubtypeValue('[52,3.14159]')],
        'expected' => ['ex' => [52, 3.14159]],
    ],
    'json102-130' => [
        'kind' => 'canonical',
        'args' => [' { "this" : "is", "a": [ "test" ] } '],
        'expected' => ['this' => 'is', 'a' => ['test']],
    ],
    'json102-140' => [
        'kind' => 'array',
        'args' => [1, 2, '3', 4],
        'expected' => [1, 2, '3', 4],
    ],
    'json102-150' => [
        'kind' => 'array',
        'args' => ['[1,2]'],
        'expected' => ['[1,2]'],
    ],
    'json102-160' => [
        'kind' => 'array',
        'args' => [new SQLiteJsonSubtypeValue('[1,2]')],
        'expected' => [[1, 2]],
    ],
    'json102-170' => [
        'kind' => 'array',
        'args' => [1, null, '3', '[4,5]', '{"six":7.7}'],
        'expected' => [1, null, '3', '[4,5]', '{"six":7.7}'],
    ],
    'json102-180' => [
        'kind' => 'array',
        'args' => [1, null, '3', new SQLiteJsonSubtypeValue('[4,5]'), new SQLiteJsonSubtypeValue('{"six":7.7}')],
        'expected' => [1, null, '3', [4, 5], ['six' => 7.7]],
    ],
];

foreach ($constructorCases as $upstreamId => $case) {
    $expectedJson = $canonical($case['expected']);
    if ($case['kind'] === 'canonical') {
        $tests["real upstream {$upstreamId} json canonical text trims json5 whitespace"] = static function (TestRunner $t) use ($case, $expectedJson): void {
            $t->same($expectedJson, SQLiteJsonCanonical::jsonSqlFunction('json', $case['args'][0]));
        };
        $tests["real upstream {$upstreamId} jsonb canonical value decodes to expected document"] = static function (TestRunner $t) use ($case, $decodeJson): void {
            $value = SQLiteJsonCanonical::jsonSqlFunction('jsonb', $case['args'][0]);
            $t->same(true, $value instanceof SQLiteBlobValue);
            $t->same($case['expected'], $decodeJson($value));
        };
        $tests["real upstream {$upstreamId} select expression json canonical dispatch"] = static function (TestRunner $t) use ($case, $expectedJson, $fn, $lit): void {
            $t->same($expectedJson, SQLiteSelectExpression::evaluate([], $fn('json', [$lit($case['args'][0])])));
        };
        continue;
    }

    $textFunction = $case['kind'] === 'array' ? 'json_array' : 'json_object';
    $blobFunction = $case['kind'] === 'array' ? 'jsonb_array' : 'jsonb_object';
    $arguments = $case['args'];

    $tests["real upstream {$upstreamId} {$textFunction} direct canonical result"] = static function (TestRunner $t) use ($textFunction, $arguments, $expectedJson): void {
        $t->same($expectedJson, $textFunction === 'json_array'
            ? SQLiteJsonConstructor::jsonArraySqlFunction($textFunction, ...$arguments)
            : SQLiteJsonConstructor::jsonObjectSqlFunction($textFunction, ...$arguments));
    };
    $tests["real upstream {$upstreamId} {$blobFunction} direct jsonb round trip"] = static function (TestRunner $t) use ($blobFunction, $arguments, $case, $decodeJson): void {
        $value = str_contains($blobFunction, 'array')
            ? SQLiteJsonConstructor::jsonArraySqlFunction($blobFunction, ...$arguments)
            : SQLiteJsonConstructor::jsonObjectSqlFunction($blobFunction, ...$arguments);
        $t->same(true, $value instanceof SQLiteBlobValue);
        $t->same($case['expected'], $decodeJson($value));
    };
    $tests["real upstream {$upstreamId} {$textFunction} select expression dispatch"] = static function (TestRunner $t) use ($textFunction, $arguments, $expectedJson, $fn, $lit): void {
        $t->same($expectedJson, SQLiteSelectExpression::evaluate([], $fn($textFunction, array_map($lit, $arguments))));
    };
    $tests["real upstream {$upstreamId} {$blobFunction} select expression dispatch"] = static function (TestRunner $t) use ($blobFunction, $arguments, $case, $decodeJson, $fn, $lit): void {
        $value = SQLiteSelectExpression::evaluate([], $fn($blobFunction, array_map($lit, $arguments)));
        $t->same(true, $value instanceof SQLiteBlobValue);
        $t->same($case['expected'], $decodeJson($value));
    };
}

$extractDocument = '{"a":2,"c":[4,5,{"f":7}],"x":null,"truth":true,"lie":false,"text":"hello"}';
$extractJsonb = new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($extractDocument, true, 512, JSON_THROW_ON_ERROR)));
$extractCases = [
    'json102-250' => ['paths' => ['$'], 'json' => ['a' => 2, 'c' => [4, 5, ['f' => 7]], 'x' => null, 'truth' => true, 'lie' => false, 'text' => 'hello'], 'scalar' => false],
    'json102-260' => ['paths' => ['$.c'], 'json' => [4, 5, ['f' => 7]], 'scalar' => false],
    'json102-270' => ['paths' => ['$.c[2]'], 'json' => ['f' => 7], 'scalar' => false],
    'json102-280' => ['paths' => ['$.c[2].f'], 'json' => 7, 'scalar' => true],
    'json102-290' => ['paths' => ['$.c', '$.a'], 'json' => [[4, 5, ['f' => 7]], 2], 'scalar' => false],
    'json102-300' => ['paths' => ['$.missing'], 'json' => null, 'scalar' => true],
    'json102-310' => ['paths' => ['$.missing', '$.a'], 'json' => [null, 2], 'scalar' => false],
    'json102-520' => ['paths' => ['$.truth'], 'json' => 1, 'scalar' => true],
    'json102-530' => ['paths' => ['$.lie'], 'json' => 0, 'scalar' => true],
    'json102-540' => ['paths' => ['$.text'], 'json' => 'hello', 'scalar' => true],
    'json102-550' => ['paths' => ['$.x'], 'json' => null, 'scalar' => true],
    'json102-560' => ['paths' => ['$.c[#-1]'], 'json' => ['f' => 7], 'scalar' => false],
    'json102-570' => ['paths' => ['$.c[#-2]'], 'json' => 5, 'scalar' => true],
    'json102-580' => ['paths' => ['$.c[#-3]'], 'json' => 4, 'scalar' => true],
    'json102-590' => ['paths' => ['$.c[#-4]'], 'json' => null, 'scalar' => true],
];

foreach ($extractCases as $upstreamId => $case) {
    $paths = $case['paths'];
    $expected = $case['json'];
    $expectedText = $case['scalar'] ? $expected : $canonical($expected);
    $tests["real upstream {$upstreamId} json_extract text source"] = static function (TestRunner $t) use ($extractDocument, $paths, $expectedText): void {
        $t->same($expectedText, SQLiteJsonExtract::extractSqlFunction('json_extract', $extractDocument, ...$paths));
    };
    $tests["real upstream {$upstreamId} json_extract jsonb input source"] = static function (TestRunner $t) use ($extractJsonb, $paths, $expectedText): void {
        $t->same($expectedText, SQLiteJsonExtract::extractSqlFunction('json_extract', $extractJsonb, ...$paths));
    };
    $tests["real upstream {$upstreamId} jsonb_extract text source"] = static function (TestRunner $t) use ($extractDocument, $paths, $expected, $decodeJson): void {
        $value = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $extractDocument, ...$paths);
        if ($value instanceof SQLiteBlobValue) {
            $t->same($expected, $decodeJson($value));
            return;
        }
        $t->same($expected, $value);
    };
    $tests["real upstream {$upstreamId} jsonb_extract jsonb input source"] = static function (TestRunner $t) use ($extractJsonb, $paths, $expected, $decodeJson): void {
        $value = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $extractJsonb, ...$paths);
        if ($value instanceof SQLiteBlobValue) {
            $t->same($expected, $decodeJson($value));
            return;
        }
        $t->same($expected, $value);
    };
    $tests["real upstream {$upstreamId} select expression json_extract dispatch"] = static function (TestRunner $t) use ($extractDocument, $paths, $expectedText, $fn, $lit): void {
        $t->same($expectedText, SQLiteSelectExpression::evaluate([], $fn('json_extract', array_map($lit, array_merge([$extractDocument], $paths)))));
    };
    $tests["real upstream {$upstreamId} select expression jsonb_extract dispatch"] = static function (TestRunner $t) use ($extractJsonb, $paths, $expected, $decodeJson, $fn, $lit): void {
        $value = SQLiteSelectExpression::evaluate([], $fn('jsonb_extract', array_map($lit, array_merge([$extractJsonb], $paths))));
        if ($value instanceof SQLiteBlobValue) {
            $t->same($expected, $decodeJson($value));
            return;
        }
        $t->same($expected, $value);
    };
}

$inspectionDocuments = [
    'json101-4.1.true' => ['json' => 'true', 'type' => 'true', 'length' => 0, 'valid' => true],
    'json101-4.1.false' => ['json' => 'false', 'type' => 'false', 'length' => 0, 'valid' => true],
    'json101-4.1.null' => ['json' => 'null', 'type' => 'null', 'length' => 0, 'valid' => true],
    'json101-4.1.integer' => ['json' => '123', 'type' => 'integer', 'length' => 0, 'valid' => true],
    'json101-4.1.negative' => ['json' => '-234', 'type' => 'integer', 'length' => 0, 'valid' => true],
    'json101-4.1.real' => ['json' => '34.5e+6', 'type' => 'real', 'length' => 0, 'valid' => true],
    'json101-4.1.empty-string' => ['json' => '""', 'type' => 'text', 'length' => 0, 'valid' => true],
    'json101-4.1.quoted-quote' => ['json' => '"\""', 'type' => 'text', 'length' => 0, 'valid' => true],
    'json101-4.1.quoted-backslash' => ['json' => '"\\\\"', 'type' => 'text', 'length' => 0, 'valid' => true],
    'json101-4.1.text' => ['json' => '"abcdefghijlmnopqrstuvwxyz"', 'type' => 'text', 'length' => 0, 'valid' => true],
    'json101-4.1.array-empty' => ['json' => '[]', 'type' => 'array', 'length' => 0, 'valid' => true],
    'json101-4.1.object-empty' => ['json' => '{}', 'type' => 'object', 'length' => 0, 'valid' => true],
    'json101-4.1.array-mixed' => ['json' => '[true,false,null,123,-234,34.5e+6,{},[]]', 'type' => 'array', 'length' => 8, 'valid' => true],
    'json101-4.1.object-nested' => ['json' => '{"a":true,"b":{"c":false}}', 'type' => 'object', 'length' => 0, 'valid' => true],
    'json101-4.4.empty' => ['json' => '', 'type' => null, 'length' => null, 'valid' => false],
    'json101-4.4.whitespace' => ['json' => " \t\n\r", 'type' => null, 'length' => null, 'valid' => false],
    'json102-190.array-length' => ['json' => '[1,2,3,4]', 'type' => 'array', 'length' => 4, 'valid' => true],
    'json102-220.object-array-child' => ['json' => '{"one":[1,2,3]}', 'type' => 'object', 'length' => 0, 'valid' => true, 'childPath' => '$.one', 'childLength' => 3],
    'json102-240.missing-child' => ['json' => '{"one":[1,2,3]}', 'type' => 'object', 'length' => 0, 'valid' => true, 'childPath' => '$.two', 'childLength' => null],
];

foreach ($inspectionDocuments as $upstreamId => $case) {
    $tests["real upstream {$upstreamId} json_valid text source"] = static function (TestRunner $t) use ($case): void {
        $t->same($case['valid'], SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $case['json']));
    };
    $tests["real upstream {$upstreamId} select expression json_valid dispatch"] = static function (TestRunner $t) use ($case, $fn, $lit): void {
        $t->same($case['valid'] ? 1 : 0, SQLiteSelectExpression::evaluate([], $fn('json_valid', [$lit($case['json'])])));
    };

    if ($case['valid']) {
        $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($case['json'], false, 512, JSON_THROW_ON_ERROR)));
        $tests["real upstream {$upstreamId} json_type text and jsonb source"] = static function (TestRunner $t) use ($case, $jsonb): void {
            $t->same($case['type'], SQLiteJsonInspection::inspectionSqlFunction('json_type', $case['json']));
            $t->same($case['type'], SQLiteJsonInspection::inspectionSqlFunction('json_type', $jsonb));
        };
        $tests["real upstream {$upstreamId} json_array_length text and jsonb source"] = static function (TestRunner $t) use ($case, $jsonb): void {
            $t->same($case['length'], SQLiteJsonInspection::inspectionSqlFunction('json_array_length', $case['json']));
            $t->same($case['length'], SQLiteJsonInspection::inspectionSqlFunction('json_array_length', $jsonb));
        };
        $tests["real upstream {$upstreamId} select expression json_type dispatch"] = static function (TestRunner $t) use ($case, $fn, $lit): void {
            $t->same($case['type'], SQLiteSelectExpression::evaluate([], $fn('json_type', [$lit($case['json'])])));
        };
        $tests["real upstream {$upstreamId} select expression json_array_length dispatch"] = static function (TestRunner $t) use ($case, $fn, $lit): void {
            $t->same($case['length'], SQLiteSelectExpression::evaluate([], $fn('json_array_length', [$lit($case['json'])])));
        };
        if (array_key_exists('childPath', $case)) {
            $tests["real upstream {$upstreamId} child json_array_length path text and jsonb source"] = static function (TestRunner $t) use ($case, $jsonb): void {
                $t->same($case['childLength'], SQLiteJsonInspection::inspectionSqlFunction('json_array_length', $case['json'], $case['childPath']));
                $t->same($case['childLength'], SQLiteJsonInspection::inspectionSqlFunction('json_array_length', $jsonb, $case['childPath']));
            };
            $tests["real upstream {$upstreamId} child json_array_length select expression dispatch"] = static function (TestRunner $t) use ($case, $fn, $lit): void {
                $t->same($case['childLength'], SQLiteSelectExpression::evaluate([], $fn('json_array_length', [$lit($case['json']), $lit($case['childPath'])])));
            };
        }
    } else {
        $tests["real upstream {$upstreamId} json_type rejects invalid source"] = static function (TestRunner $t) use ($case): void {
            $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::inspectionSqlFunction('json_type', $case['json']));
        };
        $tests["real upstream {$upstreamId} json canonical rejects invalid source"] = static function (TestRunner $t) use ($case): void {
            $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::jsonSqlFunction('json', $case['json']));
        };
    }
}

$quoteCases = [
    'json101-9.1' => ['value' => 'abc"xyz', 'expected' => '"abc\"xyz"'],
    'json101-9.2' => ['value' => 3.14159, 'expected' => '3.14159'],
    'json101-9.3' => ['value' => 12345, 'expected' => '12345'],
    'json101-9.4' => ['value' => null, 'expected' => 'null'],
    'json101-20.1.a' => ['value' => INF, 'expected' => '9.0e+999'],
    'json101-20.1.b' => ['value' => -INF, 'expected' => '-9.0e+999'],
];

foreach ($quoteCases as $upstreamId => $case) {
    $tests["real upstream {$upstreamId} json_quote direct scalar result"] = static function (TestRunner $t) use ($case): void {
        $t->same($case['expected'], SQLiteJsonQuote::jsonQuoteSqlFunction('json_quote', $case['value']));
    };
    $tests["real upstream {$upstreamId} json_quote argument vector result"] = static function (TestRunner $t) use ($case): void {
        $t->same($case['expected'], SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('json_quote', [$case['value']]));
    };
    $tests["real upstream {$upstreamId} select expression json_quote dispatch"] = static function (TestRunner $t) use ($case, $fn, $lit): void {
        $t->same($case['expected'], SQLiteSelectExpression::evaluate([], $fn('json_quote', [$lit($case['value'])])));
    };
}

$json101EscapeValidityCases = [];
foreach (range(32, 126) as $ordinal) {
    $char = chr($ordinal);
    $id = $ordinal - 31;
    $valid = in_array($char, ['"', '/', '\\', 'b', 'f', 'n', 'r', 't'], true);
    $json101EscapeValidityCases["json101-10.{$id}"] = ['json' => '" \\' . $char . ' "', 'valid' => $valid];
}
$json101EscapeValidityCases += [
    'json101-10.86.0' => ['json' => '" \\u "', 'valid' => false],
    'json101-10.86.1' => ['json' => '" \\ua "', 'valid' => false],
    'json101-10.86.2' => ['json' => '" \\uab "', 'valid' => false],
    'json101-10.86.3' => ['json' => '" \\uabc "', 'valid' => false],
    'json101-10.86.4' => ['json' => '" \\uabcd "', 'valid' => true],
    'json101-10.86.5' => ['json' => '" \\uFEDC "', 'valid' => true],
    'json101-10.86.6' => ['json' => '" \\u1234 "', 'valid' => true],
];

foreach ($json101EscapeValidityCases as $upstreamId => $case) {
    $tests["real upstream {$upstreamId} json_valid escape acceptance direct"] = static function (TestRunner $t) use ($case): void {
        $t->same($case['valid'], SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $case['json']));
    };
    $tests["real upstream {$upstreamId} json_valid escape acceptance argument vector"] = static function (TestRunner $t) use ($case): void {
        $t->same($case['valid'], SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$case['json']]));
    };
    $tests["real upstream {$upstreamId} select expression json_valid escape dispatch"] = static function (TestRunner $t) use ($case, $fn, $lit): void {
        $t->same($case['valid'] ? 1 : 0, SQLiteSelectExpression::evaluate([], $fn('json_valid', [$lit($case['json'])])));
    };
    if ($case['valid']) {
        $tests["real upstream {$upstreamId} json canonical accepts valid escape"] = static function (TestRunner $t) use ($case): void {
            $t->same(true, is_string(SQLiteJsonCanonical::jsonSqlFunction('json', $case['json'])));
        };
        $tests["real upstream {$upstreamId} jsonb canonical accepts valid escape"] = static function (TestRunner $t) use ($case): void {
            $t->same(true, SQLiteJsonCanonical::jsonSqlFunction('jsonb', $case['json']) instanceof SQLiteBlobValue);
        };
    }
}

$nestedArray1000 = str_repeat('[', 1000) . '0' . str_repeat(']', 1000);
$nestedArray1001 = str_repeat('[', 1001) . '0' . str_repeat(']', 1001);
$nestedObject1000 = str_repeat('{"a":', 1000) . '0' . str_repeat('}', 1000);
$nestedObject1001 = str_repeat('{"a":', 1001) . '0' . str_repeat('}', 1001);
$depthCases = [
    'json101-11.0' => ['json' => $nestedArray1000, 'valid' => true],
    'json101-11.1' => ['json' => $nestedArray1001, 'valid' => false],
    'json101-11.2' => ['json' => $nestedObject1000, 'valid' => true],
    'json101-11.3' => ['json' => $nestedObject1001, 'valid' => false],
];

foreach ($depthCases as $upstreamId => $case) {
    $tests["real upstream {$upstreamId} json_valid nesting depth guard direct"] = static function (TestRunner $t) use ($case): void {
        $t->same($case['valid'], SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $case['json']));
    };
    $tests["real upstream {$upstreamId} select expression nesting depth dispatch"] = static function (TestRunner $t) use ($case, $fn, $lit): void {
        $t->same($case['valid'] ? 1 : 0, SQLiteSelectExpression::evaluate([], $fn('json_valid', [$lit($case['json'])])));
    };
}

$quotedMemberDocument = '{"settings":{"layer2":{"hapax.legomenon":{"forceDisplay":true,"transliterate":true,"add.footnote":true,"summary.report":true},"dis.legomenon":{"forceDisplay":true,"transliterate":false,"add.footnote":false,"summary.report":true},"tris.legomenon":{"forceDisplay":true,"transliterate":false,"add.footnote":false,"summary.report":false}}}}';
$quotedMemberJsonb = new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($quotedMemberDocument, true, 512, JSON_THROW_ON_ERROR)));
$quotedMemberRemoved = ['settings' => ['layer2' => [
    'hapax.legomenon' => ['forceDisplay' => true, 'transliterate' => true, 'add.footnote' => true, 'summary.report' => true],
    'dis.legomenon' => ['transliterate' => false, 'add.footnote' => false, 'summary.report' => true],
    'tris.legomenon' => ['forceDisplay' => true, 'transliterate' => false, 'add.footnote' => false, 'summary.report' => false],
]]];
$quotedMemberRemovePath = '$.settings.layer2."dis.legomenon".forceDisplay';
$quotedMemberExtractPath = '$.settings.layer2."tris.legomenon"."summary.report"';

$tests['real upstream json101-12.110 quoted member json_remove text source'] = static function (TestRunner $t) use ($quotedMemberDocument, $quotedMemberRemovePath, $quotedMemberRemoved, $canonical): void {
    $t->same($canonical($quotedMemberRemoved), SQLiteJsonRemove::removeSqlFunction('json_remove', $quotedMemberDocument, $quotedMemberRemovePath));
};
$tests['real upstream json101-12.110b quoted member json_remove jsonb source'] = static function (TestRunner $t) use ($quotedMemberJsonb, $quotedMemberRemovePath, $quotedMemberRemoved, $canonical): void {
    $t->same($canonical($quotedMemberRemoved), SQLiteJsonRemove::removeSqlFunction('json_remove', $quotedMemberJsonb, $quotedMemberRemovePath));
};
$tests['real upstream json101-12.120 quoted member json_extract text source'] = static function (TestRunner $t) use ($quotedMemberDocument, $quotedMemberExtractPath): void {
    $t->same(0, SQLiteJsonExtract::extractSqlFunction('json_extract', $quotedMemberDocument, $quotedMemberExtractPath));
};
$tests['real upstream json101-12.120b quoted member json_extract jsonb source'] = static function (TestRunner $t) use ($quotedMemberJsonb, $quotedMemberExtractPath): void {
    $t->same(0, SQLiteJsonExtract::extractSqlFunction('json_extract', $quotedMemberJsonb, $quotedMemberExtractPath));
};

$nullCases = [
    'json101-21.2' => static fn (): mixed => SQLiteJsonErrorPosition::jsonErrorPositionSqlFunction('json_error_position', null),
    'json101-21.3' => static fn (): mixed => SQLiteJsonCanonical::jsonSqlFunction('json', null),
    'json101-21.5' => static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', null, '$'),
    'json101-21.6' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_insert', null, '$', 123),
    'json101-21.12' => static fn (): mixed => SQLiteJsonPatch::patchSqlFunction('json_patch', null, '{a:5}'),
    'json101-21.13' => static fn (): mixed => SQLiteJsonPatch::patchSqlFunction('json_patch', '{a:5}', null),
    'json101-21.14' => static fn (): mixed => SQLiteJsonPatch::patchSqlFunction('json_patch', null, null),
    'json101-21.15' => static fn (): mixed => SQLiteJsonRemove::removeSqlFunction('json_remove', null, '$'),
    'json101-21.16' => static fn (): mixed => SQLiteJsonRemove::removeSqlFunction('json_remove', '{a:5,b:7}', null),
    'json101-21.17' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_replace', null, '$.a', 123),
    'json101-21.19' => static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_set', null, '$.a', 123),
    'json101-21.21' => static fn (): mixed => SQLiteJsonInspection::inspectionSqlFunction('json_type', null),
    'json101-21.22' => static fn (): mixed => SQLiteJsonInspection::inspectionSqlFunction('json_type', '{a:5,b:7}', null),
];

foreach ($nullCases as $upstreamId => $callback) {
    $tests["real upstream {$upstreamId} null input returns sql null"] = static function (TestRunner $t) use ($callback): void {
        $t->same(null, $callback());
    };
}

$tests['real upstream json101-21.4 json_array preserves null element'] = static function (TestRunner $t): void {
    $t->same('[null]', SQLiteJsonConstructor::jsonArraySqlFunction('json_array', null));
};
$tests['real upstream json101-21.11 json_object rejects null label'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', null, 5));
};
$tests['real upstream json101-21.18 json_replace null path leaves text unchanged'] = static function (TestRunner $t): void {
    $t->same('{"a":5,"b":7}', SQLiteJsonMutation::mutateSqlFunction('json_replace', '{a:5,b:7}', null, null));
};
$tests['real upstream json101-21.20 json_set null path leaves text unchanged'] = static function (TestRunner $t): void {
    $t->same('{"a":5,"b":7}', SQLiteJsonMutation::mutateSqlFunction('json_set', '{a:5,b:7}', null, null));
};
$tests['real upstream json101-21.23 json_quote null returns json null text'] = static function (TestRunner $t): void {
    $t->same('null', SQLiteJsonQuote::jsonQuoteSqlFunction('json_quote', null));
};
$tests['real upstream json101-21.24 json_each null has no rows'] = static function (TestRunner $t): void {
    $t->same([], SQLiteJsonEach::jsonEachSqlFunction('json_each', null));
};
$tests['real upstream json101-21.25 json_tree null has no rows'] = static function (TestRunner $t): void {
    $t->same([], SQLiteJsonTree::jsonTreeSqlFunction('json_tree', null));
};

$scalarFullkeyCases = [
    'json101-14.100' => ['function' => 'json_each', 'json' => '123', 'value' => 123, 'type' => 'integer'],
    'json101-14.110' => ['function' => 'json_each', 'json' => '123.56', 'value' => 123.56, 'type' => 'real'],
    'json101-14.120' => ['function' => 'json_each', 'json' => '"hello"', 'value' => 'hello', 'type' => 'text'],
    'json101-14.130' => ['function' => 'json_each', 'json' => 'null', 'value' => null, 'type' => 'null'],
    'json101-14.140' => ['function' => 'json_tree', 'json' => '123', 'value' => 123, 'type' => 'integer'],
    'json101-14.150' => ['function' => 'json_tree', 'json' => '123.56', 'value' => 123.56, 'type' => 'real'],
    'json101-14.160' => ['function' => 'json_tree', 'json' => '"hello"', 'value' => 'hello', 'type' => 'text'],
    'json101-14.170' => ['function' => 'json_tree', 'json' => 'null', 'value' => null, 'type' => 'null'],
];

foreach ($scalarFullkeyCases as $upstreamId => $case) {
    $tests["real upstream {$upstreamId} scalar json table fullkey is root"] = static function (TestRunner $t) use ($case): void {
        $rows = $case['function'] === 'json_each'
            ? SQLiteJsonEach::jsonEachSqlFunction('json_each', $case['json'])
            : SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $case['json']);
        $t->same(1, count($rows));
        $t->same('$', $rows[0]['fullkey']);
        $t->same('$', $rows[0]['path']);
        $t->same($case['type'], $rows[0]['type']);
        $t->same($case['value'], $rows[0]['value']);
        $t->same($case['value'], $rows[0]['atom']);
    };
    $tests["real upstream {$upstreamId} scalar json table argument vector"] = static function (TestRunner $t) use ($case): void {
        $rows = $case['function'] === 'json_each'
            ? SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', [$case['json']])
            : SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [$case['json']]);
        $t->same('$', $rows[0]['fullkey']);
        $t->same($case['type'], $rows[0]['type']);
    };
}

$jsonEachObjectRows = static fn (): array => SQLiteJsonEach::jsonEachSqlFunction('JSON_EACH', '{"a":1, "b":2}');
$jsonEachObjectRowsParenthesized = static fn (): array => SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', ['{"a":1, "b":2}']);
$jsonEachExpectedRows = [
    ['key' => 'a', 'value' => 1, 'type' => 'integer', 'atom' => 1, 'fullkey' => '$.a', 'path' => '$'],
    ['key' => 'b', 'value' => 2, 'type' => 'integer', 'atom' => 2, 'fullkey' => '$.b', 'path' => '$'],
];

foreach ([
    'json101-15.100' => $jsonEachObjectRows,
    'json101-15.110' => $jsonEachObjectRows,
    'json101-15.120' => $jsonEachObjectRowsParenthesized,
    'json101-15.130' => $jsonEachObjectRowsParenthesized,
] as $upstreamId => $rowsCallback) {
    $tests["real upstream {$upstreamId} json_each object rows preserve visible columns"] = static function (TestRunner $t) use ($rowsCallback, $jsonEachExpectedRows): void {
        $rows = $rowsCallback();
        $t->same(2, count($rows));
        foreach ($jsonEachExpectedRows as $offset => $expected) {
            foreach ($expected as $column => $value) {
                $t->same($value, $rows[$offset][$column]);
            }
        }
    };
}

$emptyLabelJson = '[3,{"a":4,"":[5,{"hi":6},7]},8]';
$emptyLabelJsonb = new SQLiteBlobValue(SQLiteJsonB::encode([3, ['a' => 4, '' => [5, ['hi' => 6], 7]], 8]));
$emptyLabelExtractCases = [
    'json101-18.2' => ['json' => '{"":5}', 'path' => '$.""', 'expected' => 5],
    'json101-18.3' => ['json' => $emptyLabelJson, 'path' => '$[1].""[1].hi', 'expected' => 6],
    'json101-18.4' => ['json' => $emptyLabelJson, 'path' => '$[1].""[1]."hi"', 'expected' => 6],
];

foreach ($emptyLabelExtractCases as $upstreamId => $case) {
    $tests["real upstream {$upstreamId} json_extract empty quoted member text source"] = static function (TestRunner $t) use ($case): void {
        $t->same($case['expected'], SQLiteJsonExtract::extractSqlFunction('json_extract', $case['json'], $case['path']));
    };
    $tests["real upstream {$upstreamId} select expression empty quoted member dispatch"] = static function (TestRunner $t) use ($case, $fn, $lit): void {
        $t->same($case['expected'], SQLiteSelectExpression::evaluate([], $fn('json_extract', [$lit($case['json']), $lit($case['path'])])));
    };
}
$tests['real upstream json101-18.3 json_extract empty quoted member jsonb source'] = static function (TestRunner $t) use ($emptyLabelJsonb): void {
    $t->same(6, SQLiteJsonExtract::extractSqlFunction('json_extract', $emptyLabelJsonb, '$[1].""[1].hi'));
};
$tests['real upstream json101-18.5 json_extract rejects bare dot path'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extractSqlFunction('json_extract', '{"":8}', '$.'));
};

foreach ([
    'json101-21.7' => ['left' => null, 'operator' => '->', 'right' => 0],
    'json101-21.8' => ['left' => null, 'operator' => '->>', 'right' => 0],
    'json101-21.9' => ['left' => '{a:5}', 'operator' => '->', 'right' => null],
    'json101-21.10' => ['left' => '{a:5}', 'operator' => '->>', 'right' => null],
] as $upstreamId => $case) {
    $tests["real upstream {$upstreamId} json operator null operand returns null"] = static function (TestRunner $t) use ($case, $lit): void {
        $t->same(null, SQLiteSelectExpression::evaluate([], [
            'type' => 'binary',
            'operator' => $case['operator'],
            'left' => $lit($case['left']),
            'right' => $lit($case['right']),
        ]));
    };
}

$mutationCases = [
    'json101-3.1' => ['fn' => 'json_replace', 'input' => '{"a":1,"b":2}', 'path' => '$.a', 'value' => '[3,4,5]', 'expected' => ['a' => '[3,4,5]', 'b' => 2]],
    'json101-3.2' => ['fn' => 'json_replace', 'input' => '{"a":1,"b":2}', 'path' => '$.a', 'value' => new SQLiteJsonSubtypeValue('[3,4,5]'), 'expected' => ['a' => [3, 4, 5], 'b' => 2]],
    'json101-3.3' => ['fn' => 'json_set', 'input' => '{"a":1,"b":2}', 'path' => '$.b', 'value' => '{"x":3,"y":4}', 'expected' => ['a' => 1, 'b' => '{"x":3,"y":4}'], 'typePath' => '$.b', 'type' => 'text'],
    'json101-3.4' => ['fn' => 'json_set', 'input' => '{"a":1,"b":2}', 'path' => '$.b', 'value' => new SQLiteJsonSubtypeValue('{"x":3,"y":4}'), 'expected' => ['a' => 1, 'b' => ['x' => 3, 'y' => 4]], 'typePath' => '$.b', 'type' => 'object'],
    'json101-3.5' => ['fn' => 'json_set', 'input' => '{}', 'path' => '$.x', 'value' => 123, 'pairs' => ['$.x', 456], 'expected' => ['x' => 456]],
    'json102-320' => ['fn' => 'json_insert', 'input' => '{"a":2,"c":4}', 'path' => '$.a', 'value' => 99, 'expected' => ['a' => 2, 'c' => 4]],
    'json102-330' => ['fn' => 'json_insert', 'input' => '{"a":2,"c":4}', 'path' => '$.e', 'value' => 99, 'expected' => ['a' => 2, 'c' => 4, 'e' => 99]],
    'json102-340' => ['fn' => 'json_replace', 'input' => '{"a":2,"c":4}', 'path' => '$.a', 'value' => 99, 'expected' => ['a' => 99, 'c' => 4]],
    'json102-350' => ['fn' => 'json_replace', 'input' => '{"a":2,"c":4}', 'path' => '$.e', 'value' => 99, 'expected' => ['a' => 2, 'c' => 4]],
    'json102-360' => ['fn' => 'json_set', 'input' => '{"a":2,"c":4}', 'path' => '$.a', 'value' => 99, 'expected' => ['a' => 99, 'c' => 4]],
    'json102-370' => ['fn' => 'json_set', 'input' => '{"a":2,"c":4}', 'path' => '$.e', 'value' => 99, 'expected' => ['a' => 2, 'c' => 4, 'e' => 99]],
    'json102-380' => ['fn' => 'json_set', 'input' => '{"a":2,"c":4}', 'path' => '$.c', 'value' => new SQLiteJsonSubtypeValue('[97,96]'), 'expected' => ['a' => 2, 'c' => [97, 96]]],
    'json102-390' => ['fn' => 'json_insert', 'input' => '[1,2,3,4]', 'path' => '$[#]', 'value' => 99, 'expected' => [1, 2, 3, 4, 99]],
    'json102-400' => ['fn' => 'json_insert', 'input' => '[1,2,3,4]', 'path' => '$[2]', 'value' => 99, 'expected' => [1, 2, 3, 4]],
    'json102-410' => ['fn' => 'json_set', 'input' => '[1,2,3,4]', 'path' => '$[2]', 'value' => 99, 'expected' => [1, 2, 99, 4]],
];

foreach ($mutationCases as $upstreamId => $case) {
    $expectedText = $canonical($case['expected']);
    $pairs = $case['pairs'] ?? [];
    $tests["real upstream {$upstreamId} {$case['fn']} text mutation"] = static function (TestRunner $t) use ($case, $pairs, $expectedText): void {
        $t->same($expectedText, SQLiteJsonMutation::mutateSqlFunction($case['fn'], $case['input'], $case['path'], $case['value'], ...$pairs));
    };
    $tests["real upstream {$upstreamId} {$case['fn']} jsonb mutation"] = static function (TestRunner $t) use ($case, $pairs, $decodeJson): void {
        $function = str_replace('json_', 'jsonb_', $case['fn']);
        $value = SQLiteJsonMutation::mutateSqlFunction($function, $case['input'], $case['path'], $case['value'], ...$pairs);
        $t->same(true, $value instanceof SQLiteBlobValue);
        $t->same($case['expected'], $decodeJson($value));
    };
    $tests["real upstream {$upstreamId} select expression {$case['fn']} mutation"] = static function (TestRunner $t) use ($case, $pairs, $expectedText, $fn, $lit): void {
        $arguments = [$case['input'], $case['path'], $case['value'], ...$pairs];
        $t->same($expectedText, SQLiteSelectExpression::evaluate([], $fn($case['fn'], array_map($lit, $arguments))));
    };
    if (isset($case['typePath'])) {
        $tests["real upstream {$upstreamId} json_type observes mutation subtype"] = static function (TestRunner $t) use ($case): void {
            $mutated = SQLiteJsonMutation::mutateSqlFunction($case['fn'], $case['input'], $case['path'], $case['value']);
            $t->same($case['type'], SQLiteJsonInspection::inspectionSqlFunction('json_type', $mutated, $case['typePath']));
        };
    }
}

$removeInput = new SQLiteBlobValue(SQLiteJsonB::encode(['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]));
$jsonbRemoveCases = [
    'jsonb01-1.2.1' => ['path' => '$.a', 'expected' => ['b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.2' => ['path' => '$.b', 'expected' => ['a' => 5, 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.3' => ['path' => '$.c', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11]]],
    'jsonb01-1.2.4' => ['path' => '$.d', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.5' => ['path' => '$.b.x', 'expected' => ['a' => 5, 'b' => ['y' => 11], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.6' => ['path' => '$.b.y', 'expected' => ['a' => 5, 'b' => ['x' => 10], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.7' => ['path' => '$.c[0]', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [2, 3, 4]]],
    'jsonb01-1.2.8' => ['path' => '$.c[1]', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 3, 4]]],
    'jsonb01-1.2.9' => ['path' => '$.c[2]', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 4]]],
    'jsonb01-1.2.10' => ['path' => '$.c[3]', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3]]],
    'jsonb01-1.2.11' => ['path' => '$.c[4]', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.12' => ['path' => '$.c[#]', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.13' => ['path' => '$.c[#-1]', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3]]],
    'jsonb01-1.2.14' => ['path' => '$.c[#-2]', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 4]]],
    'jsonb01-1.2.15' => ['path' => '$.c[#-3]', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 3, 4]]],
    'jsonb01-1.2.16' => ['path' => '$.c[#-4]', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [2, 3, 4]]],
    'jsonb01-1.2.17' => ['path' => '$.c[#-5]', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.18' => ['path' => '$.c[#-6]', 'expected' => ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
];

foreach ($jsonbRemoveCases as $upstreamId => $case) {
    $expectedText = $canonical($case['expected']);
    $tests["real upstream {$upstreamId}.1 jsonb_remove jsonb source"] = static function (TestRunner $t) use ($removeInput, $case, $decodeJson): void {
        $value = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $removeInput, $case['path']);
        $t->same(true, $value instanceof SQLiteBlobValue);
        $t->same($case['expected'], $decodeJson($value));
    };
    $tests["real upstream {$upstreamId}.2 json_remove jsonb source"] = static function (TestRunner $t) use ($removeInput, $case, $expectedText): void {
        $t->same($expectedText, SQLiteJsonRemove::removeSqlFunction('json_remove', $removeInput, $case['path']));
    };
    $tests["real upstream {$upstreamId}.3 select expression jsonb_remove dispatch"] = static function (TestRunner $t) use ($removeInput, $case, $decodeJson, $fn, $lit): void {
        $value = SQLiteSelectExpression::evaluate([], $fn('jsonb_remove', [$lit($removeInput), $lit($case['path'])]));
        $t->same(true, $value instanceof SQLiteBlobValue);
        $t->same($case['expected'], $decodeJson($value));
    };
    $tests["real upstream {$upstreamId}.4 select expression json_remove dispatch"] = static function (TestRunner $t) use ($removeInput, $case, $expectedText, $fn, $lit): void {
        $t->same($expectedText, SQLiteSelectExpression::evaluate([], $fn('json_remove', [$lit($removeInput), $lit($case['path'])])));
    };
}

$tests['real upstream json101-1.3 json_array rejects blob values'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, str_repeat('x', 1000), new SQLiteBlobValue("\xab\xcd"), 3));
};
$tests['real upstream json101-1.3b jsonb_array rejects blob values'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 1, str_repeat('x', 1000), new SQLiteBlobValue("\xab\xcd"), 3));
};
$tests['real upstream json101-2.2 json_object rejects numeric labels'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', str_repeat('x', 1000), 2, 2.5));
};
$tests['real upstream json101-2.2b jsonb_object rejects numeric labels'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('jsonb_object', 'a', str_repeat('x', 1000), 2, 2.5));
};
$tests['real upstream json101-2.3 json_object rejects odd argument count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', 1, 'b'));
};
$tests['real upstream json101-2.4 json_object rejects non jsonb blob values'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', str_repeat('x', 1000), 'b', new SQLiteBlobValue("\xab\xcd")));
};
$tests['real upstream jsonb01-2.0 json operator rejects malformed jsonb blob'] = static function (TestRunner $t) use ($lit): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpression::evaluate([], ['type' => 'binary', 'operator' => '->', 'left' => $lit(new SQLiteBlobValue(hex2bin('8ce6ffffffff171333'))), 'right' => $lit('$')]));
};

return $tests;
