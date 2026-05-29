<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current242 = [
    ['option_id' => 1, 'option_name' => 'nul_cache_exact', 'option_value' => "plugin\0cache_suffix"],
    ['option_id' => 2, 'option_name' => 'nul_cache_upper', 'option_value' => "Plugin\0Cache_suffix"],
    ['option_id' => 3, 'option_name' => 'nul_cache_literal', 'option_value' => "plugin\0cache_"],
    ['option_id' => 4, 'option_name' => 'nul_cache_false_missing_underscore', 'option_value' => "plugin\0cacheXsuffix"],
    ['option_id' => 5, 'option_name' => 'plain_cache', 'option_value' => 'plugin_cache_suffix'],
    ['option_id' => 6, 'option_name' => 'numeric_value', 'option_value' => 10],
    ['option_id' => 7, 'option_name' => 'blob_value', 'option_value' => new SQLiteBlobValue("plugin\0cache_blob")],
    ['option_id' => 8, 'option_name' => 'null_value', 'option_value' => null],
    ['option_id' => 9, 'option_name' => 'bool_value', 'option_value' => true],
    ['option_id' => 10, 'option_name' => 'nul_other_prefix', 'option_value' => "theme\0cache_suffix"],
];

$next242 = [
    ['option_id' => 1, 'option_name' => 'nul_cache_exact', 'option_value' => "plugin\0cache_suffix2"],
    ['option_id' => 2, 'option_name' => 'nul_cache_upper', 'option_value' => "Plugin\0Cache_suffix"],
    ['option_id' => 3, 'option_name' => 'nul_cache_literal', 'option_value' => "plugin\0cache_"],
    ['option_id' => 4, 'option_name' => 'nul_cache_now_literal', 'option_value' => "plugin\0cache_suffix"],
    ['option_id' => 5, 'option_name' => 'plain_cache', 'option_value' => 'plugin_cache_suffix'],
    ['option_id' => 6, 'option_name' => 'numeric_value', 'option_value' => '10'],
    ['option_id' => 7, 'option_name' => 'blob_value', 'option_value' => new SQLiteBlobValue("plugin\0cache_blob")],
    ['option_id' => 8, 'option_name' => 'null_value', 'option_value' => null],
    ['option_id' => 9, 'option_name' => 'bool_value', 'option_value' => false],
    ['option_id' => 11, 'option_name' => 'nul_cache_new', 'option_value' => "PLUGIN\0CACHE_new"],
    ['option_id' => 12, 'option_name' => 'nul_cache_late', 'option_value' => "plugin\0cache_later"],
];

$plan242 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = "plugin\0cache!_%",
    ?string $escape = '!',
    bool $caseSensitive = false,
    string $currentSource = 'main.wp_options@241',
    string $nextSource = 'main.wp_options@242',
    int $currentCookie = 241,
    int $nextCookie = 242,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressEmbeddedNulLikePlan(
    $current ?? $current242,
    $next ?? $next242,
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
    'status' => ['status', 'encoding-collation-affinity-like-current-source-next242'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'CAST(option_value AS TEXT) COLLATE NOCASE LIKE ? ESCAPE ? /* embedded-NUL literal prefix */'],
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
    'current source' => ['currentSource', 'main.wp_options@241'],
    'next source' => ['nextSource', 'main.wp_options@242'],
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
    'dependency source' => ['dependencies.4', 'sqlite-current-source-next242'],
];

foreach ($cases242 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source next242 ' . $name] = static function (TestRunner $t) use ($plan242, $valueAt242, $path, $expected): void {
        $t->same($expected, $valueAt242($plan242(), $path));
    };
}

$tests['encoding collation affinity like current source next242 stable cursor reusable'] = static function (TestRunner $t) use ($current242, $plan242): void {
    $stable = $plan242(current: $current242, next: $current242, currentSource: 'same', nextSource: 'same', currentCookie: 242, nextCookie: 242);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source next242 case sensitive keeps uppercase out'] = static function (TestRunner $t) use ($current242, $next242, $plan242): void {
    $case = $plan242(current: $current242, next: $next242, caseSensitive: true);
    $t->same('BINARY', $case['collation']);
    $t->same([3, 1], $case['currentMatchedRowids']);
    $t->same([3, 12, 4, 1], $case['nextMatchedRowids']);
};

$tests['encoding collation affinity like current source next242 escaped underscore differs from wildcard underscore'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => "plugin\0cache_suffix"],
        ['option_id' => 2, 'option_value' => "plugin\0cacheXsuffix"],
    ];
    $escaped = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressEmbeddedNulLikePlan($rows, $rows, "plugin\0cache!_%", '!', false, 'same', 'same', 1, 1);
    $wild = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressEmbeddedNulLikePlan($rows, $rows, "plugin\0cache_%", null, false, 'same', 'same', 1, 1);
    $t->same([1], $escaped['currentMatchedRowids']);
    $t->same([2, 1], $wild['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source next242 percent after nul can match empty suffix'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => "plugin\0cache_"],
        ['option_id' => 2, 'option_value' => "plugin\0cache"],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressEmbeddedNulLikePlan($rows, $rows, "plugin\0cache!_%", '!', false, 'same', 'same', 1, 1);
    $t->same([1], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source next242 numeric bool and plain text stay non matches'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 10],
        ['option_id' => 2, 'option_value' => true],
        ['option_id' => 3, 'option_value' => 'plugin_cache_suffix'],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressEmbeddedNulLikePlan($rows, $rows, "plugin\0cache!_%", '!', false, 'same', 'same', 1, 1);
    $t->same([], $plan['currentMatchedRowids']);
    $t->same(['31', '3130', '706c7567696e5f63616368655f737566666978'], array_values($plan['currentTextsHex']));
};

$tests['encoding collation affinity like current source next242 blob and null remain unknown'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => new SQLiteBlobValue("plugin\0cache_blob")],
        ['option_id' => 2, 'option_value' => null],
        ['option_id' => 3, 'option_value' => "plugin\0cache_blob"],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressEmbeddedNulLikePlan($rows, $rows, "plugin\0cache!_%", '!', false, 'same', 'same', 1, 1);
    $t->same([3], $plan['currentMatchedRowids']);
    $t->same([1, 2], $plan['currentUnknownRowids']);
};

$tests['encoding collation affinity like current source next242 rejects multi character escape'] = static function (TestRunner $t) use ($current242, $next242): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressEmbeddedNulLikePlan($current242, $next242, "plugin\0cache!!_%", '!!'));
};

$tests['encoding collation affinity like current source next242 rejects missing option value'] = static function (TestRunner $t) use ($next242): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressEmbeddedNulLikePlan([['option_id' => 1]], $next242));
};

$tests['encoding collation affinity like current source next242 rejects non scalar option value'] = static function (TestRunner $t) use ($next242): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressEmbeddedNulLikePlan([['option_id' => 1, 'option_value' => ['plugin']]], $next242));
};

$tests['encoding collation affinity like current source next242 note fields stay explicit'] = static function (TestRunner $t) use ($plan242): void {
    $plan = $plan242();
    $t->true(str_contains($plan['dependency_closure'], 'no new support component needed'));
    $t->true(str_contains($plan['non_overlap'], 'embedded-NUL TEXT LIKE prefixes'));
    $t->true(str_contains($plan['non_overlap'], 'next239 Unicode/malformed GLOB'));
};

return $tests;
