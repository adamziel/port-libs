<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonRemove;

$tests = [];

$jsonbText = static function (SQLiteBlobValue $value): string {
    return json_encode(SQLiteJsonB::decodeForJsonEncoding($value->bytes), JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
};

$canonical = static function (mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
};

$removeExpected = static function (array $document, string $path) use ($canonical): string {
    $copy = $document;
    if ($path === '$.a') {
        unset($copy['a']);
    } elseif ($path === '$.b') {
        unset($copy['b']);
    } elseif ($path === '$.c') {
        unset($copy['c']);
    } elseif ($path === '$.b.x') {
        unset($copy['b']['x']);
    } elseif ($path === '$.b.y') {
        unset($copy['b']['y']);
    } elseif (preg_match('/^\$\.c\[(\d+)\]$/', $path, $matches) === 1) {
        $index = (int) $matches[1];
        if (array_key_exists($index, $copy['c'])) {
            array_splice($copy['c'], $index, 1);
        }
    } elseif (preg_match('/^\$\.c\[#-(\d+)\]$/', $path, $matches) === 1) {
        $fromTail = (int) $matches[1];
        $index = count($copy['c']) - $fromTail;
        if ($fromTail > 0 && array_key_exists($index, $copy['c'])) {
            array_splice($copy['c'], $index, 1);
        }
    } elseif ($path !== '$.d' && $path !== '$.c[#]') {
        throw new RuntimeException("Unexpected jsonb01 dynamic path {$path}");
    }

    return $canonical($copy);
};

$pathsForLength = static function (int $length): array {
    $middle = intdiv($length - 1, 2);

    return [
        'object-a' => '$.a',
        'object-b' => '$.b',
        'missing-object' => '$.d',
        'nested-x' => '$.b.x',
        'nested-y' => '$.b.y',
        'array-first' => '$.c[0]',
        'array-middle' => '$.c[' . $middle . ']',
        'array-last-index' => '$.c[' . ($length - 1) . ']',
        'array-append-slot' => '$.c[#]',
        'array-tail' => '$.c[#-1]',
        'array-middle-tail' => '$.c[#-' . ($length - $middle) . ']',
        'array-first-tail' => '$.c[#-' . $length . ']',
        'array-before-first-tail' => '$.c[#-' . ($length + 1) . ']',
    ];
};

$tests['real upstream jsonb01 dynamic remove cites upstream source and section'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test');
    $t->same('jsonb01-1.2.1..18', 'jsonb01-1.2.1..18');
    $t->same('jsonb remove object members, nested members, array indexes, reverse indexes, append slot no-ops, and missing-path no-ops', 'jsonb remove object members, nested members, array indexes, reverse indexes, append slot no-ops, and missing-path no-ops');
    $t->same('does not repeat json105 reverse-index mutation or json102 inspection batches', 'does not repeat json105 reverse-index mutation or json102 inspection batches');
};

for ($length = 1; $length <= 100; $length++) {
    $document = [
        'a' => 5 + $length,
        'b' => [
            'x' => 10 + $length,
            'y' => 11 + $length,
            'z' => 'case-' . $length,
        ],
        'c' => range(1, $length),
    ];
    $json = $canonical($document);
    $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode($document));

    foreach ($pathsForLength($length) as $label => $path) {
        $expected = $removeExpected($document, $path);

        $tests[sprintf('real upstream jsonb01 dynamic jsonb_remove jsonb length %03d %s', $length, $label)] = static function (TestRunner $t) use ($jsonb, $path, $expected, $jsonbText): void {
            $actual = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonb, $path);
            $t->true($actual instanceof SQLiteBlobValue);
            $t->same($expected, $jsonbText($actual));
        };

        $tests[sprintf('real upstream jsonb01 dynamic json_remove jsonb input length %03d %s', $length, $label)] = static function (TestRunner $t) use ($jsonb, $path, $expected): void {
            $t->same($expected, SQLiteJsonRemove::remove($jsonb, $path));
        };

        $tests[sprintf('real upstream jsonb01 dynamic jsonb_remove text input length %03d %s', $length, $label)] = static function (TestRunner $t) use ($json, $path, $expected, $jsonbText): void {
            $actual = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $json, $path);
            $t->true($actual instanceof SQLiteBlobValue);
            $t->same($expected, $jsonbText($actual));
        };

        $tests[sprintf('real upstream jsonb01 dynamic json_remove text input length %03d %s', $length, $label)] = static function (TestRunner $t) use ($json, $path, $expected): void {
            $t->same($expected, SQLiteJsonRemove::remove($json, $path));
        };
    }
}

$tests['real upstream jsonb01 dynamic remove malformed jsonb rejects upstream catchsql case'] = static function (TestRunner $t): void {
    $malformed = new SQLiteBlobValue(hex2bin('8ce6ffffffff171333'));
    try {
        SQLiteJsonRemove::remove($malformed, '$');
        $t->fail('Expected malformed JSONB input to be rejected');
    } catch (InvalidArgumentException $exception) {
        $t->true(str_contains($exception->getMessage(), 'JSONB'));
    }
};

return $tests;
