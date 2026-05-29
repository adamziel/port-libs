<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current232 = [
    ['option_id' => 1, 'option_name' => 'legacy_plugin_payload', 'option_value' => "Plugin_\xe2legacy"],
    ['option_id' => 2, 'option_name' => 'legacy_plugin_prefix', 'option_value' => "plugin_\xe2"],
    ['option_id' => 3, 'option_name' => 'legacy_plugin_bad_pair', 'option_value' => "plugin_\xe2("],
    ['option_id' => 4, 'option_name' => 'legacy_plugin_valid_euro', 'option_value' => "plugin_\xe2\x82\xac"],
    ['option_id' => 5, 'option_name' => 'legacy_plugin_other_bad', 'option_value' => "plugin_\xc3("],
    ['option_id' => 6, 'option_name' => 'legacy_plugin_valid_hiragana', 'option_value' => "plugin_\xe3\x81\x82"],
    ['option_id' => 7, 'option_name' => 'numeric_retry', 'option_value' => 123],
    ['option_id' => 8, 'option_name' => 'blob_payload', 'option_value' => new SQLiteBlobValue("plugin_\xe2blob")],
    ['option_id' => 9, 'option_name' => 'ascii_plugin', 'option_value' => 'plugin_X'],
    ['option_id' => 10, 'option_name' => 'uppercase_plugin', 'option_value' => "PLUGIN_\xe2TAIL"],
];

$next232 = [
    ['option_id' => 1, 'option_name' => 'legacy_plugin_payload', 'option_value' => "Plugin_\xe2legacy2"],
    ['option_id' => 3, 'option_name' => 'legacy_plugin_bad_pair', 'option_value' => "plugin_\xe2("],
    ['option_id' => 4, 'option_name' => 'legacy_plugin_valid_euro_now_truncated', 'option_value' => "plugin_\xe2\x82"],
    ['option_id' => 5, 'option_name' => 'legacy_plugin_other_bad', 'option_value' => "plugin_\xc3("],
    ['option_id' => 6, 'option_name' => 'legacy_plugin_valid_hiragana', 'option_value' => "plugin_\xe3\x81\x82"],
    ['option_id' => 7, 'option_name' => 'numeric_retry', 'option_value' => 123],
    ['option_id' => 8, 'option_name' => 'blob_payload', 'option_value' => new SQLiteBlobValue("plugin_\xe2blob")],
    ['option_id' => 9, 'option_name' => 'ascii_plugin', 'option_value' => 'plugin_X'],
    ['option_id' => 10, 'option_name' => 'uppercase_plugin', 'option_value' => "PLUGIN_\xe2TAIL"],
    ['option_id' => 11, 'option_name' => 'new_legacy_plugin', 'option_value' => "plugin_\xe2new"],
];

$plan232 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = "plugin!_\xe2%",
    ?string $escape = '!',
    bool $caseSensitive = false,
    string $currentSource = 'main.wp_options@231',
    string $nextSource = 'main.wp_options@232',
    int $currentCookie = 231,
    int $nextCookie = 232,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueMalformedByteLikePlan(
    $current ?? $current232,
    $next ?? $next232,
    $pattern,
    $escape,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt232 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases232 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-next232'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'CAST(option_value AS TEXT) COLLATE NOCASE LIKE ? ESCAPE ? /* malformed-byte current-source fence */'],
    'pattern hex' => ['patternBytesHex', '706c7567696e215fe225'],
    'pattern characters' => ['patternCharacterCount', 10],
    'escape' => ['escape', '!'],
    'escape hex' => ['escapeBytesHex', '21'],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'NOCASE'],
    'prefix' => ['prefix', "plugin_\xe2"],
    'prefix hex' => ['prefixBytesHex', '706c7567696e5fe2'],
    'prefix characters' => ['prefixCharacters', 8],
    'prefix ascii flag' => ['prefixIsAscii', false],
    'range lower' => ['rangeLowerInclusive', "plugin_\xe2"],
    'range upper' => ['rangeUpperBound', "plugin_\xe3"],
    'current source' => ['currentSource', 'main.wp_options@231'],
    'next source' => ['nextSource', 'main.wp_options@232'],
    'current cookie' => ['currentSchemaCookie', 231],
    'next cookie' => ['nextSchemaCookie', 232],
    'current rowids' => ['currentRowids', [10, 1, 2, 3]],
    'next rowids' => ['nextRowids', [10, 1, 3, 11, 4]],
    'retained rowids' => ['retainedRowids', [10, 1, 3]],
    'exited rowids' => ['exitedRowids', [2]],
    'entered rowids' => ['enteredRowids', [11, 4]],
    'changed bytes' => ['changedBytesRowids', [1]],
    'changed storage' => ['changedStorageRowids', []],
    'current malformed rowids' => ['currentMalformedRowids', [10, 1, 2, 3]],
    'next malformed rowids' => ['nextMalformedRowids', [10, 1, 3, 11, 4]],
    'current uppercase hex' => ['currentTextsHex.10', '504c5547494e5fe25441494c'],
    'current bad pair hex' => ['currentTextsHex.3', '706c7567696e5fe228'],
    'next truncated euro hex' => ['nextTextsHex.4', '706c7567696e5fe282'],
    'next new hex' => ['nextTextsHex.11', '706c7567696e5fe26e6577'],
    'current uppercase tokens' => ['currentPatternTokens.10', ['50', '4c', '55', '47', '49', '4e', '5f', 'e2', '54', '41', '49', '4c']],
    'current bad pair tokens' => ['currentPatternTokens.3', ['70', '6c', '75', '67', '69', '6e', '5f', 'e2', '28']],
    'next truncated tokens' => ['nextPatternTokens.4', ['70', '6c', '75', '67', '69', '6e', '5f', 'e2', '82']],
    'current token count uppercase' => ['currentTokenCounts.10', 12],
    'next token count truncated' => ['nextTokenCounts.4', 9],
    'current storage string' => ['currentStorage.1', 'text'],
    'next storage string' => ['nextStorage.11', 'text'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'invalid reason source' => ['invalidationReasons.0', 'source-name'],
    'invalid reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'invalid reason rowset' => ['invalidationReasons.2', 'matched-rowset'],
    'invalid reason malformed bytes' => ['invalidationReasons.3', 'malformed-byte-text'],
    'malformed byte flag' => ['malformedBytesAreSingleCharacters', true],
    'valid codepoint flag' => ['validUtf8CodepointsStayIntact', true],
    'nocase flag' => ['nocaseFoldsAsciiOnly', true],
    'dependency tokenizer' => ['dependencies.0', 'sqlite-like-malformed-utf8-byte-tokenizer'],
    'dependency affinity' => ['dependencies.1', 'sqlite-text-affinity'],
    'dependency collation' => ['dependencies.2', 'sqlite-nocase-ascii-collation'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-next232'],
];

foreach ($cases232 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source next232 ' . $name] = static function (TestRunner $t) use ($plan232, $valueAt232, $path, $expected): void {
        $t->same($expected, $valueAt232($plan232(), $path));
    };
}

