<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current258 = [
    ['option_id' => 1, 'option_name' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'PLUGIN_cache'],
    ['option_id' => 3, 'option_name' => 'Plugin_Cache'],
    ['option_id' => 4, 'option_name' => 'plugin_%literal'],
    ['option_id' => 5, 'option_name' => 'PLUGIN_%literal'],
    ['option_id' => 6, 'option_name' => 'plugin_alpha'],
    ['option_id' => 7, 'option_name' => 'PLUGIN_Ä'],
    ['option_id' => 8, 'option_name' => null],
    ['option_id' => 9, 'option_name' => new SQLiteBlobValue('PLUGIN_blob')],
    ['option_id' => 10, 'option_name' => 123],
];

$next258 = [
    ['option_id' => 1, 'option_name' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'PLUGIN_cache'],
    ['option_id' => 3, 'option_name' => 'Plugin_Cache'],
    ['option_id' => 4, 'option_name' => 'plugin_%literal'],
    ['option_id' => 5, 'option_name' => 'PLUGIN_%literal'],
    ['option_id' => 6, 'option_name' => 'plugin_alpha'],
    ['option_id' => 7, 'option_name' => 'PLUGIN_Ä'],
    ['option_id' => 11, 'option_name' => 'PLUGIN_new'],
    ['option_id' => 12, 'option_name' => true],
];

$plan258 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'PLUGIN!_%',
    ?string $escape = '!',
    bool $currentCaseSensitive = false,
    bool $nextCaseSensitive = true,
    string $currentSource = 'main.wp_options@257',
    string $nextSource = 'main.wp_options@258',
    int $currentCookie = 257,
    int $nextCookie = 258,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressCaseSensitiveLikeTransitionPlan(
    $current ?? $current258,
    $next ?? $next258,
    $pattern,
    $escape,
    $currentCaseSensitive,
    $nextCaseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt258 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases258 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-next258'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_name LIKE ? ESCAPE ? /* case_sensitive_like current-source fence */'],
    'pattern' => ['pattern', 'PLUGIN!_%'],
    'pattern hex' => ['patternHex', '504c5547494e215f25'],
    'escape' => ['escape', '!'],
    'escape hex' => ['escapeHex', '21'],
    'prefix' => ['prefix', 'PLUGIN_'],
    'prefix hex' => ['prefixHex', '504c5547494e5f'],
    'prefix chars' => ['prefixCharacters', 7],
    'binary lower' => ['binaryRange.lowerInclusive', 'PLUGIN_'],
    'binary upper' => ['binaryRange.upperBound', 'PLUGIN`'],
    'nocase lower' => ['noCaseRange.lowerInclusive', 'plugin_'],
    'nocase upper' => ['noCaseRange.upperBound', 'plugin`'],
    'current case flag' => ['currentCaseSensitiveLike', false],
    'next case flag' => ['nextCaseSensitiveLike', true],
    'current collation' => ['currentCollation', 'NOCASE'],
    'next collation' => ['nextCollation', 'BINARY'],
    'function flag' => ['caseSensitiveLikeChangesFunctionSemantics', true],
    'token flag' => ['caseSensitiveLikeDoesNotChangePatternTokens', true],
    'cursor flag' => ['caseSensitiveLikeInvalidatesPreparedLikeCursor', true],
    'ascii flag' => ['asciiNoCaseFoldsOnlyWhenPragmaIsOff', true],
    'escape flag' => ['escapedUnderscoreRemainsLiteralInBothModes', true],
    'glob flag' => ['globSemanticsUnaffectedByCaseSensitiveLike', true],
    'current source' => ['currentSource', 'main.wp_options@257'],
    'next source' => ['nextSource', 'main.wp_options@258'],
    'current cookie' => ['currentSchemaCookie', 257],
    'next cookie' => ['nextSchemaCookie', 258],
    'current candidates' => ['currentCandidateRowids', [10, 4, 5, 6, 1, 2, 3, 7]],
    'next candidates' => ['nextCandidateRowids', [12, 5, 2, 11, 7, 3, 4, 6, 1]],
    'current matched' => ['currentMatchedRowids', [4, 5, 6, 1, 2, 3, 7]],
    'next matched' => ['nextMatchedRowids', [5, 2, 11, 7]],
    'retained' => ['retainedMatchedRowids', [5, 2, 7]],
    'exited' => ['exitedMatchedRowids', [4, 6, 1, 3]],
    'entered' => ['enteredMatchedRowids', [11]],
    'current unknowns' => ['currentUnknownRowids', [8, 9]],
    'next unknowns' => ['nextUnknownRowids', []],
    'changed truth' => ['changedPredicateTruthRowids', [1, 3, 4, 6]],
    'changed text' => ['changedValueTextRowids', []],
    'changed storage' => ['changedStorageClassRowids', []],
    'current predicate row1' => ['currentPredicateResults.1', true],
    'next predicate row1' => ['nextPredicateResults.1', false],
    'current predicate row5' => ['currentPredicateResults.5', true],
    'next predicate row5' => ['nextPredicateResults.5', true],
    'current text row7' => ['currentValueText.7', 'PLUGIN_Ä'],
    'next text row11' => ['nextValueText.11', 'PLUGIN_new'],
    'current hex row5' => ['currentValueHex.5', '504c5547494e5f256c69746572616c'],
    'next hex row7' => ['nextValueHex.7', '504c5547494e5fc384'],
    'current storage row10' => ['currentStorageClasses.10', 'integer'],
    'next storage row12' => ['nextStorageClasses.12', 'integer'],
    'current sort row1' => ['currentSortKeys.1', 'plugin_cache'],
    'next sort row1' => ['nextSortKeys.1', 'plugin_cache'],
    'current glob probe' => ['currentGlobProbeRowids', [5, 2, 7]],
    'next glob probe' => ['nextGlobProbeRowids', [5, 2, 11, 7]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason pragma' => ['invalidationReasons.2', 'case-sensitive-like'],
    'reason rowset' => ['invalidationReasons.3', 'matched-rowset'],
    'reason truth' => ['invalidationReasons.4', 'predicate-truth'],
    'dependency pragma' => ['dependencies.0', 'sqlite-like-case-sensitive-pragma'],
    'dependency tokenizer' => ['dependencies.1', 'sqlite-like-escape-tokenizer'],
    'dependency affinity' => ['dependencies.2', 'sqlite-text-affinity'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-next258'],
];

foreach ($cases258 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source next258 ' . $name] = static function (TestRunner $t) use ($plan258, $valueAt258, $path, $expected): void {
        $t->same($expected, $valueAt258($plan258(), $path));
    };
}

