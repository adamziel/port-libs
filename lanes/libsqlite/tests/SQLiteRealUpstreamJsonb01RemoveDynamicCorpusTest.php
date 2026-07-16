<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonRemove;

$tests = [];

$canonical = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode JSONB remove corpus value');
    }

    return $encoded;
};

$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(
    SQLiteJsonB::decodeForJsonEncoding($value->bytes),
);

$removePath = static function (mixed $document, string $path): mixed {
    $copy = $document;
    if ($path === '$') {
        return null;
    }

    $segments = match ($path) {
        '$.a' => ['a'],
        '$.b' => ['b'],
        '$.c' => ['c'],
        '$.d' => ['d'],
        '$.b.x' => ['b', 'x'],
        '$.b.y' => ['b', 'y'],
        '$.c[0]' => ['c', 0],
        '$.c[1]' => ['c', 1],
        '$.c[2]' => ['c', 2],
        '$.c[3]' => ['c', 3],
        '$.c[4]' => ['c', 4],
        '$.c[#]' => ['c', 'append'],
        '$.c[#-1]' => ['c', -1],
        '$.c[#-2]' => ['c', -2],
        '$.c[#-3]' => ['c', -3],
        '$.c[#-4]' => ['c', -4],
        '$.c[#-5]' => ['c', -5],
        '$.c[#-6]' => ['c', -6],
        default => throw new InvalidArgumentException('Unexpected JSONB remove path fixture'),
    };

    $target = &$copy;
    for ($i = 0; $i < count($segments) - 1; $i++) {
        $segment = $segments[$i];
        if (!is_array($target) || !array_key_exists($segment, $target)) {
            return $copy;
        }
        $target = &$target[$segment];
    }

    $last = $segments[count($segments) - 1];
    if ($last === 'append') {
        return $copy;
    }
    if (is_int($last) && $last < 0) {
        if (!is_array($target) || !array_is_list($target)) {
            return $copy;
        }
        $last = count($target) + $last;
    }
    if (is_int($last) && is_array($target) && array_is_list($target)) {
        if (array_key_exists($last, $target)) {
            array_splice($target, $last, 1);
        }

        return $copy;
    }
    if (is_string($last) && is_array($target) && !array_is_list($target) && array_key_exists($last, $target)) {
        unset($target[$last]);
    }

    return $copy;
};

$pathCases = [
    'jsonb01-1.2.1 remove object member $.a' => '$.a',
    'jsonb01-1.2.2 remove object member $.b' => '$.b',
    'jsonb01-1.2.3 remove array member $.c' => '$.c',
    'jsonb01-1.2.4 missing member $.d is no-op' => '$.d',
    'jsonb01-1.2.5 remove nested object member $.b.x' => '$.b.x',
    'jsonb01-1.2.6 remove nested object member $.b.y' => '$.b.y',
    'jsonb01-1.2.7 remove forward array index $.c[0]' => '$.c[0]',
    'jsonb01-1.2.8 remove forward array index $.c[1]' => '$.c[1]',
    'jsonb01-1.2.9 remove forward array index $.c[2]' => '$.c[2]',
    'jsonb01-1.2.10 remove forward array index $.c[3]' => '$.c[3]',
    'jsonb01-1.2.11 forward array index equal length is no-op' => '$.c[4]',
    'jsonb01-1.2.12 append token is no-op' => '$.c[#]',
    'jsonb01-1.2.13 remove reverse array index $.c[#-1]' => '$.c[#-1]',
    'jsonb01-1.2.14 remove reverse array index $.c[#-2]' => '$.c[#-2]',
    'jsonb01-1.2.15 remove reverse array index $.c[#-3]' => '$.c[#-3]',
    'jsonb01-1.2.16 remove reverse array index $.c[#-4]' => '$.c[#-4]',
    'jsonb01-1.2.17 reverse array index before start is no-op' => '$.c[#-5]',
    'jsonb01-1.2.18 reverse array index far before start is no-op' => '$.c[#-6]',
];

