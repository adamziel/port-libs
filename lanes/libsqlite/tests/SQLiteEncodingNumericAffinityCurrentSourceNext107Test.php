<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingNumericAffinityCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'retry_text', 'key_value' => '10'],
    ['setting_id' => 2, 'key_name' => 'retry_integer', 'key_value' => 10],
    ['setting_id' => 3, 'key_name' => 'retry_real', 'key_value' => 10.0],
    ['setting_id' => 4, 'key_name' => 'retry_padded', 'key_value' => '0010'],
    ['setting_id' => 5, 'key_name' => 'retry_decimal', 'key_value' => '10.0'],
    ['setting_id' => 6, 'key_name' => 'retry_exp', 'key_value' => '1e1'],
    ['setting_id' => 7, 'key_name' => 'retry_plus', 'key_value' => '+10'],
    ['setting_id' => 8, 'key_name' => 'retry_non_numeric', 'key_value' => '10x'],
    ['setting_id' => 9, 'key_name' => 'retry_null', 'key_value' => null],
    ['setting_id' => 10, 'key_name' => 'retry_blob', 'key_value' => new SQLiteBlobValue('10')],
    ['setting_id' => 11, 'key_name' => 'retry_false', 'key_value' => false],
    ['setting_id' => 12, 'key_name' => 'retry_true', 'key_value' => true],
    ['setting_id' => 13, 'key_name' => 'retry_small', 'key_value' => '9.5'],
    ['setting_id' => 14, 'key_name' => 'retry_large', 'key_value' => '11'],
    ['setting_id' => 15, 'key_name' => 'retry_spaces', 'key_value' => ' 10 '],
    ['setting_id' => 16, 'key_name' => 'legacy_text_ten', 'key_value' => 'Ten'],
    ['setting_id' => 17, 'key_name' => 'legacy_text_ten_space', 'key_value' => 'Ten '],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'retry_text', 'key_value' => 10],
    ['setting_id' => 2, 'key_name' => 'retry_integer', 'key_value' => '10'],
    ['setting_id' => 3, 'key_name' => 'retry_real', 'key_value' => '10.00'],
    ['setting_id' => 4, 'key_name' => 'retry_padded', 'key_value' => '0010'],
    ['setting_id' => 5, 'key_name' => 'retry_decimal', 'key_value' => '10.5'],
    ['setting_id' => 6, 'key_name' => 'retry_exp', 'key_value' => '1.0e1'],
    ['setting_id' => 7, 'key_name' => 'retry_plus', 'key_value' => '+10'],
    ['setting_id' => 8, 'key_name' => 'retry_non_numeric', 'key_value' => '10x'],
    ['setting_id' => 9, 'key_name' => 'retry_null', 'key_value' => null],
    ['setting_id' => 10, 'key_name' => 'retry_blob', 'key_value' => new SQLiteBlobValue('10')],
    ['setting_id' => 11, 'key_name' => 'retry_false', 'key_value' => false],
    ['setting_id' => 12, 'key_name' => 'retry_true', 'key_value' => true],
    ['setting_id' => 13, 'key_name' => 'retry_small', 'key_value' => '9.5'],
    ['setting_id' => 14, 'key_name' => 'retry_large', 'key_value' => '11'],
    ['setting_id' => 15, 'key_name' => 'retry_spaces', 'key_value' => ' 10 '],
    ['setting_id' => 16, 'key_name' => 'legacy_text_ten', 'key_value' => 'ten'],
    ['setting_id' => 17, 'key_name' => 'legacy_text_ten_space', 'key_value' => 'ten   '],
    ['setting_id' => 18, 'key_name' => 'retry_new_text', 'key_value' => '10'],
    ['setting_id' => 19, 'key_name' => 'retry_new_real', 'key_value' => 10.0],
];

