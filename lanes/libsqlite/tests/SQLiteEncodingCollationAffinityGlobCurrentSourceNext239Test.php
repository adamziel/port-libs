<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan;

$tests = [];

$current239 = [
    ['option_id' => 1, 'option_name' => 'legacy_plugin_payload', 'option_value' => "plugin_\xe2legacy"],
    ['option_id' => 2, 'option_name' => 'legacy_plugin_pair', 'option_value' => "plugin_\xe2("],
    ['option_id' => 3, 'option_name' => 'legacy_plugin_valid_euro', 'option_value' => "plugin_\xe2\x82\xac"],
    ['option_id' => 4, 'option_name' => 'legacy_plugin_continuation', 'option_value' => "plugin_\x82tail"],
    ['option_id' => 5, 'option_name' => 'legacy_plugin_other_bad', 'option_value' => "plugin_\xc3("],
    ['option_id' => 6, 'option_name' => 'legacy_plugin_upper', 'option_value' => "Plugin_\xe2tail"],
    ['option_id' => 7, 'option_name' => 'numeric_retry', 'option_value' => 42],
    ['option_id' => 8, 'option_name' => 'float_retry', 'option_value' => 42.5],
    ['option_id' => 9, 'option_name' => 'blob_payload', 'option_value' => new SQLiteBlobValue("plugin_\xe2blob")],
    ['option_id' => 10, 'option_name' => 'boolean_retry', 'option_value' => true],
];

$next239 = [
    ['option_id' => 1, 'option_name' => 'legacy_plugin_payload', 'option_value' => "plugin_\xe2legacy2"],
    ['option_id' => 2, 'option_name' => 'legacy_plugin_pair', 'option_value' => "plugin_\xe2("],
    ['option_id' => 3, 'option_name' => 'legacy_plugin_truncated_euro', 'option_value' => "plugin_\xe2\x82"],
    ['option_id' => 4, 'option_name' => 'legacy_plugin_continuation', 'option_value' => "plugin_\x82tail"],
    ['option_id' => 5, 'option_name' => 'legacy_plugin_other_bad', 'option_value' => "plugin_\xc3("],
    ['option_id' => 6, 'option_name' => 'legacy_plugin_upper', 'option_value' => "Plugin_\xe2tail"],
    ['option_id' => 7, 'option_name' => 'numeric_retry', 'option_value' => 42],
    ['option_id' => 8, 'option_name' => 'float_retry', 'option_value' => 43.5],
    ['option_id' => 9, 'option_name' => 'blob_payload', 'option_value' => new SQLiteBlobValue("plugin_\xe2blob")],
    ['option_id' => 10, 'option_name' => 'boolean_retry', 'option_value' => false],
    ['option_id' => 11, 'option_name' => 'new_legacy_plugin', 'option_value' => "plugin_\xe2new"],
    ['option_id' => 12, 'option_name' => 'new_valid_utf8', 'option_value' => "plugin_\xe3\x81\x82"],
];

$plan239 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = "plugin_[\xe2-\xe2]*",
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
    'expression' => ['expression', 'CAST(option_value AS TEXT) GLOB ? /* malformed-byte bracket range current-source fence */'],
    'pattern' => ['pattern', "plugin_[\xe2-\xe2]*"],
    'pattern hex' => ['patternBytesHex', '706c7567696e5f5be22de25d2a'],
    'pattern tokens' => ['patternTokens', ['70', '6c', '75', '67', '69', '6e', '5f', '5b', 'e2', '2d', 'e2', '5d', '2a']],
    'pattern token count' => ['patternTokenCount', 13],
    'collation' => ['collation', 'BINARY'],
    'prefix lower' => ['prefixLowerInclusive', 'plugin_'],
    'prefix upper' => ['prefixUpperBound', 'plugin`'],
    'prefix lower hex' => ['prefixLowerHex', '706c7567696e5f'],
    'prefix upper hex' => ['prefixUpperHex', '706c7567696e60'],
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
    'current pair hex' => ['currentTextsHex.2', '706c7567696e5fe228'],
    'current payload hex' => ['currentTextsHex.1', '706c7567696e5fe26c6567616379'],
    'next truncated hex' => ['nextTextsHex.3', '706c7567696e5fe282'],
    'next new hex' => ['nextTextsHex.11', '706c7567696e5fe26e6577'],
    'current pair tokens' => ['currentPatternTokens.2', ['70', '6c', '75', '67', '69', '6e', '5f', 'e2', '28']],
    'next truncated tokens' => ['nextPatternTokens.3', ['70', '6c', '75', '67', '69', '6e', '5f', 'e2', '82']],
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
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($current239, $next239, "plugin_[\xe2-\xe2]*");
    $t->same(false, array_key_exists(3, $plan['currentTextsHex']));
};

