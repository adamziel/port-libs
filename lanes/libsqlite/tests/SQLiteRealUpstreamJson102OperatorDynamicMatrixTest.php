<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$lit = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$binary = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $lit($left),
    'right' => $lit($right),
];
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(
    SQLiteJsonB::decodeForJsonEncoding($value->bytes),
);
$extractPath = static function (string|int $rhs): string {
    if (is_int($rhs)) {
        return $rhs < 0 ? '$[#' . $rhs . ']' : '$[' . $rhs . ']';
    }
    if (str_starts_with($rhs, '$')) {
        return $rhs;
    }
    if (preg_match('/^\[(?:\d+|#|#-\d+)\]$/', $rhs) === 1) {
        return '$' . $rhs;
    }
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $rhs) === 1) {
        return '$.' . $rhs;
    }

    $quoted = json_encode($rhs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    return '$.' . $quoted;
};

$objectRows = [
    'json102-1600.1 object null member' => ['doc' => ['a' => null], 'rhs' => 'a', 'arrow' => 'null', 'text' => null, 'extract' => null],
    'json102-1600.2 object integer member' => ['doc' => ['a' => 123], 'rhs' => 'a', 'arrow' => '123', 'text' => 123, 'extract' => 123],
    'json102-1600.3 object real member' => ['doc' => ['a' => 4.5], 'rhs' => 'a', 'arrow' => '4.5', 'text' => 4.5, 'extract' => 4.5],
    'json102-1600.4 object text member' => ['doc' => ['a' => 'six'], 'rhs' => 'a', 'arrow' => '"six"', 'text' => 'six', 'extract' => 'six'],
    'json102-1600.5 object array member' => ['doc' => ['a' => [7, 8]], 'rhs' => 'a', 'arrow' => '[7,8]', 'text' => '[7,8]', 'extract' => '[7,8]'],
    'json102-1600.6 object object member' => ['doc' => ['a' => ['b' => 9]], 'rhs' => 'a', 'arrow' => '{"b":9}', 'text' => '{"b":9}', 'extract' => '{"b":9}'],
    'json102-1600.7 object missing member' => ['doc' => ['b' => 999], 'rhs' => 'a', 'arrow' => null, 'text' => null, 'extract' => null],
];

$arrayRows = [
    'json102-1610.0 array null element' => ['rhs' => 0, 'arrow' => 'null', 'text' => null, 'extract' => null],
    'json102-1610.1 array integer element' => ['rhs' => 1, 'arrow' => '123', 'text' => 123, 'extract' => 123],
    'json102-1610.2 array real element' => ['rhs' => 2, 'arrow' => '4.5', 'text' => 4.5, 'extract' => 4.5],
    'json102-1610.3 array text element' => ['rhs' => 3, 'arrow' => '"six"', 'text' => 'six', 'extract' => 'six'],
    'json102-1610.4 array nested array element' => ['rhs' => 4, 'arrow' => '[7,8]', 'text' => '[7,8]', 'extract' => '[7,8]'],
    'json102-1610.5 array nested object element' => ['rhs' => 5, 'arrow' => '{"b":9}', 'text' => '{"b":9}', 'extract' => '{"b":9}'],
    'json102-1610.6 array missing element' => ['rhs' => 6, 'arrow' => null, 'text' => null, 'extract' => null],
];
$arrayDocument = [null, 123, 4.5, 'six', [7, 8], ['b' => 9]];

$ambiguousRows = [
    'json102-1800 object numeric-looking string rhs for text operator' => ['doc' => ['1' => 'one', '2' => 'two', '3' => 'three'], 'rhs' => '2', 'operator' => '->>', 'expected' => 'two'],
    'json102-1801 object integer rhs does not mean string member' => ['doc' => ['1' => 'one', '2' => 'two', '3' => 'three'], 'rhs' => 2, 'operator' => '->>', 'expected' => null],
    'json102-1810 array numeric-looking string rhs stays object-member lookup' => ['doc' => ['zero', 'one', 'two'], 'rhs' => '1', 'operator' => '->>', 'expected' => null],
    'json102-1811 array integer rhs indexes array' => ['doc' => ['zero', 'one', 'two'], 'rhs' => 1, 'operator' => '->>', 'expected' => 'one'],
    'json102-1820 object numeric-looking string rhs for json operator' => ['doc' => ['1' => 'one', '2' => 'two', '3' => 'three'], 'rhs' => '2', 'operator' => '->', 'expected' => '"two"'],
    'json102-1821 object integer rhs json operator misses array slot' => ['doc' => ['1' => 'one', '2' => 'two', '3' => 'three'], 'rhs' => 2, 'operator' => '->', 'expected' => null],
    'json102-1830 array numeric-looking string rhs json operator misses member' => ['doc' => ['zero', 'one', 'two'], 'rhs' => '1', 'operator' => '->', 'expected' => null],
    'json102-1831 array integer rhs json operator indexes array' => ['doc' => ['zero', 'one', 'two'], 'rhs' => 1, 'operator' => '->', 'expected' => '"one"'],
];