$tests['encoding collation affinity like current source next232 valid euro does not match malformed byte prefix'] = static function (TestRunner $t) use ($current232, $next232): void {
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueMalformedByteLikePlan($current232, $next232, "plugin!_\xe2%", '!');
    $t->same(false, array_key_exists(4, $plan['currentTextsHex']));
};

$tests['encoding collation affinity like current source next232 case sensitive excludes uppercase plugin'] = static function (TestRunner $t) use ($plan232): void {
    $case = $plan232(caseSensitive: true);
    $t->same([2, 3], $case['currentRowids']);
    $t->same(false, array_key_exists(10, $case['currentTextsHex']));
};

$tests['encoding collation affinity like current source next232 stable cursor reusable'] = static function (TestRunner $t) use ($current232, $plan232): void {
    $stable = $plan232(current: $current232, next: $current232, currentSource: 'stable', nextSource: 'stable', currentCookie: 232, nextCookie: 232);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source next232 underscore consumes one malformed byte'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => "legacy_\xe2_tail"],
        ['option_id' => 2, 'option_value' => "legacy_\xe2\x82_tail"],
        ['option_id' => 3, 'option_value' => "legacy_\xe2\x82\xac_tail"],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueMalformedByteLikePlan($rows, $rows, "legacy!___tail", '!', false, 'same', 'same', 1, 1);
    $t->same([1, 3], $plan['currentRowids']);
};

$tests['encoding collation affinity like current source next232 numeric affinity participates'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 123],
        ['option_id' => 2, 'option_value' => 12.5],
        ['option_id' => 3, 'option_value' => true],
        ['option_id' => 4, 'option_value' => null],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueMalformedByteLikePlan($rows, $rows, '12%', null, false, 'same', 'same', 1, 1);
    $t->same([2, 1], $plan['currentRowids']);
    $t->same(['31322e35', '313233'], array_values($plan['currentTextsHex']));
};

$tests['encoding collation affinity like current source next232 blob and null remain sql null for like'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => new SQLiteBlobValue("plugin_\xe2blob")],
        ['option_id' => 2, 'option_value' => null],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueMalformedByteLikePlan($rows, $rows, "plugin!_\xe2%", '!', false, 'same', 'same', 1, 1);
    $t->same([], $plan['currentRowids']);
};

$tests['encoding collation affinity like current source next232 rejects multi character escape'] = static function (TestRunner $t) use ($current232, $next232): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueMalformedByteLikePlan($current232, $next232, "plugin!_\xe2%", '!!'));
};

$tests['encoding collation affinity like current source next232 rejects missing option value'] = static function (TestRunner $t) use ($next232): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueMalformedByteLikePlan([['option_id' => 1]], $next232, "plugin!_\xe2%", '!'));
};

$tests['encoding collation affinity like current source next232 rejects non scalar value'] = static function (TestRunner $t) use ($next232): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueMalformedByteLikePlan([['option_id' => 1, 'option_value' => ['x']]], $next232, "plugin!_\xe2%", '!'));
};

return $tests;