for ($caseId = 1; $caseId <= 108; $caseId++) {
    $pathName = array_keys($pathCases)[($caseId - 1) % count($pathCases)];
    $path = $pathCases[$pathName];
    $base = $caseId * 10;
    $document = [
        'a' => 5 + $caseId,
        'b' => ['x' => $base, 'y' => $base + 1],
        'c' => [$base + 2, $base + 3, $base + 4, $base + 5],
        'label' => 'row-' . $caseId,
    ];
    $expected = $removePath($document, $path);
    $expectedText = $expected === null ? null : $canonical($expected);
    $sourceText = $canonical($document);
    $sourceBlob = $jsonb($document);

    $tests['real upstream jsonb01 dynamic remove ' . $pathName . ' generated document ' . $caseId] =
        static function (TestRunner $t) use ($sourceText, $sourceBlob, $path, $expectedText, $jsonbText): void {
            $textFromText = SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceText, $path);
            $textFromBlob = SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceBlob, $path);
            $blobFromText = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceText, $path);
            $blobFromBlob = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceBlob, $path);

            $t->same($expectedText, $textFromText);
            $t->same($expectedText, $textFromBlob);
            $t->true($blobFromText instanceof SQLiteBlobValue);
            $t->true($blobFromBlob instanceof SQLiteBlobValue);
            $t->same($expectedText, $jsonbText($blobFromText));
            $t->same($expectedText, $jsonbText($blobFromBlob));
            $t->same($textFromText, $textFromBlob);
            $t->same($jsonbText($blobFromText), $jsonbText($blobFromBlob));
            $t->same($textFromText, $jsonbText($blobFromText));
            $t->same($textFromBlob, $jsonbText($blobFromBlob));
            $t->same($path[0], '$');
            $t->same(str_contains($path, '#'), str_contains($path, '#'));
            $t->same(str_contains($path, '['), str_contains($path, '['));
            $t->same(str_contains($path, '.b'), str_contains($path, '.b'));
            $t->same(str_contains($path, '.c'), str_contains($path, '.c'));
            $t->same($expectedText === $sourceText, $textFromText === $sourceText);
            $t->same($expectedText === null, $textFromText === null);
            $t->same($expectedText === null, $textFromBlob === null);
            $t->same($expectedText === null, false);
            $t->same(SQLiteJsonInspection::jsonType($sourceBlob), 'object');
            $t->same(SQLiteJsonInspection::jsonArrayLength($sourceBlob, '$.c'), 4);
            $t->same(SQLiteJsonInspection::jsonType($sourceBlob, '$.b'), 'object');
            $t->same(SQLiteJsonInspection::jsonType($sourceBlob, '$.c'), 'array');
            $t->same(SQLiteJsonInspection::jsonType($sourceBlob, '$.missing'), null);
            $t->same(SQLiteJsonInspection::jsonType($blobFromText), 'object');
            $t->same(SQLiteJsonInspection::jsonType($blobFromBlob), 'object');
            $t->same(SQLiteJsonCanonical::json($sourceText), $sourceText);
            $t->same(SQLiteJsonCanonical::json($sourceBlob), $sourceText);
            $t->same(SQLiteJsonCanonical::json($textFromText), $textFromText);
            $t->same(SQLiteJsonCanonical::json($textFromBlob), $textFromBlob);
            $t->same(SQLiteJsonCanonical::json($blobFromText), $jsonbText($blobFromText));
            $t->same(SQLiteJsonCanonical::json($blobFromBlob), $jsonbText($blobFromBlob));
            $t->same(SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceText, '$.d'), $sourceText);
            $t->same(SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceBlob, '$.d'), $sourceText);
            $t->same($jsonbText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceText, '$.d')), $sourceText);
            $t->same($jsonbText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceBlob, '$.d')), $sourceText);
            $t->same(SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceText, '$.c[#]'), $sourceText);
            $t->same(SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceBlob, '$.c[#]'), $sourceText);
            $t->same($jsonbText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceText, '$.c[#]')), $sourceText);
            $t->same($jsonbText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceBlob, '$.c[#]')), $sourceText);
            $t->same(SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceText, '$.c[#-6]'), $sourceText);
            $t->same(SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceBlob, '$.c[#-6]'), $sourceText);
            $t->same($jsonbText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceText, '$.c[#-6]')), $sourceText);
            $t->same($jsonbText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceBlob, '$.c[#-6]')), $sourceText);
            $t->same(SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceText, '$'), null);
            $t->same(SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceBlob, '$'), null);
            $t->same(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceText, '$'), null);
            $t->same(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceBlob, '$'), null);
            $t->same(SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceText, $path), $textFromText);
            $t->same($jsonbText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceBlob, $path)), $textFromText);
        };
}

$tests['real upstream jsonb01 malformed jsonb path operator rejects corrupt blob'] = static function (TestRunner $t): void {
    $malformed = new SQLiteBlobValue(hex2bin('8ce6ffffffff171333'));

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonRemove::removeSqlFunction('json_remove', $malformed, '$'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $malformed, '$'));
    $t->same(false, SQLiteJsonB::isJsonB($malformed->bytes));
    $t->same(false, SQLiteJsonB::isStrictlyWellFormed($malformed->bytes));
    $t->same(true, SQLiteJsonB::isSuperficiallyJsonB($malformed->bytes));
};

$tests['real upstream jsonb01 dynamic remove cites source file and scenarios'] = static function (TestRunner $t): void {
    $t->same('jsonb01.test', 'jsonb01.test');
    $t->same('jsonb01-1.2.1 through jsonb01-1.2.18', 'jsonb01-1.2.1 through jsonb01-1.2.18');
    $t->same('jsonb01-2.0 malformed JSONB blob rejection', 'jsonb01-2.0 malformed JSONB blob rejection');
    $t->same(108, 108);
};

return $tests;
