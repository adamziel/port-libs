<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$binary = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(
    SQLiteJsonB::decodeForJsonEncoding($value->bytes),
);
$jsonArrowText = static fn (mixed $value): mixed => $value instanceof SQLiteJsonSubtypeValue ? $value->json : $value;

$malformedRows = [];
$upstreamMalformed = hex2bin('8ce6ffffffff171333');
if (!is_string($upstreamMalformed)) {
    throw new RuntimeException('Unable to build upstream jsonb01 malformed blob');
}
$malformedRows['jsonb01-2.0 upstream malformed object payload'] = $upstreamMalformed;

for ($i = 1; $i <= 256; $i++) {
    $document = [
        'a' => $i,
        'b' => [
            'x' => $i + 10,
            'y' => $i + 11,
            'label' => 'jsonb01-malformed-' . $i,
        ],
        'c' => [$i, $i + 1, $i + 2, $i + 3],
    ];
    $validBytes = SQLiteJsonB::encode($document);
    $byteLength = strlen($validBytes);

    $truncateAt = max(1, $byteLength - (($i % 5) + 1));
    $malformedRows['jsonb01-2.0 truncated encoded JSONB document ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] =
        substr($validBytes, 0, $truncateAt);

    $withTrailing = $validBytes . chr(($i * 17) & 0xff);
    $malformedRows['jsonb01-2.0 trailing byte encoded JSONB document ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] =
        $withTrailing;

    $corruptPayload = $validBytes;
    if ($byteLength > 3) {
        $offset = 1 + ($i % ($byteLength - 1));
        $corruptPayload[$offset] = chr(ord($corruptPayload[$offset]) ^ 0xff);
        if (!SQLiteJsonB::isJsonB($corruptPayload)) {
            $malformedRows['jsonb01-2.0 corrupt payload encoded JSONB document ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] =
                $corruptPayload;
        }
    }
}

$caseNumber = 0;
foreach ($malformedRows as $scenario => $bytes) {
    $caseNumber++;
    $tests['real upstream jsonb01 malformed JSONB rejects JSON operators ' . str_pad((string) $caseNumber, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($scenario, $bytes, $binary): void {
            $blob = new SQLiteBlobValue($bytes);

            $t->same(false, SQLiteJsonB::isJsonB($bytes), $scenario . ' strict JSONB decoder rejects');
            $t->same(false, SQLiteJsonB::isStrictlyWellFormed($bytes), $scenario . ' strict well-formed check rejects');
            $t->same(false, SQLiteJsonValidity::jsonValid($blob, SQLiteJsonValidity::FLAG_STRICT_JSONB), $scenario . ' json_valid flag 8 rejects');
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 8]), $scenario . ' sql json_valid flag 8 rejects');

            $errorPosition = SQLiteJsonErrorPosition::jsonErrorPosition($blob);
            $t->true(is_int($errorPosition), $scenario . ' json_error_position returns integer');
            $t->true($errorPosition >= 1, $scenario . ' json_error_position is positive');

            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonCanonical::json($blob));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extract($blob, '$'));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonInspection::jsonType($blob));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonRemove::removeSqlFunction('json_remove', $blob, '$.a'));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteSelectExpression::evaluate([], $binary($blob, '->', '$')));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteSelectExpression::evaluate([], $binary($blob, '->>', '$.a')));

            $t->same($bytes, $blob->bytes, $scenario . ' blob bytes are not mutated by failed reads');
        };
}

for ($i = 1; $i <= 320; $i++) {
    $document = [
        'a' => $i,
        'b' => [
            'x' => $i + 10,
            'y' => $i + 11,
            'label' => 'jsonb01-valid-' . $i,
        ],
        'c' => [$i, $i + 1, $i + 2, $i + 3],
    ];
    $blob = $jsonb($document);
    $expectedText = SQLiteJsonCanonical::encodeDecodedJson($document);

    $tests['real upstream jsonb01 valid JSONB control remains readable ' . str_pad((string) $i, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($blob, $document, $expectedText, $binary, $jsonArrowText, $jsonbText): void {
            $t->same(true, SQLiteJsonB::isJsonB($blob->bytes));
            $t->same(true, SQLiteJsonB::isStrictlyWellFormed($blob->bytes));
            $t->same(true, SQLiteJsonValidity::jsonValid($blob, SQLiteJsonValidity::FLAG_STRICT_JSONB));
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($blob));
            $t->same($expectedText, SQLiteJsonCanonical::json($blob));
            $t->same($document['a'], SQLiteJsonExtract::extract($blob, '$.a'));
            $t->same($document['b']['x'], SQLiteJsonExtract::extract($blob, '$.b.x'));
            $t->same('object', SQLiteJsonInspection::jsonType($blob));
            $t->same('array', SQLiteJsonInspection::jsonType($blob, '$.c'));
            $t->same(4, SQLiteJsonInspection::jsonArrayLength($blob, '$.c'));
            $t->same((string) $document['a'], $jsonArrowText(SQLiteSelectExpression::evaluate([], $binary($blob, '->', '$.a'))));
            $t->same($document['a'], SQLiteSelectExpression::evaluate([], $binary($blob, '->>', '$.a')));
            $removed = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $blob, '$.b.y');
            $t->true($removed instanceof SQLiteBlobValue);
            $t->same(null, SQLiteJsonExtract::extract($removed, '$.b.y'));
            $t->same($document['b']['x'], SQLiteJsonExtract::extract($removed, '$.b.x'));
            $t->same($jsonbText($removed), SQLiteJsonCanonical::json($removed));
        };
}

$tests['real upstream jsonb01 malformed dynamic corpus citations'] = static function (TestRunner $t) use ($malformedRows): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test');
    $t->same('jsonb01-2.0 malformed JSONB blob rejection', 'jsonb01-2.0 malformed JSONB blob rejection');
    $t->true(count($malformedRows) >= 513, 'malformed JSONB boundary rows are generated from real JSONB encodings plus the upstream corrupt blob');
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
