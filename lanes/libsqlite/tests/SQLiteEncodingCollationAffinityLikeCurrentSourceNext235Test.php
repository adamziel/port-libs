<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current235 = [
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
    ['option_id' => 12, 'option_name' => 'boolean_plugin', 'option_value' => true],
    ['option_id' => 13, 'option_name' => 'null_plugin', 'option_value' => null],
];

$nextTwoThreeFive = [
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
    ['option_id' => 12, 'option_name' => 'boolean_plugin', 'option_value' => false],
    ['option_id' => 13, 'option_name' => 'null_plugin', 'option_value' => null],
    ['option_id' => 14, 'option_name' => 'numeric_text_plugin', 'option_value' => '123'],
];

$plan235 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = "plugin!_\xe2%",
    ?string $escape = '!',
    bool $caseSensitive = false,
    bool $negate = true,
    string $currentSource = 'main.wp_options@234',
    string $nextSource = 'main.wp_options@235',
    int $currentCookie = 234,
    int $nextCookie = 235,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueNotLikePlan(
    $current ?? $current235,
    $next ?? $nextTwoThreeFive,
    $pattern,
    $escape,
    $caseSensitive,
    $negate,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt235 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases235 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoThreeFive'],
    'operator' => ['operator', 'NOT LIKE'],
    'expression' => ['expression', 'CAST(option_value AS TEXT) COLLATE NOCASE NOT LIKE ? ESCAPE ? /* malformed-byte complement current-source fence */'],
    'pattern hex' => ['patternBytesHex', '706c7567696e215fe225'],
    'pattern characters' => ['patternCharacterCount', 10],
    'escape' => ['escape', '!'],
    'escape hex' => ['escapeBytesHex', '21'],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'NOCASE'],
    'negate flag' => ['negate', true],
    'prefix' => ['prefix', "plugin_\xe2"],
    'prefix hex' => ['prefixBytesHex', '706c7567696e5fe2'],
    'prefix characters' => ['prefixCharacters', 8],
    'prefix ascii flag' => ['prefixIsAscii', false],
    'range lower' => ['rangeLowerInclusive', "plugin_\xe2"],
    'range upper' => ['rangeUpperBound', "plugin_\xe3"],
    'current source' => ['currentSource', 'main.wp_options@234'],
    'next source' => ['nextSource', 'main.wp_options@235'],
    'current cookie' => ['currentSchemaCookie', 234],
    'next cookie' => ['nextSchemaCookie', 235],
    'current result rowids' => ['currentResultRowids', [12, 7, 9, 5, 4, 6]],
    'next result rowids' => ['nextResultRowids', [12, 7, 14, 9, 5, 6]],
    'current like rowids' => ['currentLikeRowids', [10, 1, 2, 3]],
    'next like rowids' => ['nextLikeRowids', [10, 1, 3, 11, 4]],
    'current unknown rowids' => ['currentUnknownRowids', [8, 13]],
    'next unknown rowids' => ['nextUnknownRowids', [8, 13]],
    'retained rowids' => ['retainedResultRowids', [12, 7, 9, 5, 6]],
    'exited rowids' => ['exitedResultRowids', [4]],
    'entered rowids' => ['enteredResultRowids', [14]],
    'changed bytes' => ['changedBytesRowids', [1, 4, 12]],
    'changed storage' => ['changedStorageRowids', []],
    'changed truth' => ['changedPredicateTruthRowids', [4]],
    'current malformed rowids' => ['currentMalformedRowids', [10, 1, 5, 2, 3]],
    'next malformed rowids' => ['nextMalformedRowids', [10, 1, 5, 3, 11, 4]],
    'current uppercase hex' => ['currentTextsHex.10', '504c5547494e5fe25441494c'],
    'current bad pair hex' => ['currentTextsHex.3', '706c7567696e5fe228'],
    'next truncated euro hex' => ['nextTextsHex.4', '706c7567696e5fe282'],
    'next numeric text hex' => ['nextTextsHex.14', '313233'],
    'current uppercase tokens' => ['currentPatternTokens.10', ['50', '4c', '55', '47', '49', '4e', '5f', 'e2', '54', '41', '49', '4c']],
    'next truncated tokens' => ['nextPatternTokens.4', ['70', '6c', '75', '67', '69', '6e', '5f', 'e2', '82']],
    'current true result' => ['currentPredicateResults.12', true],
    'next false result' => ['nextPredicateResults.12', true],
    'current euro result' => ['currentPredicateResults.4', true],
    'next truncated result' => ['nextPredicateResults.4', false],
    'current storage bool' => ['currentStorage.12', 'integer'],
    'next storage text' => ['nextStorage.14', 'text'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'invalid reason source' => ['invalidationReasons.0', 'source-name'],
    'invalid reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'invalid reason rowset' => ['invalidationReasons.2', 'result-rowset'],
    'invalid reason truth' => ['invalidationReasons.3', 'predicate-truth'],
    'invalid reason malformed bytes' => ['invalidationReasons.4', 'malformed-byte-text'],
    'not like complement flag' => ['notLikeUsesLikeTruthComplement', true],
    'unknown flag' => ['unknownValuesDoNotEnterComplement', true],
    'malformed byte flag' => ['malformedBytesAreSingleCharacters', true],
    'valid codepoint flag' => ['validUtf8CodepointsStayIntact', true],
    'nocase flag' => ['nocaseFoldsAsciiOnly', true],
    'dependency tokenizer' => ['dependencies.0', 'sqlite-like-malformed-utf8-byte-tokenizer'],
    'dependency affinity' => ['dependencies.1', 'sqlite-text-affinity'],
    'dependency collation' => ['dependencies.2', 'sqlite-nocase-ascii-collation'],
    'dependency complement' => ['dependencies.3', 'sqlite-not-like-truth-complement'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoThreeFive'],
];

foreach ($cases235 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoThreeFive ' . $name] = static function (TestRunner $t) use ($plan235, $valueAt235, $path, $expected): void {
        $t->same($expected, $valueAt235($plan235(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoThreeFive LIKE mode returns positive rowset'] = static function (TestRunner $t) use ($plan235): void {
    $like = $plan235(negate: false);
    $t->same('LIKE', $like['operator']);
    $t->same([10, 1, 2, 3], $like['currentResultRowids']);
    $t->same([10, 1, 3, 11, 4], $like['nextResultRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeFive case sensitive complement includes uppercase malformed prefix'] = static function (TestRunner $t) use ($plan235): void {
    $case = $plan235(caseSensitive: true);
    $t->same([12, 7, 10, 1, 9, 5, 4, 6], $case['currentResultRowids']);
    $t->same(false, in_array(2, $case['currentResultRowids'], true));
};

$tests['encoding collation affinity like current source nextTwoThreeFive stable cursor reusable'] = static function (TestRunner $t) use ($current235, $plan235): void {
    $stable = $plan235(current: $current235, next: $current235, currentSource: 'stable', nextSource: 'stable', currentCookie: 235, nextCookie: 235);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoThreeFive unknown null and blob stay outside complement'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => new SQLiteBlobValue("plugin_\xe2blob")],
        ['option_id' => 2, 'option_value' => null],
        ['option_id' => 3, 'option_value' => 'other'],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueNotLikePlan($rows, $rows, "plugin!_\xe2%", '!', false, true, 'same', 'same', 1, 1);
    $t->same([3], $plan['currentResultRowids']);
    $t->same([1, 2], $plan['currentUnknownRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeFive numeric and bool affinity complement'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 123],
        ['option_id' => 2, 'option_value' => 12.5],
        ['option_id' => 3, 'option_value' => true],
        ['option_id' => 4, 'option_value' => false],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueNotLikePlan($rows, $rows, '12%', null, false, true, 'same', 'same', 1, 1);
    $t->same([4, 3], $plan['currentResultRowids']);
    $t->same([2, 1], $plan['currentLikeRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeFive underscore consumes one malformed byte before complement'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => "legacy_\xe2_tail"],
        ['option_id' => 2, 'option_value' => "legacy_\xe2\x82_tail"],
        ['option_id' => 3, 'option_value' => "legacy_\xe2\x82\xac_tail"],
        ['option_id' => 4, 'option_value' => 'legacy_ascii_tail'],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueNotLikePlan($rows, $rows, "legacy!___tail", '!', false, true, 'same', 'same', 1, 1);
    $t->same([4, 2], $plan['currentResultRowids']);
    $t->same([1, 3], $plan['currentLikeRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeFive rejects multi character escape'] = static function (TestRunner $t) use ($current235, $nextTwoThreeFive): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueNotLikePlan($current235, $nextTwoThreeFive, "plugin!_\xe2%", '!!'));
};

$tests['encoding collation affinity like current source nextTwoThreeFive rejects missing option value'] = static function (TestRunner $t) use ($nextTwoThreeFive): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueNotLikePlan([['option_id' => 1]], $nextTwoThreeFive, "plugin!_\xe2%", '!'));
};

$tests['encoding collation affinity like current source nextTwoThreeFive rejects non scalar value'] = static function (TestRunner $t) use ($nextTwoThreeFive): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueNotLikePlan([['option_id' => 1, 'option_value' => ['x']]], $nextTwoThreeFive, "plugin!_\xe2%", '!'));
};

return $tests;
