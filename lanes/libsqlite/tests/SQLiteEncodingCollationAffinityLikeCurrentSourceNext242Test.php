<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current242 = [
    ['setting_id' => 1, 'key_name' => 'nul_cache_exact', 'key_value' => "plugin\0cache_suffix"],
    ['setting_id' => 2, 'key_name' => 'nul_cache_upper', 'key_value' => "Plugin\0Cache_suffix"],
    ['setting_id' => 3, 'key_name' => 'nul_cache_literal', 'key_value' => "plugin\0cache_"],
    ['setting_id' => 4, 'key_name' => 'nul_cache_false_missing_underscore', 'key_value' => "plugin\0cacheXsuffix"],
    ['setting_id' => 5, 'key_name' => 'plain_cache', 'key_value' => 'plugin_cache_suffix'],
    ['setting_id' => 6, 'key_name' => 'numeric_value', 'key_value' => 10],
    ['setting_id' => 7, 'key_name' => 'blob_value', 'key_value' => new SQLiteBlobValue("plugin\0cache_blob")],
    ['setting_id' => 8, 'key_name' => 'null_value', 'key_value' => null],
    ['setting_id' => 9, 'key_name' => 'bool_value', 'key_value' => true],
    ['setting_id' => 10, 'key_name' => 'nul_other_prefix', 'key_value' => "theme\0cache_suffix"],
];

$nextTwoFourTwo = [
    ['setting_id' => 1, 'key_name' => 'nul_cache_exact', 'key_value' => "plugin\0cache_suffix2"],
    ['setting_id' => 2, 'key_name' => 'nul_cache_upper', 'key_value' => "Plugin\0Cache_suffix"],
    ['setting_id' => 3, 'key_name' => 'nul_cache_literal', 'key_value' => "plugin\0cache_"],
    ['setting_id' => 4, 'key_name' => 'nul_cache_now_literal', 'key_value' => "plugin\0cache_suffix"],
    ['setting_id' => 5, 'key_name' => 'plain_cache', 'key_value' => 'plugin_cache_suffix'],
    ['setting_id' => 6, 'key_name' => 'numeric_value', 'key_value' => '10'],
    ['setting_id' => 7, 'key_name' => 'blob_value', 'key_value' => new SQLiteBlobValue("plugin\0cache_blob")],
    ['setting_id' => 8, 'key_name' => 'null_value', 'key_value' => null],
    ['setting_id' => 9, 'key_name' => 'bool_value', 'key_value' => false],
    ['setting_id' => 11, 'key_name' => 'nul_cache_new', 'key_value' => "PLUGIN\0CACHE_new"],
    ['setting_id' => 12, 'key_name' => 'nul_cache_late', 'key_value' => "plugin\0cache_later"],
];

