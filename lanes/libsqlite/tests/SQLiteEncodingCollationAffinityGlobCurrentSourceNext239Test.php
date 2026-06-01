<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan;

$tests = [];

$current239 = [
    ['setting_id' => 1, 'key_name' => 'legacy_module_payload', 'key_value' => "module_\xe2legacy"],
    ['setting_id' => 2, 'key_name' => 'legacy_module_pair', 'key_value' => "module_\xe2("],
    ['setting_id' => 3, 'key_name' => 'legacy_module_valid_euro', 'key_value' => "module_\xe2\x82\xac"],
    ['setting_id' => 4, 'key_name' => 'legacy_module_continuation', 'key_value' => "module_\x82tail"],
    ['setting_id' => 5, 'key_name' => 'legacy_module_other_bad', 'key_value' => "module_\xc3("],
    ['setting_id' => 6, 'key_name' => 'legacy_module_upper', 'key_value' => "Module_\xe2tail"],
    ['setting_id' => 7, 'key_name' => 'numeric_retry', 'key_value' => 42],
    ['setting_id' => 8, 'key_name' => 'float_retry', 'key_value' => 42.5],
    ['setting_id' => 9, 'key_name' => 'blob_payload', 'key_value' => new SQLiteBlobValue("module_\xe2blob")],
    ['setting_id' => 10, 'key_name' => 'boolean_retry', 'key_value' => true],
];

$next239 = [
    ['setting_id' => 1, 'key_name' => 'legacy_module_payload', 'key_value' => "module_\xe2legacy2"],
    ['setting_id' => 2, 'key_name' => 'legacy_module_pair', 'key_value' => "module_\xe2("],
    ['setting_id' => 3, 'key_name' => 'legacy_module_truncated_euro', 'key_value' => "module_\xe2\x82"],
    ['setting_id' => 4, 'key_name' => 'legacy_module_continuation', 'key_value' => "module_\x82tail"],
    ['setting_id' => 5, 'key_name' => 'legacy_module_other_bad', 'key_value' => "module_\xc3("],
    ['setting_id' => 6, 'key_name' => 'legacy_module_upper', 'key_value' => "Module_\xe2tail"],
    ['setting_id' => 7, 'key_name' => 'numeric_retry', 'key_value' => 42],
    ['setting_id' => 8, 'key_name' => 'float_retry', 'key_value' => 43.5],
    ['setting_id' => 9, 'key_name' => 'blob_payload', 'key_value' => new SQLiteBlobValue("module_\xe2blob")],
    ['setting_id' => 10, 'key_name' => 'boolean_retry', 'key_value' => false],
    ['setting_id' => 11, 'key_name' => 'new_legacy_module', 'key_value' => "module_\xe2new"],
    ['setting_id' => 12, 'key_name' => 'new_valid_utf8', 'key_value' => "module_\xe3\x81\x82"],
];