$plan = static fn (
    mixed $probe = 10,
    string $operator = '=',
    string $columnAffinity = 'NUMERIC',
    string $probeAffinity = 'NONE',
    string $collation = 'BINARY',
    int|string $currentEncoding = 'UTF-16LE',
    int|string $nextEncoding = 'UTF-16BE',
    string $currentSource = 'main.app_settings@cookie107',
    string $nextSource = 'main.app_settings@cookie108',
    int $currentCookie = 107,
    int $nextCookie = 108,
): array => SQLiteEncodingNumericAffinityCurrentSourceNextPlan::keyValueRowValueComparisonPlan(
    $currentRows,
    $nextRows,
    'key_value',
    $probe,
    $operator,
    $columnAffinity,
    $probeAffinity,
    $collation,
    $currentEncoding,
    $nextEncoding,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt = static function (array $plan, string $path): mixed {
    $value = $plan;
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'records operator' => ['operator', '='],
    'records column' => ['column', 'key_value'],
    'records column affinity' => ['columnAffinity', 'NUMERIC'],
    'records probe affinity' => ['probeAffinity', 'NONE'],
    'records probe storage' => ['probeStorage', 'integer'],
    'records probe coerced value' => ['probeCoercedValue', 10],
    'records probe coerced storage' => ['probeCoercedStorage', 'integer'],
    'records collation' => ['collation', 'BINARY'],
    'records current source' => ['currentSource', 'main.app_settings@cookie107'],
    'records next source' => ['nextSource', 'main.app_settings@cookie108'],
    'records current cookie' => ['currentSchemaCookie', 107],
    'records next cookie' => ['nextSchemaCookie', 108],
    'records current encoding' => ['currentEncoding', 'UTF-16LE'],
    'records next encoding' => ['nextEncoding', 'UTF-16BE'],
    'numeric equality current rowids' => ['currentRowids', [1, 2, 3, 4, 5, 6, 7, 15]],
    'numeric equality next rowids' => ['nextRowids', [1, 2, 3, 4, 6, 7, 15, 18, 19]],
    'numeric equality retained rowids' => ['retainedRowids', [1, 2, 3, 4, 6, 7, 15]],
    'numeric equality exited decimal rowid' => ['exitedRowids', [5]],
    'numeric equality entered rowids' => ['enteredRowids', [18, 19]],
    'raw values changed' => ['changedValueRowids', [1, 2, 3, 6]],
    'coerced values detect real to integer narrowing' => ['changedCoercedValueRowids', [3]],
    'storage changes detect text integer swaps' => ['changedStorageRowids', [1, 2, 3]],
    'coerced storage detects real to integer narrowing' => ['changedCoercedStorageRowids', [3]],
    'encoding changes include retained text rows' => ['changedEncodingRowids', [1, 2, 3, 4, 6, 7, 15]],
    'encoded bytes change for retained text rows' => ['changedBytesRowids', [1, 2, 3, 4, 6, 7, 15]],
    'current text coerces to integer' => ['currentCoercedValues.1', 10],
    'next integer coerces to integer' => ['nextCoercedValues.1', 10],
    'current real remains real storage' => ['currentCoercedValues.3', 10.0],
    'next decimal text coerces to integer' => ['nextCoercedValues.3', 10],
    'current exponent coerces to integer' => ['currentCoercedValues.6', 10],
    'next exponent coerces to integer' => ['nextCoercedValues.6', 10],
    'current padded text storage' => ['currentStorage.4', 'text'],
    'current padded coerced storage' => ['currentCoercedStorage.4', 'integer'],
    'current padded utf16le bytes' => ['currentBytesHex.4', '3000300031003000'],
    'next padded utf16be bytes' => ['nextBytesHex.4', '0030003000310030'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source name' => ['invalidationReasons.0', 'source-name'],
    'reason schema cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason scan encoding' => ['invalidationReasons.2', 'scan-encoding'],
    'reason storage class' => ['invalidationReasons.3', 'storage-class'],
    'reason numeric storage' => ['invalidationReasons.4', 'numeric-affinity-storage'],
    'reason raw value' => ['invalidationReasons.5', 'raw-value'],
    'reason numeric value' => ['invalidationReasons.6', 'numeric-affinity-value'],
    'reason text encoding' => ['invalidationReasons.7', 'text-encoding'],
    'reason encoded bytes' => ['invalidationReasons.8', 'encoded-bytes'],
    'reason matched rowset' => ['invalidationReasons.9', 'matched-rowset'],
    'dependency numeric affinity' => ['dependencies.0', 'sqlite-numeric-affinity'],
    'dependency collation comparison' => ['dependencies.1', 'sqlite-collation-comparison'],
    'dependency marker' => ['dependencies.2', 'sqlite-current-source-next107'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['encoding numeric affinity current source next107 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['encoding numeric affinity current source next107 less than includes numeric lows only'] = static function (TestRunner $t) use ($plan): void {
    $lt = $plan(10, '<', 'NUMERIC', 'NONE', 'BINARY', 'UTF-16LE', 'UTF-16LE', 'main.app_settings', 'main.app_settings', 107, 107);
    $t->same([11, 12, 13], $lt['currentRowids']);
};

$tests['encoding numeric affinity current source next107 greater than includes nonnumeric text after numeric'] = static function (TestRunner $t) use ($plan): void {
    $gt = $plan(10, '>', 'NUMERIC', 'NONE', 'BINARY', 'UTF-16LE', 'UTF-16LE', 'main.app_settings', 'main.app_settings', 107, 107);
    $t->same([14, 8, 16, 17, 10], $gt['currentRowids']);
};

$tests['encoding numeric affinity current source next107 rtrim collation collapses padded text fallback'] = static function (TestRunner $t) use ($plan): void {
    $rtrim = $plan('Ten', '=', 'NUMERIC', 'NONE', 'RTRIM', 'UTF-16LE', 'UTF-16LE', 'main.app_settings', 'main.app_settings', 107, 107);
    $t->same([16, 17], $rtrim['currentRowids']);
};

$tests['encoding numeric affinity current source next107 nocase collation matches next text fallback'] = static function (TestRunner $t) use ($plan): void {
    $nocase = $plan('TEN', '=', 'NUMERIC', 'NONE', 'NOCASE', 'UTF-16LE', 'UTF-16LE', 'main.app_settings', 'main.app_settings', 107, 107);
    $t->same([16], $nocase['nextRowids']);
};

$tests['encoding numeric affinity current source next107 stable source reusable for same bytes'] = static function (TestRunner $t) use ($currentRows): void {
    $stable = SQLiteEncodingNumericAffinityCurrentSourceNextPlan::keyValueRowValueComparisonPlan(
        $currentRows,
        $currentRows,
        'key_value',
        10,
        '=',
        'NUMERIC',
        'NONE',
        'BINARY',
        'UTF-16LE',
        'UTF-16LE',
        'main.app_settings',
        'main.app_settings',
        107,
        107,
    );
    $t->same(true, $stable['cursorReusable']);
};

$tests['encoding numeric affinity current source next107 rejects missing column'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingNumericAffinityCurrentSourceNextPlan::keyValueRowValueComparisonPlan([['setting_id' => 1]], $nextRows, 'key_value', 10));
};

$tests['encoding numeric affinity current source next107 rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingNumericAffinityCurrentSourceNextPlan::keyValueRowValueComparisonPlan($currentRows, $nextRows, 'key_value', 10, 'LIKE'));
};

$tests['encoding numeric affinity current source next107 rejects unsupported collation'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingNumericAffinityCurrentSourceNextPlan::keyValueRowValueComparisonPlan($currentRows, $nextRows, 'key_value', 10, '=', 'NUMERIC', 'NONE', 'UNICODE'));
};

return $tests;