$tests['encoding collation affinity like current source next258 stable nocase cursor reusable'] = static function (TestRunner $t) use ($current258, $plan258): void {
    $stable = $plan258(current: $current258, next: $current258, currentCaseSensitive: false, nextCaseSensitive: false, currentSource: 'same', nextSource: 'same', currentCookie: 258, nextCookie: 258);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([4, 5, 6, 1, 2, 3, 7], $stable['currentMatchedRowids']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source next258 stable binary cursor reusable'] = static function (TestRunner $t) use ($current258, $plan258): void {
    $stable = $plan258(current: $current258, next: $current258, currentCaseSensitive: true, nextCaseSensitive: true, currentSource: 'same', nextSource: 'same', currentCookie: 258, nextCookie: 258);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([5, 2, 7], $stable['currentMatchedRowids']);
    $t->same([5, 2, 7], $stable['currentGlobProbeRowids']);
};

$tests['encoding collation affinity like current source next258 omitted escape wildcard expands'] = static function (TestRunner $t) use ($current258, $plan258): void {
    $plan = $plan258(current: $current258, next: $current258, pattern: 'PLUGIN_%', escape: null, currentCaseSensitive: false, nextCaseSensitive: true, currentSource: 'same', nextSource: 'same', currentCookie: 258, nextCookie: 258);
    $t->same([4, 5, 6, 1, 2, 3, 7], $plan['currentMatchedRowids']);
    $t->same([5, 2, 7], $plan['nextMatchedRowids']);
    $t->same(['case-sensitive-like', 'matched-rowset', 'predicate-truth'], $plan['invalidationReasons']);
};

$tests['encoding collation affinity like current source next258 numeric and boolean option names use text affinity'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 123],
        ['option_id' => 2, 'option_name' => true],
        ['option_id' => 3, 'option_name' => false],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressCaseSensitiveLikeTransitionPlan($rows, $rows, '1%', null, false, true, 'same', 'same', 1, 1);
    $t->same([2, 1], $plan['currentMatchedRowids']);
    $t->same([2, 1], $plan['nextMatchedRowids']);
    $t->same('integer', $plan['currentStorageClasses'][1]);
    $t->same('integer', $plan['currentStorageClasses'][2]);
};

$tests['encoding collation affinity like current source next258 rejects invalid escape length'] = static function (TestRunner $t) use ($current258, $next258): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressCaseSensitiveLikeTransitionPlan($current258, $next258, 'PLUGIN!!_%', '!!'));
};

$tests['encoding collation affinity like current source next258 rejects missing option name'] = static function (TestRunner $t) use ($next258): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressCaseSensitiveLikeTransitionPlan([['option_id' => 1]], $next258));
};

$tests['encoding collation affinity like current source next258 rejects array option name'] = static function (TestRunner $t) use ($next258): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressCaseSensitiveLikeTransitionPlan([['option_id' => 1, 'option_name' => ['PLUGIN']]], $next258));
};

return $tests;