$plan242 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = "plugin\0cache!_%",
    ?string $escape = '!',
    bool $caseSensitive = false,
    string $currentSource = 'main.app_settings@241',
    string $nextSource = 'main.app_settings@242',
    int $currentCookie = 241,
    int $nextCookie = 242,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationEmbeddedNulLikePlan(
    $current ?? $current242,
    $next ?? $nextTwoFourTwo,
    $pattern,
    $escape,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt242 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases242 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFourTwo'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'CAST(key_value AS TEXT) COLLATE NOCASE LIKE ? ESCAPE ? /* embedded-NUL literal prefix */'],
    'pattern hex' => ['patternHex', '706c7567696e006361636865215f25'],
    'pattern tokens' => ['patternTokenHex', ['70', '6c', '75', '67', '69', '6e', '00', '63', '61', '63', '68', '65', '21', '5f', '25']],
    'pattern chars' => ['patternCharacterCount', 15],
    'escape' => ['escape', '!'],
    'escape hex' => ['escapeHex', '21'],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'NOCASE'],
    'prefix hex' => ['prefixHex', '706c7567696e0063616368655f'],
    'prefix tokens' => ['prefixTokenHex', ['70', '6c', '75', '67', '69', '6e', '00', '63', '61', '63', '68', '65', '5f']],
    'prefix chars' => ['prefixCharacters', 13],
    'prefix contains nul' => ['prefixContainsNul', true],
    'prefix ascii' => ['prefixIsAscii', true],
    'range lower' => ['rangeLowerInclusiveHex', '706c7567696e0063616368655f'],
    'range upper' => ['rangeUpperBoundHex', '706c7567696e00636163686560'],
    'current source' => ['currentSource', 'main.app_settings@241'],
    'next source' => ['nextSource', 'main.app_settings@242'],
    'current cookie' => ['currentSchemaCookie', 241],
    'next cookie' => ['nextSchemaCookie', 242],
    'current matched' => ['currentMatchedRowids', [2, 3, 1]],
    'next matched' => ['nextMatchedRowids', [11, 2, 3, 12, 4, 1]],
    'retained' => ['retainedMatchedRowids', [2, 3, 1]],
    'exited' => ['exitedMatchedRowids', []],
    'entered' => ['enteredMatchedRowids', [11, 12, 4]],
    'current unknown' => ['currentUnknownRowids', [7, 8]],
    'next unknown' => ['nextUnknownRowids', [7, 8]],
    'changed bytes' => ['changedBytesRowids', [1, 4, 9]],
    'changed truth' => ['changedLikeTruthRowids', [4]],
    'changed storage' => ['changedStorageRowids', [6]],
    'current one hex' => ['currentTextsHex.1', '706c7567696e0063616368655f737566666978'],
    'current upper hex' => ['currentTextsHex.2', '506c7567696e0043616368655f737566666978'],
    'current plain hex' => ['currentTextsHex.5', '706c7567696e5f63616368655f737566666978'],
    'next eleven hex' => ['nextTextsHex.11', '504c5547494e0043414348455f6e6577'],
    'current one tokens' => ['currentTokenHex.1', ['70', '6c', '75', '67', '69', '6e', '00', '63', '61', '63', '68', '65', '5f', '73', '75', '66', '66', '69', '78']],
    'next eleven tokens' => ['nextTokenHex.11', ['50', '4c', '55', '47', '49', '4e', '00', '43', '41', '43', '48', '45', '5f', '6e', '65', '77']],
    'current token count one' => ['currentTokenCounts.1', 19],
    'next token count eleven' => ['nextTokenCounts.11', 16],
    'current storage one' => ['currentStorage.1', 'text'],
    'current storage six' => ['currentStorage.6', 'integer'],
    'next storage six' => ['nextStorage.6', 'text'],
    'current like one' => ['currentLikeResults.1', true],
    'current like four' => ['currentLikeResults.4', false],
    'next like four' => ['nextLikeResults.4', true],
    'current like five' => ['currentLikeResults.5', false],
    'current like six' => ['currentLikeResults.6', false],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason rowset' => ['invalidationReasons.2', 'matched-rowset'],
    'reason bytes' => ['invalidationReasons.3', 'embedded-nul-text-bytes'],
    'reason truth' => ['invalidationReasons.4', 'like-truth'],
    'reason storage' => ['invalidationReasons.5', 'storage-class'],
    'nul flag' => ['embeddedNulIsOrdinaryLikeCharacter', true],
    'underscore flag' => ['escapedUnderscoreIsLiteral', true],
    'percent flag' => ['percentWildcardRunsAfterNulPrefix', true],
    'nocase flag' => ['nocaseFoldsAsciiOnlyAroundNul', true],
    'unknown flag' => ['nullAndBlobRemainUnknown', true],
    'dependency tokenizer' => ['dependencies.0', 'sqlite-like-embedded-nul-tokenizer'],
    'dependency escape' => ['dependencies.1', 'sqlite-like-escape-prefix-range'],
    'dependency collation' => ['dependencies.2', 'sqlite-nocase-ascii-collation'],
    'dependency affinity' => ['dependencies.3', 'sqlite-text-affinity'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoFourTwo'],
];