$tests['encoding collation affinity glob current source next239 continuation byte range matches separate byte'] = static function (TestRunner $t) use ($current239, $next239): void {
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($current239, $next239, "plugin_[\x80-\x8f]*");
    $t->same([4], $plan['currentRowids']);
    $t->same([4], $plan['nextRowids']);
    $t->same(['source-name', 'schema-cookie'], $plan['invalidationReasons']);
};

$tests['encoding collation affinity glob current source next239 question consumes one malformed byte'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => "legacy_\xe2_tail"],
        ['option_id' => 2, 'option_value' => "legacy_\xe2\x82_tail"],
        ['option_id' => 3, 'option_value' => "legacy_\xe2\x82\xac_tail"],
    ];
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($rows, $rows, 'legacy_?_tail', 'same', 'same', 1, 1);
    $t->same([1, 3], $plan['currentRowids']);
};

$tests['encoding collation affinity glob current source next239 negated malformed byte class excludes e2'] = static function (TestRunner $t) use ($current239, $next239): void {
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($current239, $next239, "plugin_[^\xe2]*");
    $t->same([4, 5, 3], $plan['currentRowids']);
    $t->same([4, 5, 12], $plan['nextRowids']);
};

$tests['encoding collation affinity glob current source next239 reversed malformed range is literal start only'] = static function (TestRunner $t) use ($current239, $next239): void {
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($current239, $next239, "plugin_[\xe3-\xe2]*");
    $t->same([], $plan['currentRowids']);
    $t->same([], $plan['nextRowids']);
};

$tests['encoding collation affinity glob current source next239 binary glob keeps uppercase distinct'] = static function (TestRunner $t) use ($current239, $next239): void {
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($current239, $next239, "Plugin_[\xe2-\xe2]*");
    $t->same([6], $plan['currentRowids']);
    $t->same([6], $plan['nextRowids']);
};

$tests['encoding collation affinity glob current source next239 numeric affinity participates'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 42],
        ['option_id' => 2, 'option_value' => 42.5],
        ['option_id' => 3, 'option_value' => true],
        ['option_id' => 4, 'option_value' => false],
        ['option_id' => 5, 'option_value' => null],
    ];
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($rows, $rows, '42*', 'same', 'same', 1, 1);
    $t->same([1, 2], $plan['currentRowids']);
    $t->same(['3432', '34322e35'], array_values($plan['currentTextsHex']));
};

$tests['encoding collation affinity glob current source next239 blob and null remain non matches'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => new SQLiteBlobValue("plugin_\xe2blob")],
        ['option_id' => 2, 'option_value' => null],
    ];
    $plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($rows, $rows, "plugin_[\xe2-\xe2]*", 'same', 'same', 1, 1);
    $t->same([], $plan['currentRowids']);
    $t->same(false, $plan['cursorInvalidated']);
};

$tests['encoding collation affinity glob current source next239 stable cursor reusable'] = static function (TestRunner $t) use ($current239, $plan239): void {
    $stable = $plan239(current: $current239, next: $current239, currentSource: 'stable', nextSource: 'stable', currentCookie: 239, nextCookie: 239);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity glob current source next239 rejects missing option value'] = static function (TestRunner $t) use ($next239): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan([['option_id' => 1]], $next239, "plugin_[\xe2-\xe2]*"));
};

$tests['encoding collation affinity glob current source next239 rejects non scalar value'] = static function (TestRunner $t) use ($next239): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan([['option_id' => 1, 'option_value' => ['x']]], $next239, "plugin_[\xe2-\xe2]*"));
};

return $tests;