$plan239 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = "module_[\xe2-\xe2]*",
    string $currentSource = 'main.app_settings@238',
    string $nextSource = 'main.app_settings@239',
    int $currentCookie = 238,
    int $nextCookie = 239,
): array => SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan(
    $current ?? $current239,
    $next ?? $next239,
    $pattern,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt239 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases239 = [
    'status' => ['status', 'encoding-collation-affinity-glob-current-source-next239'],
    'operator' => ['operator', 'GLOB'],
    'expression' => ['expression', 'CAST(key_value AS TEXT) GLOB ? /* malformed-byte bracket range current-source fence */'],
    'pattern' => ['pattern', "module_[\xe2-\xe2]*"],
    'pattern hex' => ['patternBytesHex', '6d6f64756c655f5be22de25d2a'],
    'pattern tokens' => ['patternTokens', ['6d', '6f', '64', '75', '6c', '65', '5f', '5b', 'e2', '2d', 'e2', '5d', '2a']],
    'pattern token count' => ['patternTokenCount', 13],
    'collation' => ['collation', 'BINARY'],
    'prefix lower' => ['prefixLowerInclusive', 'module_'],
    'prefix upper' => ['prefixUpperBound', 'module`'],
    'prefix lower hex' => ['prefixLowerHex', '6d6f64756c655f'],
    'prefix upper hex' => ['prefixUpperHex', '6d6f64756c6560'],
    'current source' => ['currentSource', 'main.app_settings@238'],
    'next source' => ['nextSource', 'main.app_settings@239'],
    'current cookie' => ['currentSchemaCookie', 238],
    'next cookie' => ['nextSchemaCookie', 239],
    'current rowids' => ['currentRowids', [2, 1]],
    'next rowids' => ['nextRowids', [2, 1, 11, 3]],
    'retained rowids' => ['retainedRowids', [2, 1]],
    'exited rowids' => ['exitedRowids', []],
    'entered rowids' => ['enteredRowids', [11, 3]],
    'changed bytes' => ['changedBytesRowids', [1]],
    'changed storage' => ['changedStorageRowids', []],
    'changed token count' => ['changedTokenCountRowids', [1]],
    'current malformed rowids' => ['currentMalformedRowids', [2, 1]],
    'next malformed rowids' => ['nextMalformedRowids', [2, 1, 11, 3]],
    'current pair hex' => ['currentTextsHex.2', '6d6f64756c655fe228'],
    'current payload hex' => ['currentTextsHex.1', '6d6f64756c655fe26c6567616379'],
    'next truncated hex' => ['nextTextsHex.3', '6d6f64756c655fe282'],
    'next new hex' => ['nextTextsHex.11', '6d6f64756c655fe26e6577'],
    'current pair tokens' => ['currentPatternTokens.2', ['6d', '6f', '64', '75', '6c', '65', '5f', 'e2', '28']],
    'next truncated tokens' => ['nextPatternTokens.3', ['6d', '6f', '64', '75', '6c', '65', '5f', 'e2', '82']],
    'current token count pair' => ['currentTokenCounts.2', 9],
    'next token count payload' => ['nextTokenCounts.1', 15],
    'current storage text' => ['currentStorage.1', 'text'],
    'next storage text' => ['nextStorage.11', 'text'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason matched rowset' => ['invalidationReasons.2', 'matched-rowset'],
    'reason bytes' => ['invalidationReasons.3', 'glob-text-bytes'],
    'reason tokens' => ['invalidationReasons.4', 'glob-token-count'],
    'malformed byte flag' => ['malformedBytesAreSingleGlobCharacters', true],
    'valid codepoint flag' => ['validUtf8CodepointsStayIntact', true],
    'binary flag' => ['globUsesBinaryComparison', true],
    'dependency tokenizer' => ['dependencies.0', 'sqlite-glob-malformed-utf8-byte-tokenizer'],
    'dependency range' => ['dependencies.1', 'sqlite-glob-bracket-range'],
    'dependency affinity' => ['dependencies.2', 'sqlite-text-affinity'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-next239'],
];

foreach ($cases239 as $name => [$path, $expected]) {
    $tests['encoding collation affinity glob current source next239 ' . $name] = static function (TestRunner $t) use ($plan239, $valueAt239, $path, $expected): void {
        $t->same($expected, $valueAt239($plan239(), $path));
    };
}

$tests['encoding collation affinity glob current source next239 valid euro does not match malformed byte range'] = static function (TestRunner $t) use ($current239, $next239): void {
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($current239, $next239, "module_[\xe2-\xe2]*");
    $t->same(false, array_key_exists(3, $plan['currentTextsHex']));
};

$tests['encoding collation affinity glob current source next239 continuation byte range matches separate byte'] = static function (TestRunner $t) use ($current239, $next239): void {
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($current239, $next239, "module_[\x80-\x8f]*");
    $t->same([4], $plan['currentRowids']);
    $t->same([4], $plan['nextRowids']);
    $t->same(['source-name', 'schema-cookie'], $plan['invalidationReasons']);
};

$tests['encoding collation affinity glob current source next239 question consumes one malformed byte'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => "legacy_\xe2_tail"],
        ['setting_id' => 2, 'key_value' => "legacy_\xe2\x82_tail"],
        ['setting_id' => 3, 'key_value' => "legacy_\xe2\x82\xac_tail"],
    ];
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($rows, $rows, 'legacy_?_tail', 'same', 'same', 1, 1);
    $t->same([1, 3], $plan['currentRowids']);
};

$tests['encoding collation affinity glob current source next239 negated malformed byte class excludes e2'] = static function (TestRunner $t) use ($current239, $next239): void {
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($current239, $next239, "module_[^\xe2]*");
    $t->same([4, 5, 3], $plan['currentRowids']);
    $t->same([4, 5, 12], $plan['nextRowids']);
};

$tests['encoding collation affinity glob current source next239 reversed malformed range is literal start only'] = static function (TestRunner $t) use ($current239, $next239): void {
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($current239, $next239, "module_[\xe3-\xe2]*");
    $t->same([], $plan['currentRowids']);
    $t->same([], $plan['nextRowids']);
};

$tests['encoding collation affinity glob current source next239 binary glob keeps uppercase distinct'] = static function (TestRunner $t) use ($current239, $next239): void {
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($current239, $next239, "Module_[\xe2-\xe2]*");
    $t->same([6], $plan['currentRowids']);
    $t->same([6], $plan['nextRowids']);
};

$tests['encoding collation affinity glob current source next239 numeric affinity participates'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 42],
        ['setting_id' => 2, 'key_value' => 42.5],
        ['setting_id' => 3, 'key_value' => true],
        ['setting_id' => 4, 'key_value' => false],
        ['setting_id' => 5, 'key_value' => null],
    ];
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($rows, $rows, '42*', 'same', 'same', 1, 1);
    $t->same([1, 2], $plan['currentRowids']);
    $t->same(['3432', '34322e35'], array_values($plan['currentTextsHex']));
};

$tests['encoding collation affinity glob current source next239 blob and null remain non matches'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => new SQLiteBlobValue("module_\xe2blob")],
        ['setting_id' => 2, 'key_value' => null],
    ];
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($rows, $rows, "module_[\xe2-\xe2]*", 'same', 'same', 1, 1);
    $t->same([], $plan['currentRowids']);
    $t->same(false, $plan['cursorInvalidated']);
};

$tests['encoding collation affinity glob current source next239 stable cursor reusable'] = static function (TestRunner $t) use ($current239, $plan239): void {
    $stable = $plan239(current: $current239, next: $current239, currentSource: 'stable', nextSource: 'stable', currentCookie: 239, nextCookie: 239);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity glob current source next239 rejects missing key value'] = static function (TestRunner $t) use ($next239): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan([['setting_id' => 1]], $next239, "module_[\xe2-\xe2]*"));
};

$tests['encoding collation affinity glob current source next239 rejects non scalar value'] = static function (TestRunner $t) use ($next239): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan([['setting_id' => 1, 'key_value' => ['x']]], $next239, "module_[\xe2-\xe2]*"));
};

return $tests;
