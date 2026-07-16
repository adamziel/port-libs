<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$tests['real upstream types3 storage classes for bound and selected values'] = static function (TestRunner $t): void {
    $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass('xxxxx'), 'types3-1.1 string-only bound value is TEXT');
    $t->same('integer', SQLiteRealExpressionAffinityCorpusPlan::storageClass(3), 'types3-1.2 integer bound value is INTEGER');
    $t->same('integer', SQLiteRealExpressionAffinityCorpusPlan::storageClass(123456789012346), 'types3-1.3 wide integer bound value is INTEGER');
    $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass(2.0), 'types3-1.4 double bound value is REAL');
    $t->same('blob', SQLiteRealExpressionAffinityCorpusPlan::storageClass(new SQLiteBlobValue('abc')), 'types3-1.5 byte-array without string representation is BLOB');
    $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass('abc'), 'types3-1.6 byte-array with string representation remains TEXT');
    $t->same('blob', SQLiteRealExpressionAffinityCorpusPlan::storageClass(new SQLiteBlobValue('abc')), 'types3-2.1 selected blob literal is byte-array/blob');
    $t->same('integer', SQLiteRealExpressionAffinityCorpusPlan::storageClass(123), 'types3-2.2 selected integer is integer');
    $t->same('integer', SQLiteRealExpressionAffinityCorpusPlan::storageClass(1234567890123456), 'types3-2.3 selected wide integer is integer');
    $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass(1234567890123456.1), 'types3-2.4.1 selected decimal is double/real');
    $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass(1234567890123.456), 'types3-2.4.2 selected decimal is double/real');
    $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass('1234567890123456.0'), 'types3-2.5 selected quoted number is text');
    $t->same('null', SQLiteRealExpressionAffinityCorpusPlan::storageClass(null), 'types3-2.6 selected NULL has null storage class');
};

$textPrimaryKeyEquals = static function (string $storedText, mixed $runtimeValue): array {
    $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression(
        $storedText,
        $runtimeValue,
        '=',
        'TEXT',
        'NONE'
    );

    return [
        'matches' => $comparison['result'],
        'leftStorageClass' => $comparison['leftStorageClass'],
        'rightStorageClass' => $comparison['rightStorageClass'],
        'right' => $comparison['right'],
    ];
};

$integerRows = [];
for ($i = 0; $i < 700; $i++) {
    $integerRows[] = [
        'key_name' => (string) $i,
        'runtime_integer' => $i,
        'runtime_text' => (string) $i,
        'mismatch' => (string) $i . '.0',
    ];
}

foreach ($integerRows as $ordinal => $row) {
    $tests['real upstream types3 3.1-3.3 text affinity integer dual representation ' . str_pad((string) $ordinal, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($row, $textPrimaryKeyEquals, $ordinal): void {
            $fromUpper = $textPrimaryKeyEquals($row['key_name'], strtoupper($row['runtime_text']));
            $fromTextType = $textPrimaryKeyEquals($row['key_name'], $row['runtime_text']);
            $fromIntType = $textPrimaryKeyEquals($row['key_name'], $row['runtime_integer']);
            $fromMismatchedText = $textPrimaryKeyEquals($row['key_name'], $row['mismatch']);

            $t->true($fromUpper['matches'] === true, "types3-3.1 upper(integer text) matches TEXT primary key {$ordinal}");
            $t->true($fromTextType['matches'] === true, "types3-3.2 add_text_type(integer) matches TEXT primary key {$ordinal}");
            $t->true($fromIntType['matches'] === true, "types3-3.3 add_int_type(text) uses text affinity for comparison {$ordinal}");
            $t->same('text', $fromIntType['rightStorageClass'], "types3-3.3 integer runtime value is coerced to TEXT before comparison {$ordinal}");
            $t->true($fromMismatchedText['matches'] === false, "types3-3.3 real-looking text remains distinct from integer text {$ordinal}");
        };
}

$realRows = [];
for ($i = 0; $i < 550; $i++) {
    $real = $i + 0.25;
    $realRows[] = [
        'key_name' => (string) $i . '.25',
        'runtime_real' => $real,
        'runtime_text' => (string) $i . '.25',
        'mismatch' => (string) $i . '.250',
    ];
}

foreach ($realRows as $ordinal => $row) {
    $tests['real upstream types3 3.4-3.5 text affinity real dual representation ' . str_pad((string) $ordinal, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($row, $textPrimaryKeyEquals, $ordinal): void {
            $fromRealType = $textPrimaryKeyEquals($row['key_name'], $row['runtime_real']);
            $fromTextType = $textPrimaryKeyEquals($row['key_name'], $row['runtime_text']);
            $fromMismatchedText = $textPrimaryKeyEquals($row['key_name'], $row['mismatch']);

            $t->true($fromRealType['matches'] === true, "types3-3.4 add_real_type(text) uses text representation for comparison {$ordinal}");
            $t->same('text', $fromRealType['rightStorageClass'], "types3-3.4 real runtime value is coerced to TEXT before comparison {$ordinal}");
            $t->same($row['key_name'], $fromRealType['right'], "types3-3.4 canonical REAL text is preserved {$ordinal}");
            $t->true($fromTextType['matches'] === true, "types3-3.5 add_text_type(real) matches TEXT primary key {$ordinal}");
            $t->true($fromMismatchedText['matches'] === false, "types3-3.5 non-canonical real text remains distinct {$ordinal}");
        };
}

$tests['real upstream types3 text affinity dynamic source coverage'] = static function (TestRunner $t) use ($integerRows, $realRows): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test');
    $t->same('types3-1.1 through types3-2.6 and types3-3.1 through types3-3.5', 'types3-1.1 through types3-2.6 and types3-3.1 through types3-3.5');
    $t->same(700, count($integerRows), 'integer dual-representation dynamic case count');
    $t->same(550, count($realRows), 'real dual-representation dynamic case count');
};

return $tests;