foreach ($cases242 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFourTwo ' . $name] = static function (TestRunner $t) use ($plan242, $valueAt242, $path, $expected): void {
        $t->same($expected, $valueAt242($plan242(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFourTwo stable cursor reusable'] = static function (TestRunner $t) use ($current242, $plan242): void {
    $stable = $plan242(current: $current242, next: $current242, currentSource: 'same', nextSource: 'same', currentCookie: 242, nextCookie: 242);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFourTwo case sensitive keeps uppercase out'] = static function (TestRunner $t) use ($current242, $nextTwoFourTwo, $plan242): void {
    $case = $plan242(current: $current242, next: $nextTwoFourTwo, caseSensitive: true);
    $t->same('BINARY', $case['collation']);
    $t->same([3, 1], $case['currentMatchedRowids']);
    $t->same([3, 12, 4, 1], $case['nextMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourTwo escaped underscore differs from wildcard underscore'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => "plugin\0cache_suffix"],
        ['setting_id' => 2, 'key_value' => "plugin\0cacheXsuffix"],
    ];
    $escaped = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationEmbeddedNulLikePlan($rows, $rows, "plugin\0cache!_%", '!', false, 'same', 'same', 1, 1);
    $wild = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationEmbeddedNulLikePlan($rows, $rows, "plugin\0cache_%", null, false, 'same', 'same', 1, 1);
    $t->same([1], $escaped['currentMatchedRowids']);
    $t->same([2, 1], $wild['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourTwo percent after nul can match empty suffix'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => "plugin\0cache_"],
        ['setting_id' => 2, 'key_value' => "plugin\0cache"],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationEmbeddedNulLikePlan($rows, $rows, "plugin\0cache!_%", '!', false, 'same', 'same', 1, 1);
    $t->same([1], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourTwo numeric bool and plain text stay non matches'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 10],
        ['setting_id' => 2, 'key_value' => true],
        ['setting_id' => 3, 'key_value' => 'plugin_cache_suffix'],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationEmbeddedNulLikePlan($rows, $rows, "plugin\0cache!_%", '!', false, 'same', 'same', 1, 1);
    $t->same([], $plan['currentMatchedRowids']);
    $t->same(['31', '3130', '706c7567696e5f63616368655f737566666978'], array_values($plan['currentTextsHex']));
};

$tests['encoding collation affinity like current source nextTwoFourTwo blob and null remain unknown'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => new SQLiteBlobValue("plugin\0cache_blob")],
        ['setting_id' => 2, 'key_value' => null],
        ['setting_id' => 3, 'key_value' => "plugin\0cache_blob"],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationEmbeddedNulLikePlan($rows, $rows, "plugin\0cache!_%", '!', false, 'same', 'same', 1, 1);
    $t->same([3], $plan['currentMatchedRowids']);
    $t->same([1, 2], $plan['currentUnknownRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourTwo rejects multi character escape'] = static function (TestRunner $t) use ($current242, $nextTwoFourTwo): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationEmbeddedNulLikePlan($current242, $nextTwoFourTwo, "plugin\0cache!!_%", '!!'));
};

$tests['encoding collation affinity like current source nextTwoFourTwo rejects missing key value'] = static function (TestRunner $t) use ($nextTwoFourTwo): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationEmbeddedNulLikePlan([['setting_id' => 1]], $nextTwoFourTwo));
};

$tests['encoding collation affinity like current source nextTwoFourTwo rejects non scalar key value'] = static function (TestRunner $t) use ($nextTwoFourTwo): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationEmbeddedNulLikePlan([['setting_id' => 1, 'key_value' => ['plugin']]], $nextTwoFourTwo));
};

$tests['encoding collation affinity like current source nextTwoFourTwo note fields stay explicit'] = static function (TestRunner $t) use ($plan242): void {
    $plan = $plan242();
    $t->true(str_contains($plan['dependency_closure'], 'no new support component needed'));
    $t->true(str_contains($plan['non_overlap'], 'embedded-NUL TEXT LIKE prefixes'));
    $t->true(str_contains($plan['non_overlap'], 'nextTwoThreeNine Unicode/malformed GLOB'));
};

return $tests;