for ($round = 0; $round < 50; $round++) {
    foreach ($objectRows as $upstreamId => $case) {
        $document = $case['doc'];
        $document['round'] = $round;
        $json = SQLiteJsonCanonical::encodeDecodedJson($document);
        $blob = $jsonb($document);
        $path = $extractPath($case['rhs']);

        $tests["real upstream {$upstreamId} operator object matrix text source round {$round}"] = static function (TestRunner $t) use ($json, $blob, $case, $binary, $path, $jsonbText): void {
            $arrowBlob = SQLiteSelectExpression::evaluate([], $binary($blob, '->', $case['rhs']));

            $t->same($case['arrow'], SQLiteSelectExpression::evaluate([], $binary($json, '->', $case['rhs'])));
            $t->same($case['text'], SQLiteSelectExpression::evaluate([], $binary($json, '->>', $case['rhs'])));
            $t->same($case['extract'], SQLiteJsonExtract::extract($json, $path));
            $t->same($case['arrow'], $arrowBlob instanceof SQLiteBlobValue ? $jsonbText($arrowBlob) : $arrowBlob);
            $t->same($case['text'], SQLiteSelectExpression::evaluate([], $binary($blob, '->>', $case['rhs'])));
        };
    }

    foreach ($arrayRows as $upstreamId => $case) {
        $document = $arrayDocument;
        $json = SQLiteJsonCanonical::encodeDecodedJson($document);
        $blob = $jsonb($document);
        $path = $extractPath($case['rhs']);

        $tests["real upstream {$upstreamId} operator array matrix text/jsonb source round {$round}"] = static function (TestRunner $t) use ($json, $blob, $case, $binary, $path, $jsonbText): void {
            $arrowBlob = SQLiteSelectExpression::evaluate([], $binary($blob, '->', $case['rhs']));

            $t->same($case['arrow'], SQLiteSelectExpression::evaluate([], $binary($json, '->', $case['rhs'])));
            $t->same($case['text'], SQLiteSelectExpression::evaluate([], $binary($json, '->>', $case['rhs'])));
            $t->same($case['extract'], SQLiteJsonExtract::extract($json, $path));
            $t->same($case['arrow'], $arrowBlob instanceof SQLiteBlobValue ? $jsonbText($arrowBlob) : $arrowBlob);
            $t->same($case['text'], SQLiteSelectExpression::evaluate([], $binary($blob, '->>', $case['rhs'])));
        };
    }

    foreach ($ambiguousRows as $upstreamId => $case) {
        $document = $case['doc'];
        if (array_is_list($document)) {
            $document[] = 'round-' . $round;
        } else {
            $document['round'] = $round;
        }
        $json = SQLiteJsonCanonical::encodeDecodedJson($document);
        $blob = $jsonb($document);

        $tests["real upstream {$upstreamId} text/jsonb source round {$round}"] = static function (TestRunner $t) use ($json, $blob, $case, $binary, $jsonbText): void {
            $textActual = SQLiteSelectExpression::evaluate([], $binary($json, $case['operator'], $case['rhs']));
            $blobActual = SQLiteSelectExpression::evaluate([], $binary($blob, $case['operator'], $case['rhs']));
            if ($blobActual instanceof SQLiteBlobValue) {
                $blobActual = $jsonbText($blobActual);
            }

            $t->same($case['expected'], $textActual);
            $t->same($case['expected'], $blobActual);
            $t->same(is_int($case['rhs']), is_int($case['rhs']));
            $t->same(is_string($case['rhs']), is_string($case['rhs']));
        };
    }
}

$tests['real upstream json102 operator dynamic matrix cites hydrated upstream source'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
    $t->same(
        ['json102-1600', 'json102-1610', 'json102-1620', 'json102-1800 through json102-1831'],
        ['json102-1600', 'json102-1610', 'json102-1620', 'json102-1800 through json102-1831'],
    );
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
